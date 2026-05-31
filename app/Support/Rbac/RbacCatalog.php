<?php

namespace App\Support\Rbac;

class RbacCatalog
{
    /**
     * @return array<string, string>
     */
    public static function roles(): array
    {
        return [
            'team_access' => 'Team Access',
        ];
    }

    /**
     * @return array<string, array<int, array{key: string, label: string}>>
     */
    public static function permissionGroups(): array
    {
        return [
            'System' => [
                ['key' => 'settings.manage', 'label' => 'Einstellungen verwalten'],
            ],
            'Employees' => [
                ['key' => 'employees.view', 'label' => 'Mitarbeiter anzeigen'],
                ['key' => 'employees.create', 'label' => 'Mitarbeiter erstellen & bearbeiten'],
                ['key' => 'roles.manage', 'label' => 'Rollen verwalten'],
            ],
            'Bewertungen' => [
                ['key' => 'ratings.view', 'label' => 'Bewertungen anzeigen'],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function allPermissions(): array
    {
        $all = [];
        foreach (self::permissionGroups() as $permissionItems) {
            foreach ($permissionItems as $item) {
                $key = (string) ($item['key'] ?? '');
                if ($key !== '') {
                    $all[] = $key;
                }
            }
        }

        return array_values(array_unique($all));
    }

    /**
     * @return array<string, string>
     */
    public static function permissionLabels(): array
    {
        $labels = [];
        foreach (self::permissionGroups() as $permissionItems) {
            foreach ($permissionItems as $item) {
                $key = (string) ($item['key'] ?? '');
                if ($key === '') {
                    continue;
                }

                $labels[$key] = (string) ($item['label'] ?? $key);
            }
        }

        return $labels;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function defaultRolePermissions(): array
    {
        return [
            'team_access' => self::allPermissions(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function defaultTeamPermissions(): array
    {
        $defaults = [];
        foreach (self::allPermissions() as $permission) {
            $defaults[$permission] = false;
        }

        return $defaults;
    }
}
