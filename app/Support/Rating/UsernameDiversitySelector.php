<?php

namespace App\Support\Rating;

/** Pure selection; durable reservation and final revalidation belong to the allocator. */
final class UsernameDiversitySelector
{
    private array $options;

    private \Closure $randomInt;

    private array $catalog;

    private array $groups = [];

    private array $dateCache = [];

    private array $gramCache = [];

    public function __construct(array $options = [], ?callable $randomInt = null)
    {
        $this->options = array_replace([
            'core_window' => 1000,
            'family_window' => 200,
            'family_days' => 30,
            'similarity_threshold' => 0.88,
            'similarity_min_length' => 8,
            'candidate_batch' => 24,
            'candidate_rounds' => 4,
            'search_budget' => 768,
            'fallback_search_budget' => 512,
            'pattern_weights' => ['curated_alias' => 20, 'modifier_subject' => 35, 'compound' => 30, 'activity_subject' => 15],
        ], $options);
        $this->randomInt = $randomInt ? \Closure::fromCallable($randomInt) : fn (int $min, int $max): int => random_int($min, $max);
        $this->catalog = UsernameCatalog::all();
        foreach ($this->catalog as $key => $entry) {
            $this->groups[$entry['pattern_key']][] = $key;
        }
    }

    public function select(array $history, callable $valid, callable $decorate, callable $exists, ?string $generationDay = null): array
    {
        $state = $this->historyState($history, $generationDay);
        $weights = [];
        foreach ($this->groups as $pattern => $keys) {
            $used = $state['used_patterns'][$pattern] ?? 0;
            $remaining = max(0, count($keys) - $used);
            $baseWeight = max(1, (int) ($this->options['pattern_weights'][$pattern] ?? 10));
            $weights[$pattern] = max(1, (int) round($baseWeight * sqrt(max(1, $remaining) / count($keys))));
        }

        $tried = [];
        $budget = max(1, (int) $this->options['search_budget']);
        $batchSize = max(1, (int) $this->options['candidate_batch']);
        for ($round = 0; $round < max(1, (int) $this->options['candidate_rounds']); $round++) {
            $candidates = [];
            while (count($candidates) < $batchSize && $budget > 0) {
                $budget--;
                $pattern = $this->weighted($weights);
                $keys = $this->groups[$pattern];
                $key = $keys[($this->randomInt)(0, count($keys) - 1)];
                if (isset($tried[$key])) {
                    continue;
                }
                $tried[$key] = true;
                $entry = $this->catalog[$key];
                if (! $valid($entry['base']) || ! $this->allowed($entry, $state)) {
                    continue;
                }
                $entry['_usage'] = $state['core_counts'][$key] ?? 0;
                $entry['_score'] = $this->score($entry, $state);
                $candidates[] = $entry;
            }

            while ($candidates !== []) {
                usort($candidates, fn (array $a, array $b): int => ($a['_usage'] <=> $b['_usage']) ?: ($a['_score'] <=> $b['_score']) ?: strcmp($a['core_key'], $b['core_key']));
                // Never let a soft template quota outrank a still unused core.
                $sameUsage = count(array_filter(array_slice($candidates, 0, 5), fn (array $candidate): bool => $candidate['_usage'] === $candidates[0]['_usage']));
                $index = ($this->randomInt)(0, max(0, $sameUsage - 1));
                $entry = $candidates[$index];
                array_splice($candidates, $index, 1);
                $username = $decorate($entry);
                if (! $valid($username) || $exists($username)) {
                    continue;
                }

                return $this->result($entry, $username);
            }
        }

        // Directed bounded search is different from adding digits to a rejected core.
        $keys = array_keys($this->catalog);
        $offset = ($this->randomInt)(0, max(0, count($keys) - 1));
        $remaining = max(1, (int) $this->options['fallback_search_budget']);
        for ($pass = 0; $pass < 2 && $remaining > 0; $pass++) {
            for ($i = 0; $i < count($keys) && $remaining > 0; $i++) {
                $key = $keys[($offset + $i) % count($keys)];
                if (isset($tried[$key]) || ($pass === 0 && isset($state['core_counts'][$key]))) {
                    continue;
                }
                $remaining--;
                $tried[$key] = true;
                $entry = $this->catalog[$key];
                if (! $valid($entry['base']) || ! $this->allowed($entry, $state)) {
                    continue;
                }
                $username = $decorate($entry);
                if ($valid($username) && ! $exists($username)) {
                    return $this->result($entry, $username);
                }
            }
        }

        throw new \RuntimeException('Kein geeigneter Benutzername innerhalb des Suchbudgets gefunden. Bitte erneut versuchen oder den Namensbestand prüfen.');
    }

    public function isAllowed(array $candidate, array $history): bool
    {
        return $this->allowed($candidate, $this->historyState($history));
    }

    private function result(array $entry, string $username): array
    {
        unset($entry['_score'], $entry['_usage']);

        return array_merge($entry, [
            'username' => $username,
            'username_key' => UsernameFingerprint::key($username),
            'generator_version' => 6,
            'catalog_version' => UsernameCatalog::version(),
            'confidence' => 'certain',
        ]);
    }

    private function historyState(array $history, ?string $generationDay = null): array
    {
        $state = ['blocked' => [], 'core_counts' => [], 'used_patterns' => [], 'families' => [], 'monthly_families' => [], 'day_families' => [], 'themes' => [], 'near' => [], 'grams' => []];
        $recentOffset = max(0, count($history) - max(1, (int) $this->options['core_window']));
        $familyOffset = max(0, count($history) - max(1, (int) $this->options['family_window']));
        $monthAgo = time() - max(1, (int) $this->options['family_days']) * 86400;

        foreach (array_values($history) as $index => $row) {
            $core = (string) ($row['core_key'] ?? '');
            if ($core !== '') {
                if (! isset($state['core_counts'][$core])) {
                    $pattern = $this->catalog[$core]['pattern_key'] ?? null;
                    if ($pattern !== null) {
                        $state['used_patterns'][$pattern] = ($state['used_patterns'][$pattern] ?? 0) + 1;
                    }
                }
                $state['core_counts'][$core] = ($state['core_counts'][$core] ?? 0) + 1;
            }

            $reserved = (bool) ($row['is_reserved'] ?? false);
            if ($index >= $recentOffset || $reserved) {
                if ($core !== '') {
                    $state['blocked'][$core] = true;
                }
                $base = (string) ($row['base'] ?? $this->catalog[$core]['base'] ?? '');
                if ($base !== '' && preg_match('/^[a-z0-9]+$/', $base) !== 1) {
                    $base = UsernameFingerprint::compact($base);
                }
                if ($base !== '') {
                    $id = count($state['near']);
                    $state['near'][$id] = $base;
                    foreach ($this->grams($base) as $gram => $count) {
                        $state['grams'][$gram][$id] = $count;
                    }
                }
            }

            $dateKey = isset($row['created_at']) ? (string) $row['created_at'] : '';
            $date = $dateKey !== '' ? ($this->dateCache[$dateKey] ??= strtotime($dateKey)) : false;
            $scheduledDays = $row['scheduled_days'] ?? (isset($row['scheduled_day']) ? [$row['scheduled_day']] : []);
            $sameDay = $generationDay !== null && $generationDay !== '' && in_array($generationDay, $scheduledDays, true);
            foreach (array_unique($row['family_keys'] ?? []) as $family) {
                if ($index >= $familyOffset || $reserved) {
                    $state['families'][$family] = ($state['families'][$family] ?? 0) + 1;
                }
                if ($date !== false && $date >= $monthAgo) {
                    $state['monthly_families'][$family] = ($state['monthly_families'][$family] ?? 0) + 1;
                }
                if ($sameDay) {
                    $state['day_families'][$family] = ($state['day_families'][$family] ?? 0) + 1;
                }
            }
            if ($index >= $familyOffset && isset($row['theme'])) {
                $state['themes'][$row['theme']] = ($state['themes'][$row['theme']] ?? 0) + 1;
            }
        }

        return $state;
    }

    private function allowed(array $entry, array $state): bool
    {
        $core = (string) ($entry['core_key'] ?? '');
        if ($core !== '' && isset($state['blocked'][$core])) {
            return false;
        }
        $base = UsernameFingerprint::compact((string) ($entry['base'] ?? $entry['username'] ?? ''));
        $length = strlen($base);
        if ($length < max(4, (int) $this->options['similarity_min_length'])) {
            return true;
        }

        $overlap = [];
        foreach ($this->grams($base) as $gram => $count) {
            foreach ($state['grams'][$gram] ?? [] as $id => $otherCount) {
                $overlap[$id] = ($overlap[$id] ?? 0) + min($count, $otherCount);
            }
        }
        $threshold = max(0.8, min(1.0, (float) $this->options['similarity_threshold']));
        foreach ($overlap as $id => $shared) {
            $other = $state['near'][$id];
            $otherLength = strlen($other);
            $maxLength = max($length, $otherLength);
            $maxEdits = (int) floor((1.0 - $threshold) * $maxLength + 0.000001);
            if (abs($length - $otherLength) > $maxEdits || $shared < max($length - 2, $otherLength - 2) - 3 * $maxEdits) {
                continue;
            }
            if (levenshtein($base, $other) <= $maxEdits) {
                return false;
            }
        }

        return true;
    }

    private function score(array $entry, array $state): float
    {
        $score = 0.0;
        foreach ($entry['family_keys'] ?? [] as $family) {
            $recent = $state['families'][$family] ?? 0;
            $score += $recent * $recent + 0.15 * ($state['monthly_families'][$family] ?? 0);
            $sameDay = $state['day_families'][$family] ?? 0;
            $score += 3 * $sameDay * $sameDay;
        }

        $base = (string) ($entry['base'] ?? '');
        $length = strlen($base);
        if ($length >= 5 && $length < max(4, (int) $this->options['similarity_min_length'])) {
            foreach ($state['near'] as $other) {
                if (abs($length - strlen($other)) <= 1 && levenshtein($base, $other) <= 1) {
                    // Short words are not categorically banned for one changed letter.
                    $score += 20;
                }
            }
        }

        return $score + 0.2 * ($state['themes'][$entry['theme'] ?? ''] ?? 0);
    }

    private function grams(string $value): array
    {
        if (isset($this->gramCache[$value])) {
            return $this->gramCache[$value];
        }
        $grams = [];
        for ($i = 0; $i <= strlen($value) - 3; $i++) {
            $gram = substr($value, $i, 3);
            $grams[$gram] = ($grams[$gram] ?? 0) + 1;
        }

        // Bound memory for long-lived workers; this cache affects speed only.
        if (count($this->gramCache) > 3000) {
            $this->gramCache = [];
        }

        return $this->gramCache[$value] = $grams;
    }

    private function weighted(array $weights): string
    {
        $draw = ($this->randomInt)(1, max(1, array_sum($weights)));
        foreach ($weights as $key => $weight) {
            $draw -= $weight;
            if ($draw <= 0) {
                return (string) $key;
            }
        }

        return (string) array_key_first($weights);
    }
}
