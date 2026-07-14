<?php
namespace App\Services;

class IpRiskScanner
{
    public const SOURCE_FIELDS = ['title', 'description', 'tags', 'seo_title', 'seo_description', 'file_name'];

    public static function normalize(string $value, bool $fileName = false): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace(["\u{2018}", "\u{2019}", "\u{201A}", "\u{201B}"], "'", $value);
        if ($fileName) {
            $value = preg_replace('/[_\-]+/u', ' ', $value) ?? $value;
        }
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[^\p{L}\p{N}\']+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return trim($value);
    }

    public static function validateTermText(string $term): ?string
    {
        $normalized = self::normalize($term);
        $plain = preg_replace('/\s+/u', '', $normalized) ?? $normalized;
        if (mb_strlen($plain, 'UTF-8') < 2) {
            return 'Terms and aliases must contain at least two letters or numbers after normalization.';
        }
        return null;
    }

    public function scan(array $input, array $terms): array
    {
        $fields = [
            'title' => [$input['title'] ?? ''],
            'description' => [$input['description'] ?? ''],
            'tags' => $input['tags'] ?? [],
            'seo_title' => [$input['seo_title'] ?? ''],
            'seo_description' => [$input['seo_description'] ?? ''],
            'file_name' => $input['file_names'] ?? [],
        ];
        $matches = [];
        $seen = [];

        foreach ($fields as $field => $values) {
            foreach ((array)$values as $value) {
                $haystack = self::normalize((string)$value, $field === 'file_name');
                if ($haystack === '') {
                    continue;
                }
                $this->scanValue($haystack, $field, $terms, $matches, $seen);
            }
        }

        usort($matches, fn($a, $b) => [$a['risk_term_id'], $a['matched_value_key'], $a['source_field']] <=> [$b['risk_term_id'], $b['matched_value_key'], $b['source_field']]);
        return $matches;
    }

    private function scanValue(string $haystack, string $field, array $terms, array &$matches, array &$seen): void
    {
        foreach ($terms as $term) {
            if (empty($term['is_enabled'])) {
                continue;
            }
            foreach ($this->candidates($term) as $candidate) {
                $needle = trim((string)$candidate['normalized']);
                if ($needle === '' || !$this->containsTokenPhrase($haystack, $needle)) {
                    continue;
                }
                $matchKey = $candidate['alias'] === null ? 'canonical' : $needle;
                $seenKey = ($term['id'] ?? $term['risk_term_id']) . '|' . $matchKey . '|' . $field;
                if (isset($seen[$seenKey])) {
                    continue;
                }
                $seen[$seenKey] = true;
                $matches[] = [
                    'risk_term_id' => (int)($term['id'] ?? $term['risk_term_id']),
                    'matched_term' => (string)$term['term'],
                    'matched_alias' => $candidate['alias'],
                    'matched_value_key' => $matchKey,
                    'category' => (string)$term['category'],
                    'source_field' => $field,
                ];
            }
        }
    }

    private function candidates(array $term): array
    {
        $candidates = [[
            'alias' => null,
            'normalized' => $term['normalized_term'] ?? self::normalize((string)$term['term']),
        ]];
        foreach (($term['aliases'] ?? []) as $alias) {
            if (!empty($alias['is_enabled'])) {
                $candidates[] = [
                    'alias' => $alias['alias'],
                    'normalized' => $alias['normalized_alias'] ?? self::normalize((string)$alias['alias']),
                ];
            }
        }
        return $candidates;
    }

    private function containsTokenPhrase(string $haystack, string $needle): bool
    {
        $quoted = preg_quote($needle, '/');
        $pattern = '/(?<![\p{L}\p{N}])' . $quoted . '(?![\p{L}\p{N}])/u';
        return preg_match($pattern, $haystack) === 1;
    }
}
