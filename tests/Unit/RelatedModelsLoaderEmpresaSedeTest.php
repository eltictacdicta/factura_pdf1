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

namespace FacturaPdf1\Tests\Unit;

use FSFramework\Plugins\factura_pdf1\Model\View\RelatedModelsLoader;
use PHPUnit\Framework\TestCase;

/**
 * DB-free spy `fs_db2` used to prove the resolver never writes. The loader
 * contract under test never opens a connection; the spy only records the
 * statements that would have been issued.
 */
final class EmpresaSedeLoaderSpyDb
{
    /** @var list<string> */
    public array $execStatements = [];

    /** @var list<string> */
    public array $selectStatements = [];

    public function var2str($val)
    {
        if ($val === null) {
            return 'NULL';
        }
        if (is_bool($val)) {
            return $val ? '1' : '0';
        }
        if (is_int($val) || is_float($val)) {
            return (string) $val;
        }

        return "'" . addslashes((string) $val) . "'";
    }

    public function escape_string(string $str): string
    {
        return addslashes($str);
    }

    public function sql_to_int(string $col): string
    {
        return 'CAST(' . $col . ' AS INTEGER)';
    }

    public function select($sql, $params = [])
    {
        $this->selectStatements[] = trim((string) $sql);

        return [];
    }

    public function exec($sql, $transaction = null, $params = [], $batch = false)
    {
        $this->execStatements[] = trim((string) $sql);

        return true;
    }

    public function lastval()
    {
        return 1;
    }
}

/**
 * WU-2 print-integration contract (design cases 27-35).
 *
 * `RelatedModelsLoader::resolveEmpresa()` is the single interception point
 * between the persisted company and the print views: it adopts the sede
 * resolved for the document type BEFORE the caller derives `codpais`, keeps
 * the base `\empresa` identity when nothing is mapped, and always publishes
 * the winning company's `telefono` as the printable `telefono1`.
 *
 * The `assertSame` identity contract lives here (it was deliberately not
 * faked at the business_data model layer, see `EmpresaSedeResolutionTest`).
 */
final class RelatedModelsLoaderEmpresaSedeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['config2'] = [];
        $GLOBALS['plugins'] = [];

        // `fs_settings` is deliberately NOT preloaded: the production loader
        // reaches `empresa_sede::mapping()`, which must resolve its own
        // dependency from any entry point. Preloading it here masked the
        // production `Class "fs_settings" not found` fatal; see
        // plugins/business_data/tests/EmpresaSedeEntryPointLoadingTest.php.
        RelatedModelsLoader::requireRelatedModels();
        if (!class_exists('empresa_sede', false)) {
            require_once FS_FOLDER . '/plugins/business_data/model/empresa_sede.php';
        }

        $this->resetEmpresaRowCache();
        $this->resetFsModelCheckedTables();
    }

    protected function tearDown(): void
    {
        $GLOBALS['config2'] = [];
        $GLOBALS['plugins'] = [];

        $this->resetEmpresaRowCache();

        parent::tearDown();
    }

    // =====================================================================
    // Case 27 — RuntimeException preserved even with a mapping present
    // =====================================================================

    public function testMissingBaseCompanyStillThrowsEvenWhenASedeIsMapped(): void
    {
        $GLOBALS['config2'] = ['empresa_sede_factura' => 'S1'];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Empresa no configurada.');

        RelatedModelsLoader::resolveEmpresa(false, 'factura', static fn (string $cod) => null);
    }

    // =====================================================================
    // Cases 28 + 29 — no-type / unmapped / unknown-type keep the base identity
    // =====================================================================

    public function testNullDocumentTypeKeepsBaseIdentityAndPrintsTheBasePhone(): void
    {
        $base = $this->makeBase(['telefono' => '600999']);

        $out = RelatedModelsLoader::resolveEmpresa($base, null);

        $this->assertSame($base, $out, 'a null document type must keep the base instance');
        $this->assertSame('600999', $out->telefono1, 'the base company phone must become printable');
    }

    public function testUnknownDocumentTypeKeepsBaseIdentity(): void
    {
        $base = $this->makeBase(['telefono' => '600999']);

        $out = RelatedModelsLoader::resolveEmpresa($base, 'factura_simplificada');

        $this->assertSame($base, $out, 'an unknown literal must not resolve any override');
    }

    public function testUnmappedTypeKeepsBaseIdentityAndPrintsTheBasePhone(): void
    {
        $base = $this->makeBase(['telefono' => '600999']);

        $out = RelatedModelsLoader::resolveEmpresa($base, 'factura');

        $this->assertSame($base, $out, 'a known type without a mapping must keep the base instance');
        $this->assertSame('600999', $out->telefono1);
    }

    // =====================================================================
    // Cases 30 + 31 + 32 — the sede is adopted, its country wins, dangling
    //            code keeps the base
    // =====================================================================

    public function testMappedSedeIsAdoptedWithItsFieldsAndPrintablePhone(): void
    {
        $GLOBALS['config2'] = ['empresa_sede_factura' => 'S1'];

        $base = $this->makeBase([
            'nombre' => 'Empresa Base',
            'codpais' => 'FRA',
            'telefono' => '600999',
            'web' => 'https://base.example.com',
        ]);
        $sede = $this->makeSede([
            'codsede' => 'S1',
            'nombre' => 'Sede Norte',
            'codpais' => 'ESP',
            'telefono' => '600111222',
            'web' => '',
        ]);

        $out = RelatedModelsLoader::resolveEmpresa(
            $base,
            'factura',
            static fn (string $cod) => $cod === 'S1' ? $sede : null
        );

        $this->assertNotSame($base, $out, 'a mapped sede must be adopted as a transient clone');
        $this->assertSame('Sede Norte', $out->nombre, 'the sede name must win');
        $this->assertSame('ESP', $out->codpais, 'the sede country must win');
        $this->assertSame('600111222', $out->telefono1, 'the sede phone must become printable');
        $this->assertSame('https://base.example.com', $out->web, 'an empty sede field must inherit the base');

        $this->assertSame('Empresa Base', $base->nombre, 'the base company must stay unmodified');
        $this->assertSame('FRA', $base->codpais);
        $this->assertSame('600999', $base->telefono);
        $this->assertSame([], $base->writes, 'the base company must never be saved, deleted nor exists()-checked');
    }

    public function testDanglingMappingKeepsTheBaseIdentity(): void
    {
        $GLOBALS['config2'] = ['empresa_sede_factura' => 'NOPE'];

        $base = $this->makeBase();

        $out = RelatedModelsLoader::resolveEmpresa(
            $base,
            'factura',
            static fn (string $cod) => null
        );

        $this->assertSame($base, $out, 'a mapped but dangling code must keep the base instance');
    }

    public function testResolvedSedeCountryIsExposedBeforeTheCodpaisDerivation(): void
    {
        $GLOBALS['config2'] = ['empresa_sede_factura' => 'S1'];

        $base = $this->makeBase(['codpais' => 'FRA']);
        $sede = $this->makeSede(['codsede' => 'S1', 'nombre' => 'Sede Sur', 'codpais' => 'ESP']);

        $out = RelatedModelsLoader::resolveEmpresa(
            $base,
            'factura',
            static fn (string $cod) => $cod === 'S1' ? $sede : null
        );

        $this->assertSame('ESP', $out->codpais, 'the resolved country must come from the sede');

        // The override must sit before the `$codpais` derivation, because that
        // is the value that selects the `pais` row in load().
        $src = (string) file_get_contents(FS_FOLDER . '/plugins/factura_pdf1/Model/View/RelatedModelsLoader.php');
        $override = strpos($src, 'self::resolveEmpresa(');
        $derivation = strpos($src, '$codpais =');

        $this->assertNotFalse($override, 'the loader must resolve the company through resolveEmpresa()');
        $this->assertNotFalse($derivation, 'the loader must still derive $codpais');
        $this->assertLessThan(
            $derivation,
            $override,
            'the sede override must precede the $codpais derivation so pais resolves from the sede'
        );
    }

    // =====================================================================
    // Cases 33 + 34 + 35 — signature, cache purity and no persistence
    // =====================================================================

    public function testLoadExposesTheOptionalDocumentTypeAndKeepsTheException(): void
    {
        $method = new \ReflectionMethod(RelatedModelsLoader::class, 'load');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'load() takes the document and the optional document type');
        $this->assertSame('document', $params[0]->getName());
        $this->assertSame('documentType', $params[1]->getName());
        $this->assertTrue($params[1]->isOptional(), 'documentType must be optional');
        $this->assertNull($params[1]->getDefaultValue(), 'documentType must default to null');

        $src = (string) file_get_contents(FS_FOLDER . '/plugins/factura_pdf1/Model/View/RelatedModelsLoader.php');
        $this->assertStringContainsString(
            "RuntimeException('Empresa no configurada.')",
            $src,
            'the missing-company exception contract must stay in the loader'
        );
    }

    public function testResolvingASedeDoesNotPopulateOrAlterTheEmpresaRowCache(): void
    {
        $this->assertNull($this->readEmpresaRowCache(), 'setUp must start from a pristine cache');

        $GLOBALS['config2'] = ['empresa_sede_factura' => 'S1'];
        $base = $this->makeBase();
        $sede = $this->makeSede(['codsede' => 'S1', 'nombre' => 'Sede Norte', 'codpais' => 'ESP']);

        RelatedModelsLoader::resolveEmpresa(
            $base,
            'factura',
            static fn (string $cod) => $cod === 'S1' ? $sede : null
        );

        $this->assertNull($this->readEmpresaRowCache(), 'adopting a sede must not populate the row cache');

        // An already-populated cache must not be altered either.
        $sentinel = [['id' => 99, 'nombre' => 'Cached']];
        $this->writeEmpresaRowCache($sentinel);

        RelatedModelsLoader::resolveEmpresa($base, 'factura');

        $this->assertSame($sentinel, $this->readEmpresaRowCache(), 'the row cache must stay untouched');
    }

    public function testResolveEmpresaIssuesNoDatabaseWrites(): void
    {
        $base = $this->makeBase(['telefono' => '600999']);

        RelatedModelsLoader::resolveEmpresa($base, 'factura');
        RelatedModelsLoader::resolveEmpresa($base, null);

        $this->assertSame([], $base->spyDb->execStatements, 'resolveEmpresa() must not INSERT/UPDATE anything');
        $this->assertSame([], $base->writes, 'the base company must not be persisted');
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /**
     * Builds a DB-free base `\empresa`. The anonymous subclass bypasses
     * `fs_model`'s DB-bound constructor (which would run `check_table()`).
     *
     * @param array<string, mixed> $overrides
     */
    private function makeBase(array $overrides = []): \empresa
    {
        $row = array_merge([
            'id' => 1,
            'cifnif' => 'B00000000',
            'nombre' => 'Empresa Base',
            'administrador' => 'Admin',
            'direccion' => 'Calle Base 1',
            'codpais' => 'FRA',
            'telefono' => '',
            'web' => 'https://base.example.com',
        ], $overrides);

        return new class($row) extends \empresa {
            /** @var list<string> */
            public array $writes = [];

            public EmpresaSedeLoaderSpyDb $spyDb;

            /**
             * @param array<string, mixed> $row
             */
            public function __construct(array $row)
            {
                $this->spyDb = new EmpresaSedeLoaderSpyDb();
                $this->db = $this->spyDb;
                $this->table_name = 'empresa';

                foreach ($row as $key => $value) {
                    if (property_exists($this, $key)) {
                        $this->{$key} = $value;
                    }
                }
            }

            public function save()
            {
                $this->writes[] = 'save';

                return true;
            }

            public function delete()
            {
                $this->writes[] = 'delete';

                return true;
            }

            public function exists()
            {
                $this->writes[] = 'exists';

                return false;
            }
        };
    }

    /**
     * Builds a DB-free `\empresa_sede` carrying the given row.
     *
     * @param array<string, mixed> $row
     */
    private function makeSede(array $row): \empresa_sede
    {
        return new class($row) extends \empresa_sede {
            /**
             * @param array<string, mixed> $row
             */
            public function __construct(array $row)
            {
                $this->table_name = 'empresa_sedes';

                foreach ($row as $key => $value) {
                    if (property_exists($this, $key)) {
                        $this->{$key} = $value;
                    }
                }
            }
        };
    }

    private function readEmpresaRowCache(): mixed
    {
        $ref = new \ReflectionClass('empresa');
        $prop = $ref->getProperty('empresa_row_cache');
        $prop->setAccessible(true);

        return $prop->getValue();
    }

    private function writeEmpresaRowCache(mixed $value): void
    {
        $ref = new \ReflectionClass('empresa');
        $prop = $ref->getProperty('empresa_row_cache');
        $prop->setAccessible(true);
        $prop->setValue(null, $value);
    }

    private function resetEmpresaRowCache(): void
    {
        // Reset to null (not []): a plain [] makes isset() true and would skip
        // fs_model's lazy core_log/base_dir initialization.
        $this->writeEmpresaRowCache(null);
    }

    private function resetFsModelCheckedTables(): void
    {
        $ref = new \ReflectionClass('fs_model');
        $prop = $ref->getProperty('checked_tables');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }
}
