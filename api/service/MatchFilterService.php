<?php

class MatchFilterService
{
    private static $allowedStates = ['saved', 'ignored', 'researching', 'working', 'pull_request_sent', 'contributed', 'archived'];
    private static $allowedSorts = ['score', 'best_match', 'recently_updated', 'most_active', 'most_stars', 'beginner_friendly'];
    private static $allowedDifficulties = ['easy', 'medium', 'hard', 'unknown', 'beginner', 'intermediate', 'advanced'];
    private static $allowedProjectSizes = ['small', 'medium', 'large'];
    private static $allowedActivities = ['low', 'moderate', 'active', 'very_active'];

    public static function fromRequest(Request $request)
    {
        return [
            'q' => $request->getQuery('q'),
            'state' => $request->getQuery('state'),
            'minimum_score' => $request->getQuery('minimum_score'),
            'technology' => $request->getQuery('technology'),
            'language' => $request->getQuery('language'),
            'difficulty' => $request->getQuery('difficulty'),
            'project_size' => $request->getQuery('project_size'),
            'activity' => $request->getQuery('activity'),
            'has_good_first_issue' => $request->getQuery('has_good_first_issue'),
            'has_help_wanted' => $request->getQuery('has_help_wanted'),
            'minimum_health_score' => $request->getQuery('minimum_health_score'),
            'sort_by' => $request->getQuery('sort_by', 'score'),
            'limit' => (int) $request->getQuery('limit', 30),
            'cursor' => $request->getQuery('cursor'),
        ];
    }

    public static function validate(array $filters)
    {
        $errors = [];

        self::validateEnum($errors, $filters, 'state', self::$allowedStates);
        self::validateEnum($errors, $filters, 'sort_by', self::$allowedSorts);
        self::validateEnum($errors, $filters, 'difficulty', self::$allowedDifficulties);
        self::validateEnum($errors, $filters, 'project_size', self::$allowedProjectSizes);
        self::validateEnum($errors, $filters, 'activity', self::$allowedActivities);
        self::validateNumber($errors, $filters, 'minimum_score', 0, 100);
        self::validateNumber($errors, $filters, 'minimum_health_score', 0, 100);
        self::validateBoolean($errors, $filters, 'has_good_first_issue');
        self::validateBoolean($errors, $filters, 'has_help_wanted');

        if (!empty($filters['limit'])) {
            $limit = filter_var($filters['limit'], FILTER_VALIDATE_INT);
            if ($limit === false || $limit < 1 || $limit > 100) {
                $errors[] = ['field' => 'limit', 'message' => 'Limit must be between 1 and 100.'];
            }
        }

        if (!empty($filters['cursor']) && self::decodeCursor($filters['cursor']) === null) {
            $errors[] = ['field' => 'cursor', 'message' => 'Invalid cursor.'];
        }

        return $errors;
    }

    public static function apply(array $items, array $filters)
    {
        $page = self::paginate($items, $filters);
        return $page['items'];
    }

    public static function paginate(array $items, array $filters)
    {
        $filtered = array_values(array_filter($items, function ($item) use ($filters) {
            return self::matchesItem($item, $filters);
        }));

        self::sort($filtered, $filters['sort_by'] ?? 'score');

        $limit = isset($filters['limit']) ? min(max((int) $filters['limit'], 1), 100) : 30;
        $offset = self::decodeCursor($filters['cursor'] ?? null) ?? 0;
        $pageItems = array_slice($filtered, $offset, $limit);
        $nextOffset = $offset + count($pageItems);

        return [
            'items' => $pageItems,
            'next_cursor' => $nextOffset < count($filtered) ? self::encodeCursor($nextOffset) : null,
            'total_filtered' => count($filtered),
        ];
    }

    public static function encodeCursor($offset)
    {
        $payload = json_encode(['offset' => max(0, (int) $offset)]);
        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    public static function decodeCursor($cursor)
    {
        $cursor = self::stringOrNull($cursor);
        if ($cursor === null) {
            return 0;
        }

        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }

        $payload = json_decode($decoded, true);
        if (!is_array($payload) || !isset($payload['offset']) || filter_var($payload['offset'], FILTER_VALIDATE_INT) === false) {
            return null;
        }

        return max(0, (int) $payload['offset']);
    }

    private static function matchesItem(array $item, array $filters)
    {
        $repository = $item['repository'] ?? [];

        if (!self::matchesText($repository, $filters['q'] ?? null)) {
            return false;
        }
        if (!self::matchesTechnology($item, $repository, $filters['technology'] ?? null)) {
            return false;
        }
        if (!self::matchesLanguage($repository, $filters['language'] ?? null)) {
            return false;
        }
        if (!self::matchesDifficulty($item, $filters['difficulty'] ?? null)) {
            return false;
        }
        if (!self::matchesEquals($repository['project_size'] ?? null, $filters['project_size'] ?? null)) {
            return false;
        }
        if (!self::matchesEquals($repository['activity_label'] ?? null, $filters['activity'] ?? null)) {
            return false;
        }
        if (!self::matchesBoolean(((int) ($repository['good_first_issues'] ?? 0)) > 0, $filters['has_good_first_issue'] ?? null)) {
            return false;
        }
        if (!self::matchesBoolean(((int) ($repository['help_wanted_issues'] ?? 0)) > 0, $filters['has_help_wanted'] ?? null)) {
            return false;
        }
        if (!self::matchesMinimum($repository['health_score'] ?? null, $filters['minimum_health_score'] ?? null)) {
            return false;
        }

        return true;
    }

    private static function matchesText(array $repository, $query)
    {
        $query = self::stringOrNull($query);
        if ($query === null) {
            return true;
        }

        $haystack = strtolower(implode(' ', array_filter([
            $repository['owner'] ?? null,
            $repository['name'] ?? null,
            $repository['full_name'] ?? null,
            $repository['description'] ?? null,
            $repository['primary_language'] ?? null,
            implode(' ', $repository['languages'] ?? []),
            implode(' ', $repository['topics'] ?? []),
        ])));

        return strpos($haystack, strtolower($query)) !== false;
    }

    private static function matchesTechnology(array $item, array $repository, $technology)
    {
        $technology = self::stringOrNull($technology);
        if ($technology === null) {
            return true;
        }

        return self::containsNormalized($item['shared_technologies'] ?? [], $technology)
            || self::containsNormalized($repository['languages'] ?? [], $technology)
            || self::containsNormalized($repository['topics'] ?? [], $technology);
    }

    private static function matchesLanguage(array $repository, $language)
    {
        $language = self::stringOrNull($language);
        if ($language === null) {
            return true;
        }

        return self::equalsNormalized($repository['primary_language'] ?? null, $language)
            || self::containsNormalized($repository['languages'] ?? [], $language);
    }

    private static function matchesDifficulty(array $item, $difficulty)
    {
        $difficulty = self::stringOrNull($difficulty);
        if ($difficulty === null) {
            return true;
        }

        $map = [
            'beginner' => 'junior',
            'easy' => 'junior',
            'intermediate' => 'mid',
            'medium' => 'mid',
            'advanced' => 'senior',
            'hard' => 'senior',
            'unknown' => 'unknown',
        ];

        $expected = $map[strtolower($difficulty)] ?? strtolower($difficulty);
        if ($expected === 'unknown') {
            return true;
        }

        return ($item['recommended_seniority'] ?? null) === $expected
            || (($item['match']['recommended_seniority'] ?? null) === $expected);
    }

    private static function matchesEquals($actual, $expected)
    {
        $expected = self::stringOrNull($expected);
        if ($expected === null) {
            return true;
        }

        return self::equalsNormalized($actual, $expected);
    }

    private static function matchesBoolean($actual, $expected)
    {
        if ($expected === null || $expected === '') {
            return true;
        }

        return $actual === filter_var($expected, FILTER_VALIDATE_BOOLEAN);
    }

    private static function matchesMinimum($actual, $minimum)
    {
        if ($minimum === null || $minimum === '') {
            return true;
        }

        if ($actual === null || $actual === '') {
            return false;
        }

        return (float) $actual >= (float) $minimum;
    }

    private static function sort(array &$items, $sortBy)
    {
        usort($items, function ($a, $b) use ($sortBy) {
            $repoA = $a['repository'] ?? [];
            $repoB = $b['repository'] ?? [];

            switch ($sortBy) {
                case 'recently_updated':
                    return strcmp((string) ($repoB['last_updated_at'] ?? ''), (string) ($repoA['last_updated_at'] ?? ''))
                        ?: self::compareNumber($b['score'] ?? 0, $a['score'] ?? 0);
                case 'most_active':
                    return self::compareNumber($repoB['activity_score'] ?? 0, $repoA['activity_score'] ?? 0)
                        ?: self::compareNumber($b['score'] ?? 0, $a['score'] ?? 0);
                case 'most_stars':
                    return self::compareNumber($repoB['stars'] ?? 0, $repoA['stars'] ?? 0)
                        ?: self::compareNumber($b['score'] ?? 0, $a['score'] ?? 0);
                case 'beginner_friendly':
                    return self::compareNumber($repoB['good_first_issues'] ?? 0, $repoA['good_first_issues'] ?? 0)
                        ?: self::compareNumber($b['score'] ?? 0, $a['score'] ?? 0);
                case 'best_match':
                case 'score':
                default:
                    return self::compareNumber($b['score'] ?? 0, $a['score'] ?? 0);
            }
        });
    }

    private static function compareNumber($left, $right)
    {
        return ((float) $left) <=> ((float) $right);
    }

    private static function validateEnum(array &$errors, array $filters, $field, array $allowed)
    {
        if (isset($filters[$field]) && $filters[$field] !== '' && !in_array($filters[$field], $allowed, true)) {
            $errors[] = ['field' => $field, 'message' => 'Invalid value.'];
        }
    }

    private static function validateNumber(array &$errors, array $filters, $field, $min, $max)
    {
        if (!isset($filters[$field]) || $filters[$field] === '') {
            return;
        }

        if (!is_numeric($filters[$field]) || (float) $filters[$field] < $min || (float) $filters[$field] > $max) {
            $errors[] = ['field' => $field, 'message' => 'Value must be between ' . $min . ' and ' . $max . '.'];
        }
    }

    private static function validateBoolean(array &$errors, array $filters, $field)
    {
        if (!isset($filters[$field]) || $filters[$field] === '') {
            return;
        }

        if (!in_array($filters[$field], ['1', '0', 1, 0, true, false, 'true', 'false'], true)) {
            $errors[] = ['field' => $field, 'message' => 'Valor deve ser booleano.'];
        }
    }

    private static function containsNormalized(array $items, $needle)
    {
        foreach ($items as $item) {
            if (self::equalsNormalized($item, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function equalsNormalized($left, $right)
    {
        return strtolower((string) $left) === strtolower((string) $right);
    }

    private static function stringOrNull($value)
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
