<?php

namespace App\Support\Rating;

use Illuminate\Support\Str;

/** Describes known cores without blindly stripping arbitrary letters or digits. */
final class UsernameFingerprint
{
    private static array $cachedKnown = [];

    private static array $cachedLengths = [];

    private array $known = [];

    private array $lengths = [];

    public function __construct(array $legacyAliases = [], array $legacyModifiers = [], array $legacySubjects = [])
    {
        if (self::$cachedKnown !== []) {
            $this->known = self::$cachedKnown;
            $this->lengths = self::$cachedLengths;

            return;
        }
        foreach (UsernameCatalog::all() as $entry) {
            $this->add($entry['base'], $entry);
            $tokens = $entry['tokens'] ?? [];
            if (count($tokens) === 2 && $entry['pattern_key'] === 'modifier_subject') {
                $this->add(implode('', array_reverse($tokens)), $entry);
            }
        }

        foreach ($legacyAliases as $alias) {
            $base = self::compact($alias);
            $this->add($base, [
                'core_key' => 'legacy:'.$base,
                'base' => $base,
                'family_keys' => [$base],
                'pattern_key' => 'curated_alias',
                'theme' => 'legacy',
            ], false);
        }

        foreach ($legacyModifiers as $modifier) {
            foreach ($legacySubjects as $subject) {
                $base = self::compact($modifier.$subject);
                $entry = $this->known[$base] ?? [
                    'core_key' => 'legacy:'.$base,
                    'base' => $base,
                    'family_keys' => [self::compact($modifier), self::compact($subject)],
                    'pattern_key' => 'modifier_subject',
                    'theme' => 'legacy',
                ];
                $this->add($base, $entry, false);
                $this->add($subject.$modifier, $entry, false);
            }
        }

        $this->lengths = array_keys($this->lengths);
        rsort($this->lengths);
        self::$cachedKnown = $this->known;
        self::$cachedLengths = $this->lengths;
    }

    public static function key(string $username): string
    {
        return Str::of(trim($username))->ascii()->lower()->toString();
    }

    public static function compact(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', self::key($value)) ?? '';
    }

    public function describe(string $username): array
    {
        $key = self::key($username);
        $compact = self::compact($username);

        foreach ($this->lengths as $length) {
            if ($length > strlen($compact)) {
                continue;
            }
            $prefix = substr($compact, 0, $length);
            $entry = $this->known[$prefix] ?? null;
            if (! $entry) {
                continue;
            }

            // Known generation context is at most three initials, a local code
            // and a short year/number. Unknown longer material is not guessed.
            $suffix = substr($compact, $length);
            if ($suffix !== '' && ! preg_match('/^[a-z0-9]{1,10}$/', $suffix)) {
                continue;
            }

            return array_merge($entry, [
                'username' => $username,
                'username_key' => $key,
                'confidence' => $suffix === '' ? 'certain' : 'estimated',
                'catalog_version' => UsernameCatalog::version(),
            ]);
        }

        return [
            'username' => $username,
            'username_key' => $key,
            'core_key' => null,
            'base' => $compact,
            'family_keys' => [],
            'pattern_key' => 'unknown',
            'confidence' => 'unknown',
            'catalog_version' => UsernameCatalog::version(),
        ];
    }

    private function add(string $base, array $entry, bool $replace = true): void
    {
        $compact = self::compact($base);
        if ($compact === '' || (! $replace && isset($this->known[$compact]))) {
            return;
        }
        $this->known[$compact] = $entry;
        $this->lengths[strlen($compact)] = true;
    }
}
