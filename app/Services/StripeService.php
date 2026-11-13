<?php

namespace App\Services;

use App\Core\Database as DB;
use Throwable;

class StripeService
{
    private static $testTransport = null;

    public static function setTestTransport(?callable $transport): void
    {
        if (PHP_SAPI !== 'cli') throw new \LogicException('Stripe test transport is CLI-only.');
        self::$testTransport = $transport;
    }
    public static function secretKey(): string { return trim((string)($_ENV['STRIPE_SECRET_KEY'] ?? '')); }
    public static function webhookSecret(): string { return trim((string)($_ENV['STRIPE_WEBHOOK_SECRET'] ?? '')); }
    public static function connectWebhookSecret(): string { return trim((string)($_ENV['STRIPE_CONNECT_WEBHOOK_SECRET'] ?? '')); }
    public static function currency(): string { return strtolower(trim((string)($_ENV['STRIPE_CURRENCY'] ?? 'usd')) ?: 'usd'); }
    public static function commissionBasisPoints(): int { return max(0, min(10000, (int)round((float)($_ENV['PLATFORM_COMMISSION_PERCENT'] ?? 9) * 100))); }
    public static function commissionFixedCents(): int { return max(0, (int)($_ENV['PLATFORM_COMMISSION_FIXED_CENTS'] ?? 30)); }
    public static function commissionRate(): float { return self::commissionBasisPoints() / 10000; }
    public static function appUrl(): string
    {
        return \App\Core\Helpers::baseUrl();
    }
    public static function configured(): bool { return self::secretKey() !== ''; }
    public static function cents($amount): int { return CreditService::parseCents((string)$amount); }

    public static function countryOptions(): array
    {
        return [
            'AD' => 'Andorra',
            'AE' => 'United Arab Emirates',
            'AF' => 'Afghanistan',
            'AG' => 'Antigua & Barbuda',
            'AI' => 'Anguilla',
            'AL' => 'Albania',
            'AM' => 'Armenia',
            'AO' => 'Angola',
            'AQ' => 'Antarctica',
            'AR' => 'Argentina',
            'AS' => 'Samoa (American)',
            'AT' => 'Austria',
            'AU' => 'Australia',
            'AW' => 'Aruba',
            'AX' => 'Åland Islands',
            'AZ' => 'Azerbaijan',
            'BA' => 'Bosnia & Herzegovina',
            'BB' => 'Barbados',
            'BD' => 'Bangladesh',
            'BE' => 'Belgium',
            'BF' => 'Burkina Faso',
            'BG' => 'Bulgaria',
            'BH' => 'Bahrain',
            'BI' => 'Burundi',
            'BJ' => 'Benin',
            'BL' => 'St Barthelemy',
            'BM' => 'Bermuda',
            'BN' => 'Brunei',
            'BO' => 'Bolivia',
            'BQ' => 'Caribbean NL',
            'BR' => 'Brazil',
            'BS' => 'Bahamas',
            'BT' => 'Bhutan',
            'BV' => 'Bouvet Island',
            'BW' => 'Botswana',
            'BY' => 'Belarus',
            'BZ' => 'Belize',
            'CA' => 'Canada',
            'CC' => 'Cocos (Keeling) Islands',
            'CD' => 'Congo (Dem. Rep.)',
            'CF' => 'Central African Rep.',
            'CG' => 'Congo (Rep.)',
            'CH' => 'Switzerland',
            'CI' => 'Côte d’Ivoire',
            'CK' => 'Cook Islands',
            'CL' => 'Chile',
            'CM' => 'Cameroon',
            'CN' => 'China',
            'CO' => 'Colombia',
            'CR' => 'Costa Rica',
            'CU' => 'Cuba',
            'CV' => 'Cape Verde',
            'CW' => 'Curaçao',
            'CX' => 'Christmas Island',
            'CY' => 'Cyprus',
            'CZ' => 'Czech Republic',
            'DE' => 'Germany',
            'DJ' => 'Djibouti',
            'DK' => 'Denmark',
            'DM' => 'Dominica',
            'DO' => 'Dominican Republic',
            'DZ' => 'Algeria',
            'EC' => 'Ecuador',
            'EE' => 'Estonia',
            'EG' => 'Egypt',
            'EH' => 'Western Sahara',
            'ER' => 'Eritrea',
            'ES' => 'Spain',
            'ET' => 'Ethiopia',
            'FI' => 'Finland',
            'FJ' => 'Fiji',
            'FK' => 'Falkland Islands',
            'FM' => 'Micronesia',
            'FO' => 'Faroe Islands',
            'FR' => 'France',
            'GA' => 'Gabon',
            'GB' => 'Britain (UK)',
            'GD' => 'Grenada',
            'GE' => 'Georgia',
            'GF' => 'French Guiana',
            'GG' => 'Guernsey',
            'GH' => 'Ghana',
            'GI' => 'Gibraltar',
            'GL' => 'Greenland',
            'GM' => 'Gambia',
            'GN' => 'Guinea',
            'GP' => 'Guadeloupe',
            'GQ' => 'Equatorial Guinea',
            'GR' => 'Greece',
            'GS' => 'South Georgia & the South Sandwich Islands',
            'GT' => 'Guatemala',
            'GU' => 'Guam',
            'GW' => 'Guinea-Bissau',
            'GY' => 'Guyana',
            'HK' => 'Hong Kong',
            'HM' => 'Heard Island & McDonald Islands',
            'HN' => 'Honduras',
            'HR' => 'Croatia',
            'HT' => 'Haiti',
            'HU' => 'Hungary',
            'ID' => 'Indonesia',
            'IE' => 'Ireland',
            'IL' => 'Israel',
            'IM' => 'Isle of Man',
            'IN' => 'India',
            'IO' => 'British Indian Ocean Territory',
            'IQ' => 'Iraq',
            'IR' => 'Iran',
            'IS' => 'Iceland',
            'IT' => 'Italy',
            'JE' => 'Jersey',
            'JM' => 'Jamaica',
            'JO' => 'Jordan',
            'JP' => 'Japan',
            'KE' => 'Kenya',
            'KG' => 'Kyrgyzstan',
            'KH' => 'Cambodia',
            'KI' => 'Kiribati',
            'KM' => 'Comoros',
            'KN' => 'St Kitts & Nevis',
            'KP' => 'Korea (North)',
            'KR' => 'Korea (South)',
            'KW' => 'Kuwait',
            'KY' => 'Cayman Islands',
            'KZ' => 'Kazakhstan',
            'LA' => 'Laos',
            'LB' => 'Lebanon',
            'LC' => 'St Lucia',
            'LI' => 'Liechtenstein',
            'LK' => 'Sri Lanka',
            'LR' => 'Liberia',
            'LS' => 'Lesotho',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'LV' => 'Latvia',
            'LY' => 'Libya',
            'MA' => 'Morocco',
            'MC' => 'Monaco',
            'MD' => 'Moldova',
            'ME' => 'Montenegro',
            'MF' => 'St Martin (French)',
            'MG' => 'Madagascar',
            'MH' => 'Marshall Islands',
            'MK' => 'North Macedonia',
            'ML' => 'Mali',
            'MM' => 'Myanmar (Burma)',
            'MN' => 'Mongolia',
            'MO' => 'Macau',
            'MP' => 'Northern Mariana Islands',
            'MQ' => 'Martinique',
            'MR' => 'Mauritania',
            'MS' => 'Montserrat',
            'MT' => 'Malta',
            'MU' => 'Mauritius',
            'MV' => 'Maldives',
            'MW' => 'Malawi',
            'MX' => 'Mexico',
            'MY' => 'Malaysia',
            'MZ' => 'Mozambique',
            'NA' => 'Namibia',
            'NC' => 'New Caledonia',
            'NE' => 'Niger',
            'NF' => 'Norfolk Island',
            'NG' => 'Nigeria',
            'NI' => 'Nicaragua',
            'NL' => 'Netherlands',
            'NO' => 'Norway',
            'NP' => 'Nepal',
            'NR' => 'Nauru',
            'NU' => 'Niue',
            'NZ' => 'New Zealand',
            'OM' => 'Oman',
            'PA' => 'Panama',
            'PE' => 'Peru',
            'PF' => 'French Polynesia',
            'PG' => 'Papua New Guinea',
            'PH' => 'Philippines',
            'PK' => 'Pakistan',
            'PL' => 'Poland',
            'PM' => 'St Pierre & Miquelon',
            'PN' => 'Pitcairn',
            'PR' => 'Puerto Rico',
            'PS' => 'Palestine',
            'PT' => 'Portugal',
            'PW' => 'Palau',
            'PY' => 'Paraguay',
            'QA' => 'Qatar',
            'RE' => 'Réunion',
            'RO' => 'Romania',
            'RS' => 'Serbia',
            'RU' => 'Russia',
            'RW' => 'Rwanda',
            'SA' => 'Saudi Arabia',
            'SB' => 'Solomon Islands',
            'SC' => 'Seychelles',
            'SD' => 'Sudan',
            'SE' => 'Sweden',
            'SG' => 'Singapore',
            'SH' => 'St Helena',
            'SI' => 'Slovenia',
            'SJ' => 'Svalbard & Jan Mayen',
            'SK' => 'Slovakia',
            'SL' => 'Sierra Leone',
            'SM' => 'San Marino',
            'SN' => 'Senegal',
            'SO' => 'Somalia',
            'SR' => 'Suriname',
            'SS' => 'South Sudan',
            'ST' => 'Sao Tome & Principe',
            'SV' => 'El Salvador',
            'SX' => 'St Maarten (Dutch)',
            'SY' => 'Syria',
            'SZ' => 'Eswatini (Swaziland)',
            'TC' => 'Turks & Caicos Is',
            'TD' => 'Chad',
            'TF' => 'French S. Terr.',
            'TG' => 'Togo',
            'TH' => 'Thailand',
            'TJ' => 'Tajikistan',
            'TK' => 'Tokelau',
            'TL' => 'East Timor',
            'TM' => 'Turkmenistan',
            'TN' => 'Tunisia',
            'TO' => 'Tonga',
            'TR' => 'Turkey',
            'TT' => 'Trinidad & Tobago',
            'TV' => 'Tuvalu',
            'TW' => 'Taiwan',
            'TZ' => 'Tanzania',
            'UA' => 'Ukraine',
            'UG' => 'Uganda',
            'UM' => 'US minor outlying islands',
            'US' => 'United States',
            'UY' => 'Uruguay',
            'UZ' => 'Uzbekistan',
            'VA' => 'Vatican City',
            'VC' => 'St Vincent',
            'VE' => 'Venezuela',
            'VG' => 'Virgin Islands (UK)',
            'VI' => 'Virgin Islands (US)',
            'VN' => 'Vietnam',
            'VU' => 'Vanuatu',
            'WF' => 'Wallis & Futuna',
            'WS' => 'Samoa (western)',
            'YE' => 'Yemen',
            'YT' => 'Mayotte',
            'ZA' => 'South Africa',
            'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe',
        ];
    }

    public static function normalizeCountryCode(string $country): string
    {
        $raw = trim($country);

        if ($raw === '') {
            return '';
        }

        $upper = strtoupper($raw);

        $aliases = [
            'USA' => 'US',
            'U.S.' => 'US',
            'U.S.A.' => 'US',
            'UNITED STATES OF AMERICA' => 'US',
            'UK' => 'GB',
            'UNITED KINGDOM' => 'GB',
            'GREAT BRITAIN' => 'GB',
        ];

        if (isset($aliases[$upper])) {
            return $aliases[$upper];
        }

        $countries = self::countryOptions();

        if (isset($countries[$upper])) {
            return $upper;
        }

        foreach ($countries as $code => $name) {
            if (strcasecmp($raw, $name) === 0) {
                return $code;
            }
        }

        return '';
    }

    public static function calculateTax(array $items, array $address, string $idempotencyKey): array
    {
        if (!self::configured()) {
            throw new \RuntimeException(
                'Stripe is not configured for authoritative tax calculation.'
            );
        }

        $country = self::normalizeCountryCode(
            (string)($address['country'] ?? '')
        );

        if ($country === '') {
            throw new \InvalidArgumentException(
                'Please select a valid billing country.'
            );
        }

        foreach (['line1', 'city', 'postal_code'] as $field) {
            if (trim((string)($address[$field] ?? '')) === '') {
                throw new \InvalidArgumentException(
                    'A complete billing address is required.'
                );
            }
        }

        $state = strtoupper(
            trim((string)($address['state'] ?? ''))
        );

        if ($country === 'US') {
            if (
                !preg_match('/^[A-Z]{2}$/', $state) ||
                !preg_match(
                    '/^\d{5}(?:-\d{4})?$/',
                    trim((string)$address['postal_code'])
                )
            ) {
                throw new \InvalidArgumentException(
                    'A valid US state and ZIP code are required.'
                );
            }
        }

        if ($country === 'CA' && $state === '') {
            throw new \InvalidArgumentException(
                'Province or territory is required for Canadian billing addresses.'
            );
        }

        $address['country'] = $country;

        $lineItems = [];

        foreach ($items as $index => $item) {
            $lineItems[] = [
                'amount' => self::cents((string)$item['total_price']),
                'reference' => 'item_' . ($item['id'] ?? $index),
                'tax_code' => (string)(
                    $_ENV['STRIPE_DIGITAL_TAX_CODE'] ?? 'txcd_10103000'
                ),
            ];
        }

        $calculation = self::request(
            'POST',
            '/v1/tax/calculations',
            [
                'currency' => self::currency(),
                'customer_details' => [
                    'address' => [
                        'line1' => trim((string)$address['line1']),
                        'line2' => trim(
                            (string)($address['line2'] ?? '')
                        ),
                        'city' => trim((string)$address['city']),
                        'state' => $state,
                        'postal_code' => trim(
                            (string)$address['postal_code']
                        ),
                        'country' => $country,
                    ],
                    'address_source' => 'billing',
                ],
                'line_items' => $lineItems,
            ],
            $idempotencyKey
        );

        return [
            'id' => $calculation['id'] ?? null,
            'tax_cents' =>
                (int)($calculation['tax_amount_exclusive'] ?? 0)
                + (int)($calculation['tax_amount_inclusive'] ?? 0),
            'total_cents' => (int)($calculation['amount_total'] ?? 0),
            'snapshot' => json_encode(
                $calculation,
                JSON_UNESCAPED_SLASHES
            ),
            'address' => $address,
        ];
    }

    public static function normalizeBillingAddress(array $address): array
    {
        return [
            'line1' => strtoupper(trim((string)($address['line1'] ?? ''))),
            'line2' => strtoupper(trim((string)($address['line2'] ?? ''))),
            'city' => strtoupper(trim((string)($address['city'] ?? ''))),
            'state' => strtoupper(trim((string)($address['state'] ?? ''))),
            'postal_code' => strtoupper(str_replace(' ', '', trim((string)($address['postal_code'] ?? '')))),
            'country' => strtoupper(trim((string)($address['country'] ?? ''))),
        ];
    }

    public static function billingAddressMatches(array $authoritative, array $returned): bool
    {
        $expected = self::normalizeBillingAddress($authoritative);
        $actual = self::normalizeBillingAddress($returned);

        if (
            $expected['country'] === '' ||
            $actual['country'] === '' ||
            $expected['country'] !== $actual['country']
        ) {
            return false;
        }

        if (
            $expected['line1'] === '' ||
            $actual['line1'] === '' ||
            $expected['line1'] !== $actual['line1'] ||
            $expected['city'] === '' ||
            $actual['city'] === '' ||
            $expected['city'] !== $actual['city']
        ) {
            return false;
        }

        if (
            $expected['state'] !== '' &&
            $expected['state'] !== $actual['state']
        ) {
            return false;
        }

        $expectedPostal = preg_replace(
            '/[^A-Z0-9]+/',
            '',
            strtoupper($expected['postal_code'])
        );

        $actualPostal = preg_replace(
            '/[^A-Z0-9]+/',
            '',
            strtoupper($actual['postal_code'])
        );

        return
            $expectedPostal !== '' &&
            $actualPostal !== '' &&
            $expectedPostal === $actualPostal;
    }

    public static function createTaxTransaction(string $calculationId, int $orderId): array
    {
        if (!preg_match('/^taxcalc_[A-Za-z0-9]+$/', $calculationId)) {
            throw new \InvalidArgumentException('A valid Stripe Tax Calculation is required.');
        }
        return self::request('POST', '/v1/tax/transactions/create_from_calculation', [
            'calculation' => $calculationId,
            'reference' => 'asset_moth_order_' . $orderId,
            'expand' => ['line_items'],
        ], 'asset_moth_tax_transaction_order_' . $orderId);
    }

    public static function createCheckoutSession(array $order, array $items): array
    {
        if (!self::configured()) throw new \RuntimeException('Stripe is not configured. Set STRIPE_SECRET_KEY before creating live checkout sessions.');
        $currency = self::currency();
        if ($currency !== 'usd') throw new \RuntimeException('Creative Moth checkout requires USD Stripe Checkout.');
        $remainingCents = CreditService::parseCents((string)$order['total'], false);
        if ($remainingCents <= 0) throw new \RuntimeException('Stripe Checkout requires a positive remaining total.');
        $lineItems = [[
            'quantity' => 1,
            'price_data' => [
                'currency' => $currency,
                'unit_amount' => $remainingCents,
                'product_data' => ['name' => 'Creative Moth order #' . (int)$order['id']],
            ],
        ]];
        $base = self::appUrl();
        return self::request('POST', '/v1/checkout/sessions', [
            'mode' => 'payment',
            'client_reference_id' => (string)$order['id'],
            'success_url' => $base . '/checkout/success?order_id=' . (int)$order['id'] . '&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $base . '/checkout/cancel?order_id=' . (int)$order['id'],
            'metadata' => ['order_id' => (string)$order['id'], 'buyer_user_id' => (string)$order['user_id']],
            'payment_intent_data' => ['metadata' => ['order_id' => (string)$order['id'], 'buyer_user_id' => (string)$order['user_id']]],
            'automatic_tax' => ['enabled' => 'false'],
            'billing_address_collection' => 'required',
            'line_items' => $lineItems,
        ]);
    }

    public static function createPromoCheckoutSession(array $campaign): array
    {
        if(!self::configured())throw new \RuntimeException('Stripe is not configured.');
        $currency=strtolower((string)($campaign['currency']??self::currency()));
        $chargeCents=array_key_exists('stripe_due_cents',$campaign)
            && $campaign['stripe_due_cents']!==null
            ? (int)$campaign['stripe_due_cents']
            : (int)$campaign['price_cents'];

        if($currency!==self::currency()||$chargeCents<1){
            throw new \RuntimeException('Invalid promotion charge.');
        }

        $metadata=[
            'payment_kind'=>'promo',
            'promo_campaign_id'=>(string)$campaign['id'],
            'designer_id'=>(string)$campaign['designer_id']
        ];
        $base=self::appUrl();
        return self::request('POST','/v1/checkout/sessions',[
            'mode'=>'payment','client_reference_id'=>'promo_'.(int)$campaign['id'],
            'success_url'=>$base.'/seller/promos/success?campaign='.(int)$campaign['id'],
            'cancel_url'=>$base.'/seller/promos/cancel?campaign='.(int)$campaign['id'],
            'metadata'=>$metadata,'payment_intent_data'=>['metadata'=>$metadata],
            'line_items'=>[[
                'quantity'=>1,
                'price_data'=>[
                    'currency'=>$currency,
                    'unit_amount'=>$chargeCents,
                    'product_data'=>[
                        'name'=>'Creative Moth promotion #'.(int)$campaign['id']
                    ]
                ]
            ]],
            'automatic_tax'=>['enabled'=>'false'],
        ],'creative_moth_promo_campaign_'.(int)$campaign['id']);
    }


    public static function createConnectedAccount(array $designer, array $user): array
    {
        if (!self::configured()) {
            throw new \RuntimeException('Stripe is not configured.');
        }

        $country = self::normalizeCountryCode(
            (string)($designer['country_code'] ?? '')
        );

        if ($country === '') {
            throw new \InvalidArgumentException(
                'Select your seller country before starting Stripe setup.'
            );
        }

        $params = [
                'type' => 'express',
                'country' => $country,
                'email' => $user['email'] ?? null,
                'capabilities' => [
                    'card_payments' => ['requested' => 'true'],
                    'transfers' => ['requested' => 'true']
                ],
                'tos_acceptance' => [
                    'service_agreement' => 'full'
                ],
                'business_profile' => [
                    'name' =>
                        $designer['display_name']
                        ?? 'Creative Moth seller'
                ],
                'metadata' => [
                    'designer_id' => (string)$designer['id'],
                    'user_id' => (string)$user['id'],
                    'platform' => 'asset_moth'
                ],
            ];

        // Same parameters reuse one key; changed parameters get a new key.
        $key = 'creative_moth_connect_v2_' .
            (int)$designer['id'] . '_' .
            substr(hash('sha256',http_build_query($params)),0,24);

        return self::request('POST','/v1/accounts',$params,$key);
    }

    public static function createExpressLoginLink(string $accountId): array
    {
        if (!self::configured()) {
            throw new \RuntimeException('Stripe is not configured.');
        }
        return self::request(
            'POST',
            '/v1/accounts/' . rawurlencode($accountId) . '/login_links'
        );
    }

    public static function createAccountLink(string $accountId, string $refreshUrl, string $returnUrl): array
    {
        if (!self::configured()) throw new \RuntimeException('Stripe is not configured.');
        return self::request('POST', '/v1/account_links', [
            'account' => $accountId,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);
    }

    public static function retrieveConnectedAccount(string $accountId): array
    {
        if (!self::configured()) throw new \RuntimeException('Stripe is not configured.');
        return self::request('GET', '/v1/accounts/' . rawurlencode($accountId));
    }

    public static function connectedAccountStatus(array $account): string
    {
        if(empty($account['id']))return 'not_connected';
        $requirements=$account['requirements']??[];$disabled=trim((string)($requirements['disabled_reason']??''));
        if($disabled!=='')return str_contains($disabled,'rejected')?'disabled':'restricted';
        if(!empty($requirements['past_due'])||!empty($requirements['currently_due'])||!empty($requirements['errors']))return 'information_required';
        $details=!empty($account['details_submitted']);$payouts=!empty($account['payouts_enabled']);
        if($details&&$payouts)return 'payout_ready';
        if($details)return 'details_submitted';
        return 'onboarding_incomplete';
    }

    public static function syncConnectedAccountStatus(int $designerId, array $account): void
    {
        $charges = !empty($account['charges_enabled']) ? 1 : 0;
        $payouts = !empty($account['payouts_enabled']) ? 1 : 0;
        $details = !empty($account['details_submitted']) ? 1 : 0;
        $status = self::connectedAccountStatus($account);
        DB::exec('update designers set stripe_connect_account_id=coalesce(?,stripe_connect_account_id),stripe_charges_enabled=?,stripe_payouts_enabled=?,stripe_details_submitted=?,stripe_account_status=?,stripe_onboarding_completed_at=case when ?=1 then coalesce(stripe_onboarding_completed_at,now()) else stripe_onboarding_completed_at end,updated_at=now() where id=?', [$account['id'] ?? null,$charges,$payouts,$details,$status,($details && $payouts)?1:0,$designerId]);
    }

    public static function createTransfer(string $accountId, int $amountCents, string $currency, array $metadata, ?string $idempotencyKey = null, ?string $sourceTransaction = null, ?string $transferGroup = null): array
    {
        if (!self::configured()) throw new \RuntimeException('Stripe is not configured.');
        $params = ['amount' => $amountCents, 'currency' => $currency, 'destination' => $accountId, 'metadata' => $metadata];
        if (trim((string)$sourceTransaction) !== '') $params['source_transaction'] = trim((string)$sourceTransaction);
        if (trim((string)$transferGroup) !== '') $params['transfer_group'] = trim((string)$transferGroup);
        return self::request('POST', '/v1/transfers', $params, $idempotencyKey);
    }

    public static function request(string $method, string $path, array $params = [], ?string $idempotencyKey = null): array
    {
        if (self::$testTransport !== null) {
            return (self::$testTransport)($method,$path,$params,$idempotencyKey);
        }
        $ch = curl_init('https://api.stripe.com' . $path);
        $headers = [];
        if ($idempotencyKey) $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => self::secretKey() . ':', CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 30]);
        if ($headers) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($params) curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($body === false) throw new \RuntimeException('Stripe API request failed: ' . $error);
        $json = json_decode($body, true) ?: [];
        if ($code < 200 || $code >= 300) throw new \RuntimeException($json['error']['message'] ?? ('Stripe API returned HTTP ' . $code));
        return $json;
    }

    public static function verifyWebhook(string $payload, string $sigHeader): array
    {
        $secrets = array_values(array_unique(array_filter([self::webhookSecret(), self::connectWebhookSecret()])));
        if (!$secrets) throw new \RuntimeException('Stripe webhook secret is not configured.');
        $parts = [];
        foreach (explode(',', $sigHeader) as $pair) { [$k,$v] = array_pad(explode('=', trim($pair), 2), 2, ''); $parts[$k][] = $v; }
        $timestamp = $parts['t'][0] ?? '';
        $signatures = $parts['v1'] ?? [];
        if ($timestamp === '' || !$signatures) throw new \RuntimeException('Invalid Stripe signature header.');
        if (abs(time() - (int)$timestamp) > 300) throw new \RuntimeException('Stripe webhook timestamp outside tolerance.');
        foreach ($secrets as $secret) {
            $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
            foreach ($signatures as $sig) if (hash_equals($expected, $sig)) return json_decode($payload, true) ?: [];
        }
        throw new \RuntimeException('Invalid Stripe webhook signature.');
    }


    public static function attemptPendingTransfersForDesigner(int $designerId): array
    {
        $summary = ['attempted' => 0, 'transferred' => 0, 'recovery_applied' => 0, 'failed' => 0, 'skipped' => 0];
        $designer = DB::row('select * from designers where id=?', [$designerId]);
        $ready = $designer && !empty($designer['stripe_connect_account_id']) && !empty($designer['stripe_details_submitted']) && !empty($designer['stripe_payouts_enabled']);
        if (!$ready) return $summary;

        DB::exec('update seller_payouts sp join orders o on o.id=sp.order_id set sp.payout_status="pending_transfer",sp.updated_at=now() where sp.designer_id=? and sp.payout_status="pending_stripe_onboarding" and o.payment_status in ("paid","partially_refunded") and o.status not in ("failed","cancelled","refunded") and coalesce(o.manual_review_required,0)=0', [$designerId]);
        DB::exec('update order_items oi join orders o on o.id=oi.order_id set oi.seller_payout_status="pending_transfer" where oi.designer_id=? and oi.seller_payout_status="pending_stripe_onboarding" and o.payment_status in ("paid","partially_refunded") and o.status not in ("failed","cancelled","refunded") and coalesce(o.manual_review_required,0)=0', [$designerId]);

        $rows = DB::rows('select sp.*,o.payment_status,o.status order_status,o.manual_review_required,o.stripe_charge_id from seller_payouts sp join orders o on o.id=sp.order_id where sp.designer_id=? and sp.payout_status in ("pending_transfer","transfer_failed","recovery_applied") order by sp.id', [$designerId]);
        foreach ($rows as $row) {
            $refundService=new MarketplaceRefundService();if(!empty($row['stripe_transfer_id'])){if($refundService->repairStoredTransfer((int)$row['id']))$summary['transferred']++;else $summary['skipped']++;continue;}
            if($row['payout_status']==='recovery_applied'&&$row['recovery_claim_status']==='applied'){$summary['recovery_applied']++;continue;}
            if (!in_array(($row['payment_status'] ?? ''),['paid','partially_refunded'],true) || in_array(($row['order_status'] ?? ''), ['failed','cancelled','refunded'], true) || !empty($row['manual_review_required'])) {
                $summary['skipped']++;
                continue;
            }
            if ((float)$row['seller_payout_amount'] <= 0) {
                $summary['skipped']++;
                continue;
            }
            $chargeId = trim((string)($row['stripe_charge_id'] ?? ''));
            if ($chargeId === '') {
                DB::exec('update seller_payouts set payout_status="pending_transfer",updated_at=now() where id=? and payout_status<>"transferred"', [$row['id']]);
                DB::exec('update order_items set seller_payout_status="pending_transfer" where order_id=? and designer_id=? and seller_payout_status<>"transferred"', [(int)$row['order_id'],$designerId]);
                $summary['skipped']++;
                continue;
            }
            $summary['attempted']++;
            $orderId = (int)$row['order_id'];
            $idempotencyKey = 'asset_moth_payout_order_' . $orderId . '_designer_' . $designerId;
            $recovery=$refundService->claimPayout((int)$row['id']);
            if($recovery['transfer_cents']===0){$refundService->finalizePayoutClaim((int)$row['id']);DB::exec('update seller_payouts set payout_status="recovery_applied" where id=?',[$row['id']]);DB::exec('update order_items set seller_payout_status="recovery_applied" where order_id=? and designer_id=?',[$orderId,$designerId]);$summary['recovery_applied']++;continue;}
            $execution=$refundService->beginPayoutExecution((int)$row['id'],$idempotencyKey);if(!$execution['execute']){$summary['skipped']++;continue;}
            try {
                $transfer = self::createTransfer($designer['stripe_connect_account_id'], $recovery['transfer_cents'], strtolower((string)($row['currency'] ?: self::currency())), ['order_id'=>(string)$orderId,'designer_id'=>(string)$designerId,'seller_payout_id'=>(string)$row['id']], $idempotencyKey, $chargeId, 'order_' . $orderId);
                $transferId = $transfer['id'] ?? null;
                if(empty($transferId))throw new \RuntimeException('Stripe transfer did not return an identifier.');DB::exec('update seller_payouts set stripe_transfer_id=? where id=?',[$transferId,$row['id']]);$refundService->repairStoredTransfer((int)$row['id']);
                $summary['transferred']++;
            } catch (Throwable $e) {
                $storedTransfer=DB::row('select stripe_transfer_id from seller_payouts where id=?',[$row['id']]);if(!empty($storedTransfer['stripe_transfer_id'])){$summary['skipped']++;continue;}
                $refundService->failPayoutExecution((int)$row['id']);
                $error = mb_substr($e->getMessage(),0,1000);
                DB::exec('update seller_payouts set payout_status="transfer_failed",stripe_transfer_error=?,updated_at=now() where id=? and payout_status<>"transferred"', [$error,$row['id']]);
                DB::exec('update order_items set seller_payout_status="transfer_failed",stripe_transfer_error=? where order_id=? and designer_id=? and seller_payout_status<>"transferred"', [$error,$orderId,$designerId]);
                $summary['failed']++;
            }
        }
        return $summary;
    }

    public static function logTransaction(int $orderId, ?string $eventId, string $type, string $status, $amount, string $currency, array $refs = [], string $message = '', bool $review = false): void
    {
        DB::exec('insert into payment_transactions (order_id,stripe_event_id,transaction_type,payment_status,amount,currency,stripe_checkout_session_id,stripe_payment_intent_id,stripe_charge_id,message,manual_review_required) values (?,?,?,?,?,?,?,?,?,?,?)', [$orderId,$eventId,$type,$status,$amount,$currency,$refs['session'] ?? null,$refs['intent'] ?? null,$refs['charge'] ?? null,$message,$review ? 1 : 0]);
    }
}
