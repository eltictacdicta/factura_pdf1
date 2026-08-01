<?php
/**
 * This file is part of factura_pdf1
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace FSFramework\Plugins\factura_pdf1\Model;

/**
 * Persistence model for the factura_pdf1_settings singleton row.
 */
final class FacturaPdf1Setting extends \fs_model
{
    public const DEFAULT_ROW_NAME = 'default';

    public ?int $id = null;

    public string $name = self::DEFAULT_ROW_NAME;

    public string $settings_json = '{}';

    public int $current_version = 0;

    public ?string $created_at = null;

    public ?string $updated_at = null;

    /** @var array<string, array{settings_json: string, current_version: int}>|null */
    private static ?array $testStorage = null;

    private static bool $forceSaveAtomicFailure = false;

    public function __construct($data = false)
    {
        if (self::$testStorage !== null) {
            if (is_array($data)) {
                $this->hydrateFromArray($data);
            }

            return;
        }

        parent::__construct('factura_pdf1_settings');
        if (is_array($data)) {
            $this->hydrateFromArray($data);
        }
    }

    public static function enableTestStorage(): void
    {
        self::$testStorage = [];
    }

    public static function disableTestStorage(): void
    {
        self::$testStorage = null;
        self::$forceSaveAtomicFailure = false;
    }

    public static function forceSaveAtomicFailure(bool $enabled = true): void
    {
        self::$forceSaveAtomicFailure = $enabled;
    }

    /**
     * @param array{settings_json?: string, current_version?: int, name?: string} $row
     */
    public static function seedTestRow(array $row): void
    {
        if (self::$testStorage === null) {
            self::enableTestStorage();
        }

        $name = $row['name'] ?? self::DEFAULT_ROW_NAME;
        self::$testStorage[$name] = [
            'settings_json' => $row['settings_json'] ?? '{}',
            'current_version' => (int) ($row['current_version'] ?? 0),
        ];
    }

    /**
     * @return array{settings_json: string, current_version: int}|null
     */
    public static function getTestRow(?string $name = null): ?array
    {
        if (self::$testStorage === null) {
            return null;
        }

        $name ??= self::DEFAULT_ROW_NAME;

        return self::$testStorage[$name] ?? null;
    }

    public function getByName(string $name): self|false
    {
        if (self::$testStorage !== null) {
            if (!isset(self::$testStorage[$name])) {
                return false;
            }

            return new self([
                'name' => $name,
                'settings_json' => self::$testStorage[$name]['settings_json'],
                'current_version' => self::$testStorage[$name]['current_version'],
            ]);
        }

        $data = $this->db->select(
            'SELECT * FROM ' . $this->table_name
            . ' WHERE name = ' . $this->var2str($name) . ';'
        );

        return $data ? new self($data[0]) : false;
    }

    public function saveAtomic(string $name, string $settingsJson, int $currentVersion): bool
    {
        if (self::$testStorage !== null) {
            if (self::$forceSaveAtomicFailure) {
                return false;
            }

            self::$testStorage[$name] = [
                'settings_json' => $settingsJson,
                'current_version' => $currentVersion,
            ];

            return true;
        }

        if (!$this->db->begin_transaction()) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $existing = $this->getByName($name);
        if ($existing instanceof self) {
            $sql = 'UPDATE ' . $this->table_name . ' SET '
                . 'settings_json = ' . $this->var2str($settingsJson)
                . ', current_version = ' . $this->var2str($currentVersion)
                . ', updated_at = ' . $this->var2str($now)
                . ' WHERE name = ' . $this->var2str($name) . ';';
        } else {
            $sql = 'INSERT INTO ' . $this->table_name
                . ' (name, settings_json, current_version, created_at, updated_at) VALUES ('
                . $this->var2str($name) . ','
                . $this->var2str($settingsJson) . ','
                . $this->var2str($currentVersion) . ','
                . $this->var2str($now) . ','
                . $this->var2str($now) . ');';
        }

        if (!$this->db->exec($sql, false)) {
            return false;
        }

        return $this->db->commit();
    }

    public function test(): bool
    {
        if ($this->name === '') {
            return false;
        }

        json_decode($this->settings_json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        return $this->current_version >= 0;
    }

    public function exists(): bool
    {
        if (self::$testStorage !== null) {
            return isset(self::$testStorage[$this->name]);
        }

        if ($this->id === null) {
            return false;
        }

        return (bool) $this->db->select(
            'SELECT id FROM ' . $this->table_name
            . ' WHERE id = ' . $this->var2str($this->id) . ';'
        );
    }

    public function save()
    {
        return $this->saveAtomic($this->name, $this->settings_json, $this->current_version);
    }

    public function delete()
    {
        if (self::$testStorage !== null) {
            unset(self::$testStorage[$this->name]);

            return true;
        }

        return $this->db->exec(
            'DELETE FROM ' . $this->table_name
            . ' WHERE name = ' . $this->var2str($this->name) . ';'
        );
    }

    protected function install()
    {
        return '';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hydrateFromArray(array $data): void
    {
        if (isset($data['id'])) {
            $this->id = (int) $data['id'];
        }
        if (isset($data['name'])) {
            $this->name = (string) $data['name'];
        }
        if (isset($data['settings_json'])) {
            $this->settings_json = (string) $data['settings_json'];
        }
        if (isset($data['current_version'])) {
            $this->current_version = (int) $data['current_version'];
        }
        if (array_key_exists('created_at', $data)) {
            $this->created_at = $data['created_at'] !== null ? (string) $data['created_at'] : null;
        }
        if (array_key_exists('updated_at', $data)) {
            $this->updated_at = $data['updated_at'] !== null ? (string) $data['updated_at'] : null;
        }
    }
}
