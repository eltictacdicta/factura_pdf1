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

namespace FSFramework\Plugins\factura_pdf1\Model\Adapters;

use FSFramework\Plugins\factura_pdf1\Model\HasPrintView;
use FSFramework\Plugins\factura_pdf1\Model\PrintableDocumentInterface;
use FSFramework\Plugins\factura_pdf1\Model\View\ClientDocumentPrintViewInterface;
use FSFramework\Plugins\factura_pdf1\Services\LineDiscountFormatter;
use FSFramework\Plugins\factura_pdf1\Services\RelatedModelsLoader;

abstract class AbstractClienteDocumentAdapter implements PrintableDocumentInterface, HasPrintView
{
    /**
     * Per-adapter test seam: when set, all `getAlmacen`,
     * `getContactoEnvio`, `getCuentaBancaria`, `getAgenciaTransporte`
     * and `getRecibos` calls go through this loader instead of the
     * one passed to the constructor. The default is `null` (use the
     * per-adapter loader).
     *
     * @var RelatedModelsLoader|null
     */
    private static ?RelatedModelsLoader $sharedLoaderForTests = null;

    protected function __construct(
        private readonly ClientDocumentPrintViewInterface $view,
        private readonly ?RelatedModelsLoader $relatedModelsLoader = null,
    ) {
    }

    /**
     * Inject a custom `RelatedModelsLoader` that all adapters will
     * use for the duration of a test. Pass `null` to clear the
     * override and restore the per-adapter loader.
     */
    public static function setSharedRelatedModelsLoaderForTests(?RelatedModelsLoader $loader): void
    {
        self::$sharedLoaderForTests = $loader;
    }

    protected function getLoader(): ?RelatedModelsLoader
    {
        return self::$sharedLoaderForTests ?? $this->relatedModelsLoader;
    }

    public function getPrintView(): ClientDocumentPrintViewInterface
    {
        return $this->view;
    }

    public function getId(): int
    {
        return $this->view->getDocumentId();
    }

    /**
     * Per PR-2: pass-through to the print-view's `getDocument()`.
     * The renderer reads the source model's fields (codigo,
     * cifnif, codpostal, etc.) from this object directly.
     */
    public function getDocument(): object
    {
        return $this->view->getDocument();
    }

    /**
     * Per PR-2: pass-through to the print-view's `getEmpresa()`.
     */
    public function getEmpresa(): object
    {
        return $this->view->getEmpresa();
    }

    /**
     * Per PR-2: pass-through to the print-view's `getDivisa()`.
     */
    public function getDivisa(): object
    {
        return $this->view->getDivisa();
    }

    /**
     * Per PR-2: pass-through to the print-view's `getFormaPago()`.
     */
    public function getFormaPago(): object
    {
        return $this->view->getFormaPago();
    }

    /**
     * Per PR-2 spec H.4 (delta `invoice-pdf-adapters`):
     * returns the FQCN of the source model wrapped by the
     * adapter (e.g. `\\FSFramework\\model\\factura_cliente`).
     * The renderer uses this to drive per-model branching
     * that the upstream `BusinessDocument::modelClassName()`
     * provided.
     */
    public function getModelClassName(): string
    {
        return $this->view->getDocument()::class;
    }

    /**
     * Per PR-2 spec H.4: returns the source document's
     * `codigorect` (rectifying invoice original code), or
     * `null` when the source has no rectified counterpart.
     */
    public function getCodigoRect(): ?string
    {
        $doc = $this->view->getDocument();
        if (!property_exists($doc, 'codigorect')) {
            return null;
        }

        $value = trim((string) $doc->codigorect);

        return $value !== '' ? $value : null;
    }

    public function getCodigo(): string
    {
        $doc = $this->view->getDocument();

        return property_exists($doc, 'codigo') ? (string) $doc->codigo : '';
    }

    public function getFecha(): string
    {
        $doc = $this->view->getDocument();
        if (!property_exists($doc, 'fecha')) {
            return '';
        }

        $fecha = (string) $doc->fecha;
        if ($fecha === '') {
            return '';
        }

        $timestamp = strtotime($fecha);

        return $timestamp !== false ? date('Y-m-d', $timestamp) : $fecha;
    }

    public function getCliente(): object
    {
        return $this->view->getCliente();
    }

    public function getLineas(): array
    {
        $result = [];
        $cliente = $this->view->getCliente();
        foreach ($this->view->getLineas() as $linea) {
            $discounts = LineDiscountFormatter::resolveLineDiscounts($linea, $cliente);
            $cantidad = (float) ($linea->cantidad ?? 0);
            $pvpUnitario = (float) ($linea->pvpunitario ?? 0);
            $pvpsindto = (float) ($linea->pvpsindto ?? 0);
            if ($pvpsindto <= 0 && $cantidad > 0 && $pvpUnitario > 0) {
                $pvpsindto = $cantidad * $pvpUnitario;
            }

            $storedHadDiscounts = LineDiscountFormatter::lineHadStoredDiscounts($linea);
            $pvptotal = (float) ($linea->pvptotal ?? 0);
            if (
                !$storedHadDiscounts
                && LineDiscountFormatter::hasDiscount(
                    $discounts['dtopor'],
                    $discounts['dtopor2'],
                    $discounts['dtopor3'],
                    $discounts['dtopor4'],
                )
            ) {
                $pvptotal = LineDiscountFormatter::calcPvptotal(
                    $cantidad,
                    $pvpUnitario,
                    $discounts['dtopor'],
                    $discounts['dtopor2'],
                    $discounts['dtopor3'],
                    $discounts['dtopor4'],
                );
            }

            $result[] = [
                'codigo' => (string) ($linea->referencia ?? ''),
                'descripcion' => (string) ($linea->descripcion ?? ''),
                'cantidad' => $cantidad,
                'pvpunitario' => $pvpUnitario,
                'pvpsindto' => $pvpsindto,
                'dtopor' => $discounts['dtopor'],
                'dtopor2' => $discounts['dtopor2'],
                'dtopor3' => $discounts['dtopor3'],
                'dtopor4' => $discounts['dtopor4'],
                'pvptotal' => $pvptotal,
                'iva' => (float) ($linea->iva ?? 0),
                'recargo' => (float) ($linea->recargo ?? 0),
                'irpf' => (float) ($linea->irpf ?? 0),
                'total' => $pvptotal,
            ];
        }

        return $result;
    }

    /**
     * Per PR-2 spec H.4: alias of `getLineas()` for the
     * renderer. The renderer consumes the lines via `foreach`;
     * we keep the typed return as `iterable` so the renderer
     * can swap the backing store for a generator in the
     * future without breaking the contract.
     */
    public function getLines(): iterable
    {
        return $this->getLineas();
    }

    public function getTotales(): array
    {
        $doc = $this->view->getDocument();
        $totalsuplidos = (float) ($doc->totalsuplidos ?? 0);

        if ($this->shouldRecomputeDocumentTotals()) {
            $computed = LineDiscountFormatter::computeDocumentTotalsFromLines(
                $this->getLineas(),
                $totalsuplidos,
            );

            return [
                'neto' => $computed['neto'],
                'total' => $computed['total'],
                'totaliva' => $computed['totaliva'],
                'totalirpf' => $computed['totalirpf'],
                'totalrecargo' => $computed['totalrecargo'],
                'netosindto' => (float) ($doc->netosindto ?? 0),
                'dtopor1' => (float) ($doc->dtopor1 ?? 0),
                'dtopor2' => (float) ($doc->dtopor2 ?? 0),
                'totalsuplidos' => $totalsuplidos,
                'totales' => $computed['total'],
            ];
        }

        return [
            'neto' => (float) ($doc->neto ?? 0),
            'total' => (float) ($doc->total ?? 0),
            'totaliva' => (float) ($doc->totaliva ?? 0),
            'netosindto' => (float) ($doc->netosindto ?? 0),
            'dtopor1' => (float) ($doc->dtopor1 ?? 0),
            'dtopor2' => (float) ($doc->dtopor2 ?? 0),
            'totalirpf' => (float) ($doc->totalirpf ?? 0),
            'totalrecargo' => (float) ($doc->totalrecargo ?? 0),
            'totalsuplidos' => $totalsuplidos,
            'totales' => (float) ($doc->total ?? 0),
        ];
    }

    private function shouldRecomputeDocumentTotals(): bool
    {
        $cliente = $this->view->getCliente();
        foreach ($this->view->getLineas() as $linea) {
            if (LineDiscountFormatter::lineNeedsDiscountEnrichment($linea, $cliente)) {
                return true;
            }
        }

        return false;
    }

    public function getVencimiento(): ?string
    {
        $doc = $this->view->getDocument();
        if (!property_exists($doc, 'vencimiento')) {
            return null;
        }

        $vencimiento = (string) $doc->vencimiento;

        return $vencimiento !== '' ? $vencimiento : null;
    }

    public function getRelatedDocuments(): array
    {
        return [];
    }

    public function getIban(): ?string
    {
        return null;
    }

    public function getCarrier(): ?array
    {
        $doc = $this->view->getDocument();
        $codtrans = property_exists($doc, 'codtrans') ? (string) $doc->codtrans : '';
        $codigoenv = property_exists($doc, 'codigoenv') ? (string) $doc->codigoenv : '';

        if ($codtrans === '' && $codigoenv === '') {
            return null;
        }

        return [
            'codtrans' => $codtrans !== '' ? $codtrans : null,
            'codigoenv' => $codigoenv !== '' ? $codigoenv : null,
        ];
    }

    public function getPaymentBreakdown(): array
    {
        return [];
    }

    public function getObservaciones(): ?string
    {
        $doc = $this->view->getDocument();
        if (!property_exists($doc, 'observaciones')) {
            return null;
        }

        $obs = trim((string) $doc->observaciones);

        return $obs !== '' ? $obs : null;
    }

    /**
     * Per AD-11 + AD-12: the default implementation calls the
     * RelatedModelsLoader, which is null-safe against the underlying
     * `almacen` model. When the loader is null (the legacy code path
     * before the related-model join) the getter returns null. PR-2
     * wires the join via RelatedModelsLoader::loadAlmacen(); the
     * audit (obs #367) flagged 27 dead settings and feature #8 is
     * the live path.
     */
    public function getAlmacen(): ?object
    {
        $loader = $this->getLoader();
        if ($loader === null) {
            return null;
        }

        $doc = $this->view->getDocument();
        $codalmacen = property_exists($doc, 'codalmacen') ? (string) $doc->codalmacen : '';
        if ($codalmacen === '') {
            return null;
        }

        return $loader->loadAlmacen($codalmacen);
    }

    /**
     * Per AD-11 + AD-12: reads the source document's `idcontactoenv`
     * and resolves the contact via the RelatedModelsLoader. The
     * shipping-address block (feature #6) is gated by the
     * `ocultardireccionenvio` setting in the template.
     */
    public function getContactoEnvio(): ?object
    {
        $loader = $this->getLoader();
        if ($loader === null) {
            return null;
        }

        $doc = $this->view->getDocument();
        if (!property_exists($doc, 'idcontactoenv')) {
            return null;
        }

        $idcontactoenv = (int) $doc->idcontactoenv;
        if ($idcontactoenv <= 0) {
            return null;
        }

        return $loader->loadContactoEnvio($idcontactoenv);
    }

    /**
     * Per AD-11 + AD-12: resolves the IBAN to render in the payment
     * footer. The RelatedModelsLoader is null-safe against the
     * upstream `cuenta_banco*` models; the full cliente-vs-empresa
     * resolution (feature #4) lives in
     * RelatedModelsLoader::loadCuentaBancaria().
     */
    public function getCuentaBancaria(): string
    {
        $loader = $this->getLoader();
        if ($loader === null) {
            return '';
        }

        $doc = $this->view->getDocument();
        $codcliente = property_exists($doc, 'codcliente') ? (string) $doc->codcliente : '';
        $codcuenta = property_exists($this->view->getFormaPago(), 'codcuenta')
            ? (string) $this->view->getFormaPago()->codcuenta
            : '';

        return $loader->loadCuentaBancaria($codcliente, $codcuenta);
    }

    /**
     * Per AD-11 + AD-12: reads the source document's `codtrans` and
     * `codigoenv` and resolves the carrier via the
     * RelatedModelsLoader. The carrier block (feature #5) is
     * rendered from this getter.
     *
     * @return array{nombre: string, tracking: string}
     */
    public function getAgenciaTransporte(): array
    {
        $loader = $this->getLoader();
        if ($loader === null) {
            return ['nombre' => '', 'tracking' => ''];
        }

        $doc = $this->view->getDocument();
        $codtrans = property_exists($doc, 'codtrans') ? (string) $doc->codtrans : '';
        $codigoenv = property_exists($doc, 'codigoenv') ? (string) $doc->codigoenv : '';

        return $loader->loadAgenciaTransporte($codtrans, $codigoenv);
    }

    /**
     * Per AD-11 + AD-12: resolves the receipt list via the
     * RelatedModelsLoader. The receipt list (feature #3, mode 3 of
     * `pagoyvencimiento`) is rendered from this getter. The model
     * class string is the FQCN of the source document.
     *
     * @return list<object>
     */
    public function getRecibos(): array
    {
        $loader = $this->getLoader();
        if ($loader === null) {
            return [];
        }

        $doc = $this->view->getDocument();
        $modelClass = $doc::class;
        $id = $this->view->getDocumentId();

        return $loader->loadRecibos($modelClass, (string) $id);
    }
}
