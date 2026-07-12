<?php

class TopRepositoryService
{
    private const ALLOWED_SORTS = ['stars', 'open_issues', 'contributors'];

    public static function fromRequest(Request $request)
    {
        return [
            'sort_by' => self::stringOrNull($request->getQuery('sort_by')) ?: 'stars',
            'technology' => self::stringOrNull($request->getQuery('technology')),
            'limit' => $request->getQuery('limit', 30),
            'cursor' => $request->getQuery('cursor'),
        ];
    }

    public static function validate(array $filters)
    {
        $errors = [];

        if (!in_array($filters['sort_by'] ?? 'stars', self::ALLOWED_SORTS, true)) {
            $errors[] = ['field' => 'sort_by', 'message' => 'Invalid value.'];
        }

        $limit = filter_var($filters['limit'] ?? null, FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 100) {
            $errors[] = ['field' => 'limit', 'message' => 'Limit must be between 1 and 100.'];
        }

        if (self::stringOrNull($filters['cursor'] ?? null) !== null && self::decodeCursor($filters['cursor'], $filters) === null) {
            $errors[] = ['field' => 'cursor', 'message' => 'Invalid cursor.'];
        }

        return $errors;
    }

    public function list(array $cacheRows, array $filters, array $technology = null)
    {
        $sortBy = $filters['sort_by'] ?? 'stars';
        $limit = min(max((int) ($filters['limit'] ?? 30), 1), 100);
        $entries = [];

        foreach ($cacheRows as $row) {
            $summary = RepositorySummary::fromCacheRow($row);
            $githubRepositoryId = (int) ($summary['github_repository_id'] ?? 0);

            if ($githubRepositoryId <= 0 || empty($summary['owner']) || empty($summary['name'])) {
                continue;
            }

            if ($technology && !$this->matchesTechnology($summary, $technology)) {
                continue;
            }

            $entries[] = $this->entryFromSummary($summary, $sortBy);
        }

        usort($entries, function ($left, $right) use ($sortBy) {
            return $this->compareEntries($left, $right, $sortBy);
        });

        $startIndex = $this->resolveStartIndex($entries, $filters);
        $pageEntries = array_slice($entries, $startIndex, $limit);
        $items = [];

        foreach ($pageEntries as $offset => $entry) {
            $items[] = [
                'repository' => $entry['repository'],
                'rank' => $startIndex + $offset + 1,
                'rank_metric' => [
                    'type' => $sortBy,
                    'value' => $entry['metric_value'],
                ],
                'user_state' => null,
            ];
        }

        $nextCursor = null;
        $consumed = $startIndex + count($pageEntries);
        if ($pageEntries && $consumed < count($entries)) {
            $nextCursor = self::encodeCursor([
                'sort_by' => $sortBy,
                'technology' => self::stringOrNull($filters['technology'] ?? null),
                'metric_value' => (int) end($pageEntries)['metric_value'],
                'stars' => (int) (end($pageEntries)['repository']['stars'] ?? 0),
                'updated_at' => self::sortableDate(end($pageEntries)['repository']['updated_at'] ?? null),
                'github_repository_id' => (int) (end($pageEntries)['repository']['github_repository_id'] ?? 0),
            ]);
        }

        return [
            'items' => $items,
            'pagination' => [
                'next_cursor' => $nextCursor,
            ],
            'metadata' => [
                'sort_by' => $sortBy,
                'technology' => self::stringOrNull($filters['technology'] ?? null),
                'generated_at' => gmdate('c'),
                'cached' => true,
            ],
        ];
    }

    public static function encodeCursor(array $payload)
    {
        return rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    }

    public static function decodeCursor($cursor, array $filters)
    {
        $cursor = self::stringOrNull($cursor);
        if ($cursor === null) {
            return [];
        }

        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }

        $payload = json_decode($decoded, true);
        if (!is_array($payload)) {
            return null;
        }

        foreach (['sort_by', 'metric_value', 'stars', 'updated_at', 'github_repository_id'] as $field) {
            if (!array_key_exists($field, $payload)) {
                return null;
            }
        }

        if (($payload['sort_by'] ?? null) !== ($filters['sort_by'] ?? 'stars')) {
            return null;
        }

        if (self::stringOrNull($payload['technology'] ?? null) !== self::stringOrNull($filters['technology'] ?? null)) {
            return null;
        }

        foreach (['metric_value', 'stars', 'github_repository_id'] as $field) {
            if (filter_var($payload[$field], FILTER_VALIDATE_INT) === false) {
                return null;
            }
        }

        $payload['metric_value'] = (int) $payload['metric_value'];
        $payload['stars'] = (int) $payload['stars'];
        $payload['github_repository_id'] = (int) $payload['github_repository_id'];
        $payload['updated_at'] = self::sortableDate($payload['updated_at']);

        return $payload;
    }

    private function entryFromSummary(array $summary, $sortBy)
    {
        return [
            'repository' => $summary,
            'metric_value' => $this->metricValue($summary, $sortBy),
        ];
    }

    private function resolveStartIndex(array $entries, array $filters)
    {
        $cursor = self::decodeCursor($filters['cursor'] ?? null, $filters);
        if ($cursor === null) {
            throw new InvalidArgumentException('Invalid cursor.');
        }

        if ($cursor === []) {
            return 0;
        }

        foreach ($entries as $index => $entry) {
            $comparison = $this->compareEntries($entry, $cursor, $filters['sort_by'] ?? 'stars');
            if ($comparison === 0) {
                return $index + 1;
            }

            if ($comparison > 0) {
                return $index;
            }
        }

        return count($entries);
    }

    private function compareEntries(array $left, array $right, $sortBy)
    {
        $leftRepository = $this->repositoryFields($left);
        $rightRepository = $this->repositoryFields($right);

        if ($sortBy === 'stars') {
            return $this->compareMetric($left['metric_value'] ?? 0, $right['metric_value'] ?? 0)
                ?: $this->compareUpdatedAt($leftRepository, $rightRepository)
                ?: $this->compareRepositoryId($leftRepository, $rightRepository);
        }

        return $this->compareMetric($left['metric_value'] ?? 0, $right['metric_value'] ?? 0)
            ?: $this->compareStars($leftRepository, $rightRepository)
            ?: $this->compareUpdatedAt($leftRepository, $rightRepository)
            ?: $this->compareRepositoryId($leftRepository, $rightRepository);
    }

    private function compareMetric($left, $right)
    {
        return ((int) $right) <=> ((int) $left);
    }

    private function compareStars(array $leftRepository, array $rightRepository)
    {
        return ((int) ($rightRepository['stars'] ?? $rightRepository['metric_value'] ?? 0))
            <=> ((int) ($leftRepository['stars'] ?? $leftRepository['metric_value'] ?? 0));
    }

    private function compareUpdatedAt(array $leftRepository, array $rightRepository)
    {
        return self::timestamp($rightRepository['updated_at'] ?? null) <=> self::timestamp($leftRepository['updated_at'] ?? null);
    }

    private function compareRepositoryId(array $leftRepository, array $rightRepository)
    {
        return ((int) ($leftRepository['github_repository_id'] ?? 0)) <=> ((int) ($rightRepository['github_repository_id'] ?? 0));
    }

    private function repositoryFields(array $entry)
    {
        if (isset($entry['repository']) && is_array($entry['repository'])) {
            return $entry['repository'];
        }

        return $entry;
    }

    private function metricValue(array $summary, $sortBy)
    {
        switch ($sortBy) {
            case 'open_issues':
                return (int) ($summary['open_issues'] ?? 0);
            case 'contributors':
                return (int) ($summary['contributors'] ?? 0);
            case 'stars':
            default:
                return (int) ($summary['stars'] ?? 0);
        }
    }

    private function matchesTechnology(array $summary, array $technology)
    {
        $githubLanguage = self::stringOrNull($technology['github_language'] ?? null);
        $githubTopics = self::normalizeStringList($technology['github_topics'] ?? []);
        $slug = self::stringOrNull($technology['slug'] ?? null);

        if ($githubLanguage !== null && self::containsNormalized($summary['languages'] ?? [], $githubLanguage)) {
            return true;
        }

        foreach ($githubTopics as $topic) {
            if (self::containsNormalized($summary['topics'] ?? [], $topic)) {
                return true;
            }
        }

        return $slug !== null && self::containsNormalized($summary['topics'] ?? [], $slug);
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

    private static function normalizeStringList($items)
    {
        if (!is_array($items)) {
            $value = self::stringOrNull($items);
            return $value === null ? [] : [$value];
        }

        $normalized = [];
        foreach ($items as $item) {
            $value = self::stringOrNull($item);
            if ($value !== null && !in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }

    private static function equalsNormalized($left, $right)
    {
        return strtolower((string) $left) === strtolower((string) $right);
    }

    private static function sortableDate($value)
    {
        return self::stringOrNull($value) ?: '';
    }

    private static function timestamp($value)
    {
        $value = self::sortableDate($value);
        if ($value === '') {
            return 0;
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? 0 : $timestamp;
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
