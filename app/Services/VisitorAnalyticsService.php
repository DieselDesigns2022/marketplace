<?php

namespace App\Services;

use App\Core\Database as DB;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class VisitorAnalyticsService
{
    private const SESSION_TIMEOUT = 1800;
    private const MAX_DURATION = 21600;

    public static function captureRequest(): void
    {
        try {
            $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

            if (!in_array($method, ['GET', 'HEAD'], true)) {
                unset($_SESSION['_visitor_analytics_page_view_id']);
                return;
            }

            $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
            $user = $_SESSION['user'] ?? null;
            $type = self::visitorType(is_array($user) ? $user : null);

            if ($type === null || !self::trackablePath($path) || self::isBot()) {
                unset($_SESSION['_visitor_analytics_page_view_id']);
                return;
            }

            $ip = self::clientIp();

            if ($ip === '') {
                unset($_SESSION['_visitor_analytics_page_view_id']);
                return;
            }

            $now = self::now();
            $nowString = $now->format('Y-m-d H:i:s');
            $visitDate = $now->format('Y-m-d');
            $visitorHash = self::hash($ip);
            $userId = !empty($user['id']) ? (int)$user['id'] : null;

            $state = $_SESSION['_visitor_analytics'] ?? null;
            $timestamp = $now->getTimestamp();

            if (
                !is_array($state)
                || empty($state['token'])
                || empty($state['last_seen'])
                || ($timestamp - (int)$state['last_seen']) > self::SESSION_TIMEOUT
            ) {
                $state = [
                    'token' => bin2hex(random_bytes(24)),
                    'last_seen' => $timestamp,
                ];
            } else {
                $state['last_seen'] = $timestamp;
            }

            $_SESSION['_visitor_analytics'] = $state;

            $sessionHash = self::hash('session:' . $state['token']);

            DB::exec(
                'insert into analytics_daily_visitors
                    (visit_date,visitor_hash,user_id,visitor_type,first_path,last_path,first_seen_at,last_seen_at)
                 values (?,?,?,?,?,?,?,?)
                 on duplicate key update
                    user_id=coalesce(values(user_id),user_id),
                    visitor_type=case
                        when visitor_type="seller" or values(visitor_type)="seller" then "seller"
                        when visitor_type="buyer" or values(visitor_type)="buyer" then "buyer"
                        else "guest"
                    end,
                    last_path=values(last_path),
                    last_seen_at=values(last_seen_at)',
                [
                    $visitDate,
                    $visitorHash,
                    $userId,
                    $type,
                    mb_substr($path, 0, 500),
                    mb_substr($path, 0, 500),
                    $nowString,
                    $nowString,
                ]
            );

            $existingSession = DB::row(
                'select id from analytics_sessions where session_hash=? limit 1',
                [$sessionHash]
            );

            $isEntry = $existingSession ? 0 : 1;

            DB::exec(
                'insert into analytics_sessions
                    (session_hash,visitor_hash,user_id,visitor_type,started_at,last_seen_at,landing_path,last_path,page_views)
                 values (?,?,?,?,?,?,?,?,1)
                 on duplicate key update
                    user_id=coalesce(values(user_id),user_id),
                    visitor_type=case
                        when visitor_type="seller" or values(visitor_type)="seller" then "seller"
                        when visitor_type="buyer" or values(visitor_type)="buyer" then "buyer"
                        else "guest"
                    end,
                    last_seen_at=values(last_seen_at),
                    last_path=values(last_path),
                    page_views=page_views+1',
                [
                    $sessionHash,
                    $visitorHash,
                    $userId,
                    $type,
                    $nowString,
                    $nowString,
                    mb_substr($path, 0, 500),
                    mb_substr($path, 0, 500),
                ]
            );

            DB::exec(
                'update analytics_page_views
                 set is_exit=0
                 where session_hash=? and is_exit=1',
                [$sessionHash]
            );

            DB::exec(
                'insert into analytics_page_views
                    (session_hash,visitor_hash,visit_date,user_id,visitor_type,path,viewed_at,is_entry)
                 values (?,?,?,?,?,?,?,?)',
                [
                    $sessionHash,
                    $visitorHash,
                    $visitDate,
                    $userId,
                    $type,
                    mb_substr($path, 0, 500),
                    $nowString,
                    $isEntry,
                ]
            );

            $_SESSION['_visitor_analytics_page_view_id'] = (int)DB::id();
        } catch (Throwable $error) {
            unset($_SESSION['_visitor_analytics_page_view_id']);
            error_log('Visitor analytics tracking failed: ' . $error->getMessage());
        }
    }

    public static function recordEngagement(array $input): void
    {
        try {
            $pageViewId = (int)($input['page_view_id'] ?? 0);
            $duration = max(0, min(self::MAX_DURATION, (int)($input['duration_seconds'] ?? 0)));
            $state = $_SESSION['_visitor_analytics'] ?? null;

            if (
                $pageViewId <= 0
                || !is_array($state)
                || empty($state['token'])
            ) {
                return;
            }

            $sessionHash = self::hash('session:' . $state['token']);

            $page = DB::row(
                'select id,path,visit_date,visitor_hash
                 from analytics_page_views
                 where id=? and session_hash=?
                 limit 1',
                [$pageViewId, $sessionHash]
            );

            if (!$page) {
                return;
            }

            $now = self::now();
            $nowString = $now->format('Y-m-d H:i:s');

            DB::exec(
                'update analytics_page_views
                 set is_exit=0
                 where session_hash=? and id<>?',
                [$sessionHash, $pageViewId]
            );

            DB::exec(
                'update analytics_page_views
                 set duration_seconds=greatest(duration_seconds,?),
                     is_exit=1,
                     confirmed_at=coalesce(confirmed_at,?)
                 where id=? and session_hash=?',
                [$duration, $nowString, $pageViewId, $sessionHash]
            );

            DB::exec(
                'update analytics_sessions
                 set last_seen_at=?,
                     last_path=?,
                     confirmed_at=coalesce(confirmed_at,?)
                 where session_hash=?',
                [
                    $nowString,
                    mb_substr((string)$page['path'], 0, 500),
                    $nowString,
                    $sessionHash
                ]
            );

            DB::exec(
                'update analytics_daily_visitors
                 set last_seen_at=?,
                     last_path=?,
                     confirmed_at=coalesce(confirmed_at,?)
                 where visit_date=? and visitor_hash=?',
                [
                    $nowString,
                    mb_substr((string)$page['path'], 0, 500),
                    $nowString,
                    $page['visit_date'],
                    $page['visitor_hash'],
                ]
            );

            $_SESSION['_visitor_analytics']['last_seen'] = $now->getTimestamp();
        } catch (Throwable $error) {
            error_log('Visitor analytics engagement failed: ' . $error->getMessage());
        }
    }

    private static function visitorType(?array $user): ?string
    {
        if (!$user) {
            return 'guest';
        }

        $role = (string)($user['role'] ?? 'buyer');

        if ($role === 'admin') {
            return null;
        }

        if ($role === 'designer') {
            return 'seller';
        }

        if (!empty($user['id'])) {
            $seller = DB::row(
                'select id
                 from designers
                 where user_id=? and status="approved"
                 limit 1',
                [(int)$user['id']]
            );

            if ($seller) {
                return 'seller';
            }
        }

        return 'buyer';
    }

    private static function trackablePath(string $path): bool
    {
        if (
            $path === '/sitemap.xml'
            || $path === '/analytics/engagement'
            || $path === '/stripe/webhook'
            || str_starts_with($path, '/admin')
            || str_starts_with($path, '/download/')
            || str_starts_with($path, '/promo-library/download/')
        ) {
            return false;
        }

        return true;
    }

    private static function isBot(): bool
    {
        $agent = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

        if ($agent === '') {
            return false;
        }

        return preg_match(
            '/bot|crawler|spider|slurp|facebookexternalhit|preview|monitor|uptime|curl|wget|headless/i',
            $agent
        ) === 1;
    }

    private static function clientIp(): string
    {
        $candidate = trim((string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));

        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }

        $candidate = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));

        return filter_var($candidate, FILTER_VALIDATE_IP) ? $candidate : '';
    }

    private static function hash(string $value): string
    {
        $salt = (string)(
            $_ENV['ANALYTICS_HASH_SALT']
            ?? $_ENV['APP_KEY']
            ?? $_ENV['DB_PASS']
            ?? 'creative-moth-visitor-analytics'
        );

        return hash_hmac('sha256', $value, $salt);
    }

    private static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(
            'now',
            new DateTimeZone($_ENV['APP_TIMEZONE'] ?? 'America/Detroit')
        );
    }
}
