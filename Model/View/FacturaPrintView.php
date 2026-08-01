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

namespace FSFramework\Plugins\factura_pdf1\Model\View;

use FSFramework\Plugins\factura_pdf1\Model\Exception\PrintableDocumentNotFoundException;

/**
 * Read-only view model for rendering a customer invoice PDF.
 *
 * @phpstan-type RelatedPayload array{
 *     empresa: \empresa,
 *     cliente: \FSFramework\model\cliente,
 *     divisa: \divisa,
 *     formaPago: \forma_pago,
 *     pais: \pais,
 *     lineas: list<\FSFramework\model\linea_factura_cliente>,
 *     lineasIva: list<\FSFramework\model\linea_iva_factura_cliente>
 * }
 */
final class FacturaPrintView implements ClientDocumentPrintViewInterface
{
    use PrintViewFormattingTrait;

    /** @var (callable(int): (\FSFramework\model\factura_cliente|false))|null */
    private static $facturaResolver = null;

    /** @var (callable(\FSFramework\model\factura_cliente): RelatedPayload)|null */
    private static $relatedResolver = null;

    /**
     * Per PR-2 feature 17: a test seam that returns the formato_documento
     * `titulo` for this document, or `null` when the formato is missing
     * and the literal fallback applies. Production code would resolve
     * the formato row from `idformato` (a column that upstream models
     * expose; the local `factura_cliente` does not, hence the seam).
     * The unit test injects a stub titulo without a DB.
     *
     * @var (callable(): ?string)|null
     */
    private static $formatoDocumentoTituloResolver = null;

    /**
     * @param list<\FSFramework\model\linea_factura_cliente> $lineas
     * @param list<\FSFramework\model\linea_iva_factura_cliente> $lineasIva
     */
    private function __construct(
        private readonly \FSFramework\model\factura_cliente $factura,
        private readonly \empresa $empresa,
        private readonly \FSFramework\model\cliente $cliente,
        private readonly \divisa $divisa,
        private readonly \forma_pago $formaPago,
        private readonly \pais $pais,
        private readonly array $lineas,
        private readonly array $lineasIva,
        private readonly string $locale,
    ) {
    }

    public static function fromId(int $id): self
    {
        $factura = self::resolveFactura($id);
        if ($factura === false) {
            throw new PrintableDocumentNotFoundException($id);
        }

        return self::build($factura);
    }

    public static function resetResolversForTests(): void
    {
        self::$facturaResolver = null;
        self::$relatedResolver = null;
        self::$formatoDocumentoTituloResolver = null;
    }

    /**
     * @param callable(int): (\FSFramework\model\factura_cliente|false)|null $facturaResolver
     * @param callable(\FSFramework\model\factura_cliente): RelatedPayload|null $relatedResolver
     */
    public static function setResolversForTests(?callable $facturaResolver, ?callable $relatedResolver): void
    {
        self::$facturaResolver = $facturaResolver;
        self::$relatedResolver = $relatedResolver;
    }

    /**
     * Per PR-2 feature 17: inject a callable that returns the
     * `formato_documento->titulo` for the current document, or `null`
     * when the formato is missing (literal fallback applies).
     *
     * @param (callable(): ?string)|null $resolver
     */
    public static function setFormatoDocumentoResolverForTests(?callable $resolver): void
    {
        self::$formatoDocumentoTituloResolver = $resolver;
    }

    public function getDocument(): object
    {
        return $this->factura;
    }

    public function getFactura(): \FSFramework\model\factura_cliente
    {
        return $this->factura;
    }

    public function getDocumentId(): int
    {
        return (int) $this->factura->idfactura;
    }

    public function getDocumentTypeLabel(): string
    {
        return $this->resolveDocumentTypeLabel();
    }

    public function getEmpresa(): object
    {
        return $this->empresa;
    }

    public function getCliente(): object
    {
        return $this->cliente;
    }

    /** @return list<\FSFramework\model\linea_factura_cliente> */
    public function getLineas(): array
    {
        return $this->lineas;
    }

    /** @return list<\FSFramework\model\linea_iva_factura_cliente> */
    public function getLineasIva(): array
    {
        return $this->lineasIva;
    }

    public function getDivisa(): object
    {
        return $this->divisa;
    }

    public function getFormaPago(): object
    {
        return $this->formaPago;
    }

    public function getPais(): object
    {
        return $this->pais;
    }

    public function getTotalFormatted(): string
    {
        return self::formatMoney($this->divisa, $this->locale, (float) $this->factura->total);
    }

    public function getSubtotalFormatted(): string
    {
        return self::formatMoney($this->divisa, $this->locale, (float) $this->factura->neto);
    }

    public function getTaxTotalsFormatted(): array
    {
        return self::formatTaxTotals($this->divisa, $this->locale, $this->lineasIva);
    }

    /** @return \FSFramework\model\factura_cliente|false */
    private static function resolveFactura(int $id): \FSFramework\model\factura_cliente|false
    {
        if (self::$facturaResolver !== null) {
            return (self::$facturaResolver)($id);
        }

        self::requireFacturaModel();

        return (new \FSFramework\model\factura_cliente())->get((string) $id);
    }

    private static function build(\FSFramework\model\factura_cliente $factura): self
    {
        if (self::$relatedResolver !== null) {
            $related = (self::$relatedResolver)($factura);

            return new self(
                $factura,
                $related['empresa'],
                $related['cliente'],
                $related['divisa'],
                $related['formaPago'],
                $related['pais'],
                $related['lineas'],
                $related['lineasIva'],
                self::resolveLocale($related['empresa']),
            );
        }

        $related = RelatedModelsLoader::load($factura);

        /** @var mixed $lineasRaw */
        $lineasRaw = $factura->get_lineas();
        /** @var list<\FSFramework\model\linea_factura_cliente> $lineas */
        $lineas = is_array($lineasRaw) ? array_values($lineasRaw) : [];

        /** @var mixed $lineasIvaRaw */
        $lineasIvaRaw = $factura->get_lineas_iva();
        /** @var list<\FSFramework\model\linea_iva_factura_cliente> $lineasIva */
        $lineasIva = is_array($lineasIvaRaw) ? array_values($lineasIvaRaw) : [];

        return new self(
            $factura,
            $related['empresa'],
            $related['cliente'],
            $related['divisa'],
            $related['formaPago'],
            $related['pais'],
            $lineas,
            $lineasIva,
            self::resolveLocale($related['empresa']),
        );
    }

    private static function requireFacturaModel(): void
    {
        if (!class_exists(\FSFramework\model\factura_cliente::class, false)) {
            require_once FS_FOLDER . '/plugins/clientes_facturacion/model/core/factura_cliente.php';
        }
    }

    /**
     * Per PR-2 feature 17: read `formato_documento->titulo` first,
     * falling back to the hard-coded literal 'Factura' when the formato
     * row is missing. The formato lookup is gated on the static
     * resolver seam: when no resolver is installed (the production
     * path with no formato associated), the literal is returned. This
     * keeps the public-endpoint test (no formato) green without
     * requiring a `formato_documento` table in the test environment.
     */
    private function resolveDocumentTypeLabel(): string
    {
        $fallback = 'Factura';

        if (self::$formatoDocumentoTituloResolver === null) {
            return $fallback;
        }

        $titulo = (self::$formatoDocumentoTituloResolver)();
        if ($titulo === null) {
            return $fallback;
        }

        $trimmed = trim($titulo);

        return $trimmed !== '' ? $trimmed : $fallback;
    }
}
