<?php

namespace App\Services;

use App\Models\SyntheticRatingUser;
use App\Support\Rating\SyntheticIdentityGenerator;
use Illuminate\Support\Str;

class UsernameQualityAnalyzer
{
    /** Read-only report. Similar spelling is a bounded hint, never an identity assertion. */
    public function analyze(): array
    {
        $generator = new SyntheticIdentityGenerator;
        $rows = [];
        $exact = [];
        $cores = [];
        $families = [];
        $unknown = 0;

        foreach (SyntheticRatingUser::query()->orderBy('id')->lazyById(500) as $user) {
            $username = (string) ($user->username ?: $user->name);
            $metadata = data_get($user->data, 'username_generation', []);
            if (! is_array($metadata) || empty($metadata['core_key'])) {
                $metadata = $generator->describeUsername($username);
            }
            $core = $metadata['core_key'] ?? null;
            $confidence = $metadata['confidence'] ?? ($core ? 'exact' : 'unknown');
            $familyKeys = $metadata['family_keys'] ?? [];
            $key = strtolower(Str::ascii(trim($username)));
            if ($key !== '') {
                $exact[$key][] = $user->id;
            }
            if ($core) {
                $cores[$core][] = $user->id;
            } else {
                $unknown++;
            }
            $rows[$user->id] = [
                'username' => $username, 'core_key' => $core,
                'base' => $metadata['base'] ?? null,
                'confidence' => $confidence, 'family_keys' => $familyKeys,
            ];
        }

        $recent = array_slice($rows, -200, null, true);
        foreach ($recent as $id => $row) {
            foreach (array_unique($row['family_keys']) as $family) {
                $families[$family][] = $id;
            }
        }
        $frequentFamilies = array_filter($families, fn ($ids) => count($ids) >= max(3, (int) ceil(count($recent) * .05)));
        uasort($frequentFamilies, fn ($a, $b) => count($b) <=> count($a));

        $duplicates = array_filter($exact, fn ($ids) => count($ids) > 1);
        $repeatedCores = array_filter($cores, fn ($ids) => count($ids) > 1);
        $similar = $this->similarityHints(array_slice($rows, -1000, null, true));
        $ids = static fn (array $groups): array => array_values(array_unique(array_merge([], ...array_values($groups))));

        return [
            'generated_at' => now()->toDateTimeString(),
            'total' => count($rows), 'recognized' => count($rows) - $unknown,
            'unknown' => $unknown, 'distinct_cores' => count($cores),
            'base_repeats' => count($rows) - $unknown - count($cores),
            'duplicate_groups' => count($duplicates),
            'duplicate_ids' => $ids($duplicates), 'core_ids' => $ids($repeatedCores),
            'family_ids' => $ids($frequentFamilies), 'similar_ids' => array_keys($similar),
            'families' => array_map('count', array_slice($frequentFamilies, 0, 8, true)),
            'rows' => $rows, 'similar' => $similar,
        ];
    }

    private function similarityHints(array $rows): array
    {
        $index = [];
        $previous = [];
        $hints = [];
        foreach ($rows as $id => $row) {
            // Unknown historical suffixes are deliberately not stripped.
            $base = preg_replace('/[^a-z]/', '', Str::ascii(strtolower((string) ($row['base'] ?: $row['username']))));
            if (strlen($base) < 8) {
                continue;
            }
            $grams = [];
            for ($i = 0; $i <= strlen($base) - 3; $i++) {
                $grams[substr($base, $i, 3)] = true;
            }
            $candidates = [];
            foreach ($grams as $gram => $_) {
                foreach ($index[$gram] ?? [] as $otherId) {
                    $candidates[$otherId] = ($candidates[$otherId] ?? 0) + 1;
                }
            }
            arsort($candidates);
            foreach (array_slice($candidates, 0, 60, true) as $otherId => $_) {
                $other = $previous[$otherId];
                if ($base === $other || abs(strlen($base) - strlen($other)) > 3) {
                    continue;
                }
                if (1 - levenshtein($base, $other) / max(strlen($base), strlen($other)) >= .88) {
                    $hints[$id] = $otherId;
                    $hints[$otherId] = $id;
                    break;
                }
            }
            $previous[$id] = $base;
            foreach ($grams as $gram => $_) {
                $index[$gram][] = $id;
            }
        }

        return $hints;
    }
}
