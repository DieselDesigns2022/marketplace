<?php

namespace App\Services;

use App\Core\Database as DB;

final class CustomCartReadinessService
{
    public static function missingAnswers(
        int $userId,
        int $serviceId,
        string $token
    ): ?int {
        if (
            $userId < 1 ||
            $serviceId < 1 ||
            !preg_match('/^[a-f0-9]{48}$/D', $token)
        ) {
            return null;
        }

        $row = DB::row(
            'SELECT cd.payload,s.brief_fields
             FROM custom_checkout_drafts cd
             JOIN custom_design_services s
               ON s.id=cd.custom_service_id
             WHERE cd.token=?
               AND cd.buyer_user_id=?
               AND cd.custom_service_id=?
             LIMIT 1',
            [$token,$userId,$serviceId]
        );

        if (!$row) return null;

        $payload = json_decode((string)$row['payload'],true);

        if (!is_array($payload)) return null;

        $input = $payload['input'] ?? [];

        if (!is_array($input)) return null;

        $missing = 0;

        $fields = json_decode(
            (string)($row['brief_fields'] ?: '[]'),
            true
        );

        foreach (is_array($fields) ? $fields : [] as $field) {
            $key = (string)($field['key'] ?? '');

            if (
                $key !== '' &&
                !empty($field['required']) &&
                trim((string)($input[$key] ?? '')) === ''
            ) {
                $missing++;
            }
        }

        $questions = DB::rows(
            'SELECT id,is_required
             FROM custom_service_questions
             WHERE custom_service_id=?',
            [$serviceId]
        );

        $answers = $input['answers'] ?? [];

        if (!is_array($answers)) $answers = [];

        foreach ($questions as $question) {
            if (
                !empty($question['is_required']) &&
                trim((string)(
                    $answers[$question['id']] ?? ''
                )) === ''
            ) {
                $missing++;
            }
        }

        return $missing;
    }
}
