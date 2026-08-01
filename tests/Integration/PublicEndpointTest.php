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

namespace FacturaPdf1\Tests\Integration;

use FacturaPdf1\Tests\Fixtures\DocumentPrintViewFixture;
use FacturaPdf1\Tests\Fixtures\SeedInvoiceFakt20260001;
use FSFramework\Plugins\factura_pdf1\Controller\FacturaPdf1Controller;
use FSFramework\Plugins\factura_pdf1\Model\View\AlbaranPrintView;
use FSFramework\Plugins\factura_pdf1\Model\View\FacturaPrintView;
use FSFramework\Plugins\factura_pdf1\Model\View\PedidoPrintView;
use FSFramework\Plugins\factura_pdf1\Model\View\PresupuestoPrintView;
use FSFramework\Plugins\factura_pdf1\Services\CezpdfRenderService;
use FSFramework\Plugins\factura_pdf1\Services\SettingsService;
use FSFramework\Security\UserAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PublicEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        FacturaPdf1Controller::resetDependenciesForTests();
        FacturaPrintView::resetResolversForTests();
        AlbaranPrintView::resetResolversForTests();
        PedidoPrintView::resetResolversForTests();
        PresupuestoPrintView::resetResolversForTests();
    }

    protected function tearDown(): void
    {
        FacturaPdf1Controller::resetDependenciesForTests();
        FacturaPrintView::resetResolversForTests();
        AlbaranPrintView::resetResolversForTests();
        PedidoPrintView::resetResolversForTests();
        PresupuestoPrintView::resetResolversForTests();
    }

    public function testEndpointStreamsPdfForSeededInvoice(): void
    {
        SeedInvoiceFakt20260001::configureResolvers();
        $adapter = SeedInvoiceFakt20260001::buildAdapter();

        FacturaPdf1Controller::setDependenciesForTests(
            new CezpdfRenderService(),
            new SettingsService(),
            static fn (int $id, Request $request) => $adapter,
            static fn (object $user): UserAdapter => new UserAdapter($user),
        );

        $controller = $this->createController();
        $response = $controller->processRequest(
            Request::create('/index.php', 'GET', ['id' => '1']),
            $this->createLegacyUser(),
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
        $this->assertGreaterThanOrEqual(1024, strlen((string) $response->getContent()));
    }

    public function testMissingIdReturns404Json(): void
    {
        $controller = $this->createController();
        $response = $controller->processRequest(
            Request::create('/index.php', 'GET', []),
            $this->createLegacyUser(),
        );

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('{"error":"not_found"}', (string) $response->getContent());
    }

    public function testNonNumericIdReturns404(): void
    {
        $controller = $this->createController();
        $response = $controller->processRequest(
            Request::create('/index.php', 'GET', ['id' => 'abc']),
            $this->createLegacyUser(),
        );

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testMissingDocumentReturns404Json(): void
    {
        FacturaPrintView::setResolversForTests(
            static fn (int $id): \FSFramework\model\factura_cliente|false => false,
            null,
        );

        $controller = $this->createController();
        $response = $controller->processRequest(
            Request::create('/index.php', 'GET', ['id' => '999999']),
            $this->createLegacyUser(),
        );

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('{"error":"not_found"}', (string) $response->getContent());
    }

    public function testEndpointStreamsPdfForSeededAlbaran(): void
    {
        $this->configureAlbaranResolvers();

        FacturaPdf1Controller::setDependenciesForTests(
            new CezpdfRenderService(),
            new SettingsService(),
            null,
            static fn (object $user): UserAdapter => new UserAdapter($user),
        );

        $controller = $this->createController();
        $response = $controller->processRequest(
            Request::create('/index.php', 'GET', [
                'page' => 'factura_detallada',
                'tipo' => 'albaran',
                'id' => '1',
            ]),
            $this->createLegacyUser(),
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
        $this->assertStringContainsString('albaran-1.pdf', (string) $response->headers->get('Content-Disposition'));
    }

    public function testEndpointStreamsPdfForSeededPedido(): void
    {
        $this->configurePedidoResolvers();

        FacturaPdf1Controller::setDependenciesForTests(
            new CezpdfRenderService(),
            new SettingsService(),
            null,
            static fn (object $user): UserAdapter => new UserAdapter($user),
        );

        $controller = $this->createController();
        $response = $controller->processRequest(
            Request::create('/index.php', 'GET', [
                'page' => 'factura_detallada',
                'tipo' => 'pedido',
                'id' => '1',
            ]),
            $this->createLegacyUser(),
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
        $this->assertGreaterThanOrEqual(1024, strlen((string) $response->getContent()));
        $this->assertSame('inline; filename="pedido-1.pdf"', (string) $response->headers->get('Content-Disposition'));
    }

    public function testEndpointStreamsPdfForSeededPresupuesto(): void
    {
        $this->configurePresupuestoResolvers();

        FacturaPdf1Controller::setDependenciesForTests(
            new CezpdfRenderService(),
            new SettingsService(),
            null,
            static fn (object $user): UserAdapter => new UserAdapter($user),
        );

        $controller = $this->createController();
        $response = $controller->processRequest(
            Request::create('/index.php', 'GET', [
                'page' => 'factura_detallada',
                'tipo' => 'presupuesto',
                'id' => '1',
            ]),
            $this->createLegacyUser(),
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
        $this->assertGreaterThanOrEqual(1024, strlen((string) $response->getContent()));
        $this->assertSame('inline; filename="presupuesto-1.pdf"', (string) $response->headers->get('Content-Disposition'));
    }

    #[DataProvider('invalidIdProvider')]
    public function testZeroOrNegativeIdReturns404Json(string $id): void
    {
        $controller = $this->createController();
        $response = $controller->processRequest(
            Request::create('/index.php', 'GET', ['id' => $id]),
            $this->createLegacyUser(),
        );

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('{"error":"not_found"}', (string) $response->getContent());
    }

    /** @return array<string, array{0: string}> */
    public static function invalidIdProvider(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-1'],
        ];
    }

    public function testMissingAlbaranReturns404Json(): void
    {
        AlbaranPrintView::setResolversForTests(
            static fn (int $id): \FSFramework\model\albaran_cliente|false => false,
            null,
        );

        $controller = $this->createController();
        $response = $controller->processRequest(
            Request::create('/index.php', 'GET', [
                'page' => 'factura_detallada',
                'tipo' => 'albaran',
                'id' => '42',
            ]),
            $this->createLegacyUser(),
        );

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('{"error":"not_found"}', (string) $response->getContent());
    }

    private function configureAlbaranResolvers(): void
    {
        DocumentPrintViewFixture::requireModels();
        $base = DocumentPrintViewFixture::buildFacturaPayload();
        $document = new \FSFramework\model\albaran_cliente(
            DocumentPrintViewFixture::buildGenericDocumentRow('ALB-TEST-001', 500.0, 400.0),
        );
        $document->idalbaran = 1;

        $line = new \FSFramework\model\linea_albaran_cliente([
            'idlinea' => 1,
            'idalbaran' => 1,
            'referencia' => 'REF-1',
            'descripcion' => 'Linea 1',
            'cantidad' => 1,
            'pvpunitario' => 400,
            'pvpsindto' => 400,
            'dtopor' => 0,
            'dtopor2' => 0,
            'dtopor3' => 0,
            'dtopor4' => 0,
            'pvptotal' => 400,
            'codimpuesto' => 'IVA21',
            'codcombinacion' => null,
            'iva' => 21,
            'recargo' => 0,
            'irpf' => 0,
            'orden' => 1,
            'mostrar_cantidad' => true,
            'mostrar_precio' => true,
        ]);

        $payload = [
            'empresa' => $base['empresa'],
            'cliente' => $base['cliente'],
            'divisa' => $base['divisa'],
            'formaPago' => $base['formaPago'],
            'pais' => $base['pais'],
            'lineas' => [$line],
            'lineasIva' => [],
        ];

        AlbaranPrintView::setResolversForTests(
            static fn (int $id): \FSFramework\model\albaran_cliente|false => $id === 1 ? $document : false,
            static fn (): array => $payload,
        );
    }

    private function configurePedidoResolvers(): void
    {
        DocumentPrintViewFixture::requireModels();
        $base = DocumentPrintViewFixture::buildFacturaPayload();
        $document = new \FSFramework\model\pedido_cliente(
            DocumentPrintViewFixture::buildGenericDocumentRow('PED-TEST-001', 500.0, 400.0),
        );
        $document->idpedido = 1;

        $line = new \FSFramework\model\linea_pedido_cliente([
            'idlinea' => 1,
            'idpedido' => 1,
            'referencia' => 'REF-1',
            'descripcion' => 'Linea 1',
            'cantidad' => 1,
            'pvpunitario' => 400,
            'pvpsindto' => 400,
            'dtopor' => 0,
            'dtopor2' => 0,
            'dtopor3' => 0,
            'dtopor4' => 0,
            'pvptotal' => 400,
            'codimpuesto' => 'IVA21',
            'codcombinacion' => null,
            'iva' => 21,
            'recargo' => 0,
            'irpf' => 0,
            'orden' => 1,
            'mostrar_cantidad' => true,
            'mostrar_precio' => true,
        ]);

        $payload = [
            'empresa' => $base['empresa'],
            'cliente' => $base['cliente'],
            'divisa' => $base['divisa'],
            'formaPago' => $base['formaPago'],
            'pais' => $base['pais'],
            'lineas' => [$line],
            'lineasIva' => [],
        ];

        PedidoPrintView::setResolversForTests(
            static fn (int $id): \FSFramework\model\pedido_cliente|false => $id === 1 ? $document : false,
            static fn (): array => $payload,
        );
    }

    private function configurePresupuestoResolvers(): void
    {
        DocumentPrintViewFixture::requireModels();
        $base = DocumentPrintViewFixture::buildFacturaPayload();
        $document = new \FSFramework\model\presupuesto_cliente(
            DocumentPrintViewFixture::buildGenericDocumentRow('PRE-TEST-001', 500.0, 400.0),
        );
        $document->idpresupuesto = 1;

        $line = new \FSFramework\model\linea_presupuesto_cliente([
            'idlinea' => 1,
            'idpresupuesto' => 1,
            'referencia' => 'REF-1',
            'descripcion' => 'Linea 1',
            'cantidad' => 1,
            'pvpunitario' => 400,
            'pvpsindto' => 400,
            'dtopor' => 0,
            'dtopor2' => 0,
            'dtopor3' => 0,
            'dtopor4' => 0,
            'pvptotal' => 400,
            'codimpuesto' => 'IVA21',
            'codcombinacion' => null,
            'iva' => 21,
            'recargo' => 0,
            'irpf' => 0,
            'orden' => 1,
            'mostrar_cantidad' => true,
            'mostrar_precio' => true,
        ]);

        $payload = [
            'empresa' => $base['empresa'],
            'cliente' => $base['cliente'],
            'divisa' => $base['divisa'],
            'formaPago' => $base['formaPago'],
            'pais' => $base['pais'],
            'lineas' => [$line],
            'lineasIva' => [],
        ];

        PresupuestoPrintView::setResolversForTests(
            static fn (int $id): \FSFramework\model\presupuesto_cliente|false => $id === 1 ? $document : false,
            static fn (): array => $payload,
        );
    }

    private function createController(): FacturaPdf1Controller
    {
        $reflection = new \ReflectionClass(FacturaPdf1Controller::class);

        /** @var FacturaPdf1Controller $controller */
        return $reflection->newInstanceWithoutConstructor();
    }

    private function createLegacyUser(): object
    {
        return new class {
            public string $nick = 'admin';
            public string $email = 'admin@example.com';
            public bool $admin = true;

            public function have_access_to(string $page): bool
            {
                return true;
            }
        };
    }
}
