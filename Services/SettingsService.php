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

namespace FSFramework\Plugins\factura_pdf1\Services;

use FSFramework\Plugins\factura_pdf1\Model\FacturaPdf1Setting;

final class SettingsService
{
    public const ROW_NAME = FacturaPdf1Setting::DEFAULT_ROW_NAME;

    public const IN_CODE_VERSION = 2;

    /** @var list<string> Upstream FacturaPDF1 SettingsInvoice.xml fieldnames. */
    public const UPSTREAM_SETTING_KEYS = [
        'posicionlogo',
        'margenlogo',
        'medidalogo',
        'espaciomaximoempresa',
        'ocultarprovincia',
        'ocultarpais',
        'mostraralmacen',
        'tituloalmacen',
        'mostraralmacentel',
        'ocultardireccionenvio',
        'ref2',
        'documentosrelacionados',
        'colorcabecera',
        'colorfilas',
        'espaciofilas',
        'ocultarreferenciaprod',
        'ocultartablaimpuestos',
        'pagoyvencimiento',
        'traducirformaspago',
        'posiciontexto1',
        'medidatexto1',
        'colortexto1',
        'justiftexto1',
        'posiciontexto2',
        'medidatexto2',
        'colortexto2',
        'justiftexto2',
        'texto2',
        'disposicion_cabecera',
    ];

    public function __construct(private ?FacturaPdf1Setting $settingModel = null)
    {
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return list<string>
     */
    public function knownSettingKeys(): array
    {
        return array_keys($this->defaults());
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'posicionlogo' => 0,
            'margenlogo' => 0,
            'medidalogo' => 100,
            'espaciomaximoempresa' => 280,
            'ocultarprovincia' => false,
            'ocultarpais' => false,
            'mostraralmacen' => ' ',
            'tituloalmacen' => '',
            'mostraralmacentel' => false,
            'ocultardireccionenvio' => false,
            'ref2' => 2,
            'documentosrelacionados' => 1,
            'colorcabecera' => '#E9E9E9',
            'colorfilas' => '#EDEDED',
            'espaciofilas' => 4,
            'ocultarreferenciaprod' => false,
            'ocultartablaimpuestos' => false,
            'pagoyvencimiento' => 3,
            'traducirformaspago' => true,
            'posiciontexto1' => 7,
            'medidatexto1' => 8,
            'colortexto1' => '#555555',
            'justiftexto1' => 'left',
            'posiciontexto2' => 7,
            'medidatexto2' => 8,
            'colortexto2' => '#555555',
            'justiftexto2' => 'left',
            'texto2' => '',
            'disposicion_cabecera' => 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function load(): array
    {
        $row = $this->resolveModel()->getByName(self::ROW_NAME);
        if ($row === false) {
            return $this->defaults();
        }

        $stored = $this->decodeJson($row->settings_json);
        $version = $row->current_version;

        if ($version < self::IN_CODE_VERSION) {
            $stored = $this->applyMigrations($stored, $version);
            $this->persist($stored, self::IN_CODE_VERSION);
        }

        return $this->mergeWithDefaults($stored);
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function save(array $settings): void
    {
        $row = $this->resolveModel()->getByName(self::ROW_NAME);
        $currentVersion = $row === false ? 0 : $row->current_version;
        $existing = $row === false ? [] : $this->decodeJson($row->settings_json);

        $merged = $this->mergeStoredSettings($existing, $settings);
        $this->persist($merged, $currentVersion + 1);
    }

    public function currentVersion(): int
    {
        $row = $this->resolveModel()->getByName(self::ROW_NAME);

        return $row === false ? 0 : $row->current_version;
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    public function applyMigrations(array $settings, int $fromVersion): array
    {
        if ($fromVersion >= self::IN_CODE_VERSION) {
            return $settings;
        }

        $settings = $this->migrateMostrarpais($settings);
        $settings = $this->migrateOcultarReferenciasFact($settings);

        return $settings;
    }

    public function reset(): void
    {
        $this->persist($this->defaults(), self::IN_CODE_VERSION);
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    private function mergeWithDefaults(array $stored): array
    {
        $merged = $this->defaults();
        foreach ($stored as $key => $value) {
            $merged[$key] = $value;
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $incoming
     *
     * @return array<string, mixed>
     */
    private function mergeStoredSettings(array $existing, array $incoming): array
    {
        $merged = $this->mergeWithDefaults($existing);
        foreach ($incoming as $key => $value) {
            $merged[$key] = $value;
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function persist(array $settings, int $version): void
    {
        $json = json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $model = $this->resolveModel();
        if (!$model->saveAtomic(self::ROW_NAME, $json, $version)) {
            throw new \RuntimeException('No se pudieron guardar los ajustes de factura_pdf1.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $json): array
    {
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    private function migrateMostrarpais(array $settings): array
    {
        if (!array_key_exists('mostrarpais', $settings)) {
            return $settings;
        }

        if (!array_key_exists('ocultarpais', $settings)) {
            $settings['ocultarpais'] = self::isTruthy($settings['mostrarpais']) ? false : true;
        }

        unset($settings['mostrarpais']);

        return $settings;
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    private function migrateOcultarReferenciasFact(array $settings): array
    {
        if (!array_key_exists('ocultarreferenciasfact', $settings)) {
            return $settings;
        }

        if (!array_key_exists('documentosrelacionados', $settings)) {
            $settings['documentosrelacionados'] = self::isTruthy($settings['ocultarreferenciasfact']) ? 0 : 2;
        }

        unset($settings['ocultarreferenciasfact']);

        return $settings;
    }

    private static function isTruthy(mixed $value): bool
    {
        if ($value === false || $value === null || $value === '' || $value === '0' || $value === 0) {
            return false;
        }

        return true;
    }

    private function resolveModel(): FacturaPdf1Setting
    {
        return $this->settingModel ?? new FacturaPdf1Setting();
    }
}
