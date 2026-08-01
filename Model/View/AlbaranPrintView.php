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

final class AlbaranPrintView implements ClientDocumentPrintViewInterface
{
    use PrintViewFormattingTrait;

    /** @var (callable(int): (\FSFramework\model\albaran_cliente|false))|null */
    private static $documentResolver = null;

    /** @var (callable(\FSFramework\model\albaran_cliente): array<string, mixed>)|null */
    private static $relatedResolver = null;

    /** @var (callable(): ?string)|null */
    private static $formatoDocumentoTituloResolver = null;

    /**
     * @param list<object> $lineas
     * @param list<object> $lineasIva
     */
    private function __construct(
        private readonly \FSFramework\model\albaran_cliente $albaran,
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
        $albaran = self::resolveDocument($id);
        if ($albaran === false) {
            throw new PrintableDocumentNotFoundException($id);
        }

        return self::build($albaran);
    }

    public static function resetResolversForTests(): void
    {
        self::$documentResolver = null;
        self::$relatedResolver = null;
        self::$formatoDocumentoTituloResolver = null;
    }

    /** @param callable(int): (\FSFramework\model\albaran_cliente|false)|null $documentResolver */
    public static function setResolversForTests(?callable $documentResolver, ?callable $relatedResolver): void
    {
        self::$documentResolver = $documentResolver;
        self::$relatedResolver = $relatedResolver;
    }

    /** @param (callable(): ?string)|null $resolver */
    public static function setFormatoDocumentoResolverForTests(?callable $resolver): void
    {
        self::$formatoDocumentoTituloResolver = $resolver;
    }

    public function getDocument(): object
    {
        return $this->albaran;
    }

    public function getDocumentId(): int
    {
        return (int) $this->albaran->idalbaran;
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

    public function getLineas(): array
    {
        return $this->lineas;
    }

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
        return self::formatMoney($this->divisa, $this->locale, (float) $this->albaran->total);
    }

    public function getSubtotalFormatted(): string
    {
        return self::formatMoney($this->divisa, $this->locale, (float) $this->albaran->neto);
    }

    public function getTaxTotalsFormatted(): array
    {
        return self::formatTaxTotals($this->divisa, $this->locale, $this->lineasIva);
    }

    /** @return \FSFramework\model\albaran_cliente|false */
    private static function resolveDocument(int $id): \FSFramework\model\albaran_cliente|false
    {
        if (self::$documentResolver !== null) {
            return (self::$documentResolver)($id);
        }

        self::requireModel();

        return (new \FSFramework\model\albaran_cliente())->get((string) $id);
    }

    private static function build(\FSFramework\model\albaran_cliente $albaran): self
    {
        if (self::$relatedResolver !== null) {
            $related = (self::$relatedResolver)($albaran);

            return new self(
                $albaran,
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

        $related = RelatedModelsLoader::load($albaran);
        $lineasRaw = $albaran->get_lineas();
        $lineas = is_array($lineasRaw) ? array_values($lineasRaw) : [];
        $lineasIva = self::aggregateLineasIvaFromLineas($lineas);

        return new self(
            $albaran,
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

    private static function requireModel(): void
    {
        if (!class_exists(\FSFramework\model\albaran_cliente::class, false)) {
            require_once FS_FOLDER . '/plugins/clientes_facturacion/model/core/albaran_cliente.php';
        }
    }

    private function resolveDocumentTypeLabel(): string
    {
        $fallback = 'Albarán';

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
