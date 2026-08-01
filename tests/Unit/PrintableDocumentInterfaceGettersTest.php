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
 * You should have received a copy of the GNU Lesser Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace FacturaPdf1\Tests\Unit\Model;

use FacturaPdf1\Tests\Fixtures\SeedInvoiceFakt20260001;
use FSFramework\Plugins\factura_pdf1\Model\Adapters\AbstractClienteDocumentAdapter;
use FSFramework\Plugins\factura_pdf1\Model\View\FacturaPrintView;
use PHPUnit\Framework\TestCase;

/**
 * Per PR-2 spec H.4 (delta `invoice-pdf-adapters`): the
 * `PrintableDocumentInterface` exposes 5 new getters so the
 * renderer can read the model fields without going through the
 * FS2025 `BusinessDocument` contract. This test asserts each
 * getter's default behaviour on a seeded
 * `FacturaClienteAdapter` + a synthetic subclass that overrides
 * the getters (the "default + override" triangulation per
 * strict-tdd-apply).
 *
 * The concrete `FacturaClienteAdapter` is `final`, so the
 * override stubs in this file extend
 * `AbstractClienteDocumentAdapter` directly via a tiny
 * in-test private stub. The seeded adapter is used for the
 * "default" assertions; the in-test stubs for the "override"
 * assertions.
 */
final class PrintableDocumentInterfaceGettersTest extends TestCase
{
    protected function setUp(): void
    {
        FacturaPrintView::resetResolversForTests();
    }

    protected function tearDown(): void
    {
        FacturaPrintView::resetResolversForTests();
    }

    public function testGetModelClassNameReturnsSourceFqcn(): void
    {
        SeedInvoiceFakt20260001::configureResolvers();
        $adapter = SeedInvoiceFakt20260001::buildAdapter();

        $this->assertSame(
            \FSFramework\model\factura_cliente::class,
            $adapter->getModelClassName(),
        );
    }

    public function testGetModelClassNameOverrideReturnsLiteral(): void
    {
        $stub = new StubAdapter(overrideModelClassName: 'Tests\\Fixtures\\CustomModel');

        $this->assertSame('Tests\\Fixtures\\CustomModel', $stub->getModelClassName());
    }

    public function testGetCodigoRectReturnsNullWhenSourceHasNoRectifiedCode(): void
    {
        SeedInvoiceFakt20260001::configureResolvers();
        $adapter = SeedInvoiceFakt20260001::buildAdapter();

        $this->assertNull($adapter->getCodigoRect());
    }

    public function testGetCodigoRectReturnsLiteralWhenSourceHasRectifiedCode(): void
    {
        $stub = new StubAdapter(overrideCodigoRect: 'A/2026/0001-RECT');

        $this->assertSame('A/2026/0001-RECT', $stub->getCodigoRect());
    }

    public function testGetObservacionesReturnsNullForEmptyObservaciones(): void
    {
        SeedInvoiceFakt20260001::configureResolvers();
        $adapter = SeedInvoiceFakt20260001::buildAdapter();

        $this->assertNull($adapter->getObservaciones());
    }

    public function testGetObservacionesReturnsTrimmedValue(): void
    {
        $stub = new StubAdapter(overrideObservaciones: '  Cliente VIP  ');

        $this->assertSame('Cliente VIP', $stub->getObservaciones());
    }

    public function testGetLinesReturnsEmptyForDocumentWithoutLines(): void
    {
        $stub = new StubAdapter(overrideLines: []);

        $lines = [];
        foreach ($stub->getLines() as $line) {
            $lines[] = $line;
        }
        $this->assertSame([], $lines);
    }

    public function testGetLinesReturnsThreeLinesForSeededInvoice(): void
    {
        SeedInvoiceFakt20260001::configureResolvers();
        $adapter = SeedInvoiceFakt20260001::buildAdapter();

        $lines = [];
        foreach ($adapter->getLines() as $line) {
            $lines[] = $line;
        }

        $this->assertCount(3, $lines);
        $this->assertSame('ART-1', $lines[0]['codigo']);
        $this->assertSame(100.0, $lines[0]['pvpunitario']);
        $this->assertSame('Articulo demo 1', $lines[0]['descripcion']);
    }

    public function testGetIdReturnsSourcePrimaryKey(): void
    {
        SeedInvoiceFakt20260001::configureResolvers();
        $adapter = SeedInvoiceFakt20260001::buildAdapter();

        $this->assertSame(1, $adapter->getId());
    }

    public function testGetIdOverrideReturnsLiteral(): void
    {
        $stub = new StubAdapter(overrideId: 42);

        $this->assertSame(42, $stub->getId());
    }
}

/**
 * Test-only adapter that extends the abstract adapter so we
 * can override individual getters without touching the final
 * `FacturaClienteAdapter` / `AlbaranClienteAdapter` etc.
 */
final class StubAdapter extends AbstractClienteDocumentAdapter
{
    public function __construct(
        private readonly ?string $overrideModelClassName = null,
        private readonly ?string $overrideCodigoRect = null,
        private readonly ?string $overrideObservaciones = null,
        private readonly array $overrideLines = [],
        private readonly ?int $overrideId = null,
    ) {
        parent::__construct(
            new \FacturaPdf1\Tests\Fixtures\StubView(),
            null,
        );
    }

    public function getModelClassName(): string
    {
        return $this->overrideModelClassName ?? parent::getModelClassName();
    }

    public function getCodigoRect(): ?string
    {
        return $this->overrideCodigoRect ?? parent::getCodigoRect();
    }

    public function getObservaciones(): ?string
    {
        if ($this->overrideObservaciones !== null) {
            $trimmed = trim($this->overrideObservaciones);

            return $trimmed !== '' ? $trimmed : null;
        }

        return parent::getObservaciones();
    }

    public function getLines(): iterable
    {
        if ($this->overrideLines !== []) {
            return $this->overrideLines;
        }

        return parent::getLines();
    }

    public function getId(): int
    {
        return $this->overrideId ?? parent::getId();
    }
}
