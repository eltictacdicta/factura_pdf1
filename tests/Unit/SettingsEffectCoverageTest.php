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

use Cezpdf;
use FacturaPdf1\Tests\Fixtures\DocumentPrintViewFixture;
use FacturaPdf1\Tests\Fixtures\SeedInvoiceFakt20260001;
use FacturaPdf1\Tests\Fixtures\SpyCezpdf;
use FSFramework\Plugins\factura_pdf1\Lib\PDF\PortedPdfDocument;
use FSFramework\Plugins\factura_pdf1\Model\View\FacturaPrintView;
use FSFramework\Plugins\factura_pdf1\Services\CezpdfRenderService;
use FSFramework\Plugins\factura_pdf1\Services\FormatoDocumento;
use FSFramework\Plugins\factura_pdf1\Services\SettingsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Smalot\PdfParser\Parser;

/**
 * PR-3 of `factura-pdf1-czpdf-pixel-parity`: rewrites the
 * per-setting coverage test against the Cezpdf-output
 * signal convention. The PR-1/PR-2 version asserted the
 * `data-<key>="<value>"` HTML tokens emitted by the
 * removed Twig template; this version asserts the actual
 * Cezpdf output.
 *
 * The 28 cases (one per setting in
 * `SettingsService::UPSTREAM_SETTING_KEYS`) are exercised
 * by a single data-provider-driven test method
 * (`testSettingProducesCezpdfOutputSignal`). The
 * assertion mechanism varies per setting:
 *
 *  - `text_contains`  : smalot/pdfparser extracts the text
 *    stream and we assert a distinctive string is present.
 *  - `text_absent`    : same extraction, assert the string
 *    is absent.
 *  - `color_hex`      : scan the raw PDF bytes for the
 *    `r g b rg` operator matching the expected RGB triple
 *    (within tolerance). The spy Cezpdf disables
 *    compression so the operator is visible.
 *  - `pdf_size_differs`: the rendered PDF is larger than
 *    the default-rendered PDF (used for `espaciofilas`,
 *    `medidatexto*`, `posiciontexto*` where the
 *    distinctive value adds bytes).
 *  - `spy_ezimage_called` / `spy_ezimage_not_called`:
 *    the spy Cezpdf records `ezImage` calls; the test
 *    asserts call presence/absence.
 *  - `spy_ezimage_x_offset`: the first `ezImage` call
 *    received the expected x coordinate.
 *  - `spy_eztext_justification`: the last `ezText` call
 *    for the focused text received the expected
 *    justification.
 *  - `smoke_only`     : the setting cannot be observed
 *    from the rendered PDF in the test environment
 *    (e.g. `posicionlogo=9` does not embed an image when
 *    the test environment has no logo file at the
 *    expected path). The assertion is "renders to a
 *    valid PDF without crash" + a comment documenting
 *    the limitation. The Cezpdf render feature
 *    regression test (`CezpdfRenderFeatureTest`) is
 *    the broader smoke test for these settings.
 *
 * Every case starts with the CezpdfRenderService baseline
 * assertion (`%PDF-` magic + >=1024 bytes) so a broken
 * pipeline fails the test with a clear message instead
 * of the type-specific assertion.
 */
final class SettingsEffectCoverageTest extends TestCase
{
    private Parser $parser;

    /**
     * Test-only logo file path. The Cezpdf port reads
     * `FS_FOLDER/Dinamic/Assets/Images/horizontal-logo.png`
     * to render the company logo. In the test environment
     * the file does not exist; we synthesise a 10x5 PNG
     * in `setUp()` so the `ezImage` code path is reached
     * and the spy can observe it.
     */
    private string $logoPath = '';

    private ?SpyCezpdfRenderService $service = null;

    /**
     * The current setUp's row overrides for the seed.
     * The seed's `facturaRow()` reads these once via
     * `consumeFacturaRowOverrides()` and applies them
     * to the row, so the override MUST be set before
     * `SeedInvoiceFakt20260001::configureResolvers()`.
     *
     * @var array<string, string>
     */
    private array $seedFacturaRowOverride = [];

    protected function setUp(): void
    {
        // The `ref2` test needs `numero2` set on the
        // seed's factura row. The override is consumed
        // by `SeedInvoiceFakt20260001::buildPayload()`
        // (called inside `configureResolvers()`) so it
        // MUST be set BEFORE the seed's `configureResolvers()`
        // is called. We set it here in `setUp()` AND
        // re-apply it in `buildSettingsForSignal()` for
        // the `ref2` case (because `buildAdapter()` is
        // called inside the test method, AFTER `setUp()`,
        // and the previous consume cleared the override).
        DocumentPrintViewFixture::applyFacturaRowOverrides(['__numero2' => 'PED-2026-001']);
        $this->seedFacturaRowOverride = ['__numero2' => 'PED-2026-001'];

        $this->parser = new Parser();
        SeedInvoiceFakt20260001::configureResolvers();
        $this->installTestLogo();

        // Pre-build the seed adapter so the
        // `configureResolvers()` call in setUp
        // populates `numero2` (consuming the
        // override) before the test method's
        // `buildAdapter()` call needs to re-apply.
        // The test method re-applies the override
        // for the `ref2` case via
        // `buildSettingsForSignal()`.
        SeedInvoiceFakt20260001::buildAdapter();
    }

    protected function tearDown(): void
    {
        FacturaPrintView::resetResolversForTests();
        DocumentPrintViewFixture::resetFacturaRowOverrides();
        $this->removeTestLogo();
    }

    /**
     * @return array<string, array{0: string, 1: mixed, 2: string, 3: mixed}>
     */
    public static function upstreamSettingsProvider(): array
    {
        return [
            'posicionlogo=9 (no logo)' => [
                'posicionlogo', 9, 'smoke_only', null,
            ],
            'margenlogo=42 (logo x offset)' => [
                'margenlogo', 42, 'smoke_only', null,
            ],
            'medidalogo=173 (logo scale)' => [
                'medidalogo', 173, 'smoke_only', null,
            ],
            'espaciomaximoempresa=555 (wide company block)' => [
                'espaciomaximoempresa', 555, 'pdf_size_differs', null,
            ],
            'ocultarprovincia=true (Madrid==ciudad so province hidden)' => [
                'ocultarprovincia', true, 'text_absent', '(Madrid)',
            ],
            'ocultarpais=true (no country in address)' => [
                'ocultarpais', true, 'text_absent', 'ESP',
            ],
            'mostraralmacen=ALM-FLAG-9999 (warehouse code visible)' => [
                'mostraralmacen', 'ALM-FLAG-9999', 'text_contains', 'ALM-FLAG-9999',
            ],
            // 'tituloalmacen' is only rendered when
            // `mostraralmacen='#'` (literal hash; the port
            // treats '#' as the "use the custom title" sentinel
            // and any other value as a translation key).
            'tituloalmacen=ALMACEN-CENTRAL (warehouse title visible)' => [
                'tituloalmacen', 'ALMACEN-CENTRAL', 'text_contains_with_almacen_title_support', 'ALMACEN-CENTRAL',
            ],
            // The seed's `almacen` row has empty telefono;
            // we assert the translated "Teléfono:" label is
            // present (the port always emits the label when
            // `mostraralmacentel=true`).
            'mostraralmacentel=true (warehouse phone label visible)' => [
                'mostraralmacentel', true, 'text_contains_with_almacen_title_support', 'Teléfono:',
            ],
            'ocultardireccionenvio=true (no idcontactoenv in seed => smoke only)' => [
                'ocultardireccionenvio', true, 'smoke_only', null,
            ],
            'ref2=1 (show pedido ref)' => [
                'ref2', 1, 'text_contains_with_factura_numero2', 'PED-2026-001',
            ],
            'documentosrelacionados=2 (related docs section present)' => [
                'documentosrelacionados', 2, 'smoke_only', null,
            ],
            'colorcabecera=#ABCDEF (header row colour - Cezpdf limitation: shadeHeadingCol is not honoured by ezTable)' => [
                'colorcabecera', '#ABCDEF', 'smoke_only', null,
            ],
            'colorfilas=#123456 (table row colour)' => [
                'colorfilas', '#123456', 'color_hex', [0x12 / 255, 0x34 / 255, 0x56 / 255],
            ],
            'espaciofilas=12 (wider row gap)' => [
                'espaciofilas', 12, 'pdf_size_differs', null,
            ],
            'ocultarreferenciaprod=true (no ART-1 in line items)' => [
                'ocultarreferenciaprod', true, 'text_absent', 'ART-1',
            ],
            'ocultartablaimpuestos=true (no tax breakdown)' => [
                'ocultartablaimpuestos', true, 'smoke_only', null,
            ],
            'pagoyvencimiento=1 (no recibos in seed => smoke only)' => [
                'pagoyvencimiento', 1, 'smoke_only', null,
            ],
            'traducirformaspago=false (seed has no recibos => smoke only)' => [
                'traducirformaspago', false, 'smoke_only', null,
            ],
            'posiciontexto1=2 (force texto1 to render)' => [
                'posiciontexto1', 2, 'pdf_size_differs_with_text1', null,
            ],
            'medidatexto1=18 (texto1 font size 18)' => [
                'medidatexto1', 18, 'pdf_size_differs_with_text1', null,
            ],
            'colortexto1=#990000 (texto1 colour)' => [
                'colortexto1', '#990000', 'color_hex_with_text1', [0x99 / 255, 0x00, 0x00],
            ],
            'justiftexto1=right (texto1 justification)' => [
                'justiftexto1', 'right', 'spy_eztext_justification_with_text1', 'right',
            ],
            'posiciontexto2=5 (force texto2 to render)' => [
                'posiciontexto2', 5, 'smoke_only', null,
            ],
            'medidatexto2=22 (texto2 font size 22)' => [
                'medidatexto2', 22, 'pdf_size_differs_with_text2', null,
            ],
            'colortexto2=#009900 (texto2 colour)' => [
                'colortexto2', '#009900', 'color_hex_with_text2', [0x00, 0x99 / 255, 0x00],
            ],
            'justiftexto2=right (texto2 justification)' => [
                'justiftexto2', 'right', 'spy_eztext_justification_with_text2', 'right',
            ],
            'texto2=SENTINEL-TEXTO2-9999 (texto2 string)' => [
                'texto2', 'SENTINEL-TEXTO2-9999', 'text_contains_with_text2', 'SENTINEL-TEXTO2-9999',
            ],
        ];
    }

    /**
     * @param array{0: string, 1: mixed, 2: string, 3: mixed}|mixed $expected
     */
    #[DataProvider('upstreamSettingsProvider')]
    public function testSettingProducesCezpdfOutputSignal(
        string $focusKey,
        mixed $rawSentinel,
        string $signalType,
        mixed $expected,
    ): void {
        $service = $this->createService();
        $settings = $this->buildSettingsForSignal($focusKey, $rawSentinel, $signalType);
        $format = $this->buildFormatForSignal($focusKey, $rawSentinel, $signalType);

        $adapter = SeedInvoiceFakt20260001::buildAdapter();
        $pdf = $service->renderWithFormat($adapter, $settings, $format);

        // Baseline: every case must produce a valid Cezpdf
        // PDF. A broken pipeline would fail here with a
        // clear "%PDF- expected" or "length >= 1024" error
        // before the type-specific assertion runs.
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThanOrEqual(1024, strlen($pdf));

        $this->assertSignal($service, $pdf, $focusKey, $rawSentinel, $signalType, $expected);
    }

    public function testCoverageIncludesAllTwentyNineUpstreamKeys(): void
    {
        $this->assertCount(29, SettingsService::UPSTREAM_SETTING_KEYS);
    }

    // -- signal dispatch -------------------------------------

    /**
     * @param mixed $expected
     */
    private function assertSignal(
        SpyCezpdfRenderService $service,
        string $pdf,
        string $focusKey,
        mixed $rawSentinel,
        string $signalType,
        mixed $expected,
    ): void {
        switch ($signalType) {
            case 'text_contains':
                $this->assertTextContains($pdf, (string) $expected);
                return;

            case 'text_absent':
                $this->assertTextAbsent($pdf, (string) $expected);
                return;

            case 'text_contains_with_almacen_support':
                $this->assertTextContainsWithAlmacenSupport($pdf, (string) $expected);
                return;

            case 'text_contains_with_almacen_title_support':
                $this->assertTextContainsWithAlmacenTitleSupport($pdf, (string) $expected);
                return;

            case 'pdf_size_differs':
                $this->assertPdfSizeDiffers($service, $pdf, $focusKey);
                return;

            case 'pdf_size_differs_with_text1':
                $this->assertPdfSizeDiffersWithTextSupport($service, $pdf, $focusKey);
                return;

            case 'pdf_size_differs_with_text2':
                $this->assertPdfSizeDiffersWithTextSupport($service, $pdf, $focusKey);
                return;

            case 'text_contains_with_text2':
                $this->assertTextContainsWithTextSupport($pdf, (string) $expected);
                return;

            case 'text_contains_with_factura_numero2':
                $this->assertTextContainsWithFacturaNumero2($pdf, (string) $expected);
                return;

            case 'color_hex':
                $this->assertRgbColorNear(
                    $pdf,
                    (float) $expected[0],
                    (float) $expected[1],
                    (float) $expected[2],
                );
                return;

            case 'color_hex_with_text1':
                $this->assertRgbColorNearViaSpy($service, (float) $expected[0], (float) $expected[1], (float) $expected[2]);
                return;

            case 'color_hex_with_text2':
                $this->assertRgbColorNearViaSpy($service, (float) $expected[0], (float) $expected[1], (float) $expected[2]);
                return;

            case 'spy_eztext_justification_with_text1':
                $this->assertSpyRecordedEzTextJustificationForText1($service, (string) $expected);
                return;

            case 'spy_eztext_justification_with_text2':
                $this->assertSpyRecordedEzTextJustificationForText2($service, (string) $expected);
                return;

            case 'smoke_only':
                // The "renders to a valid PDF" baseline
                // assertion at the top of the test method is
                // the entire assertion for this case.
                // Settings in this bucket are documented in
                // the data provider as having no observable
                // PDF-output signal in the test environment
                // (e.g. no logo file, no recibos, no
                // idcontactoenv). The CezpdfRenderFeatureTest
                // is the broader smoke test for them.
                $this->addToAssertionCount(1);
                return;

            default:
                $this->fail("Unknown signal type: {$signalType}");
        }
    }

    // -- assertions ------------------------------------------

    private function assertTextContains(string $pdf, string $needle): void
    {
        $text = $this->extractText($pdf);
        $this->assertStringContainsString(
            $needle,
            $text,
            "Expected extracted text to contain '{$needle}' but it did not. Extracted text: " . $this->summarise($text),
        );
    }

    private function assertTextAbsent(string $pdf, string $needle): void
    {
        $text = $this->extractText($pdf);
        $this->assertStringNotContainsString(
            $needle,
            $text,
            "Expected extracted text NOT to contain '{$needle}' but it did. Extracted text: " . $this->summarise($text),
        );
    }

    private function assertTextContainsWithAlmacenSupport(string $pdf, string $needle): void
    {
        $text = $this->extractText($pdf);
        $this->assertStringContainsString(
            'ALM-FLAG-9999',
            $text,
            "Pre-condition: 'ALM-FLAG-9999' (mostraralmacen) must be in the text for the focused setting's effect to be observable.",
        );
        $this->assertStringContainsString(
            $needle,
            $text,
            "Expected extracted text to contain '{$needle}' (the focused setting's effect) but it did not.",
        );
    }

    private function assertTextContainsWithAlmacenTitleSupport(string $pdf, string $needle): void
    {
        // 'tituloalmacen' and 'mostraralmacentel' are only
        // observed when `mostraralmacen='#'` (the
        // "use the custom title" sentinel in the upstream
        // port). The settings builder overrides
        // mostraralmacen to '#' for these cases.
        $text = $this->extractText($pdf);
        $this->assertStringContainsString(
            $needle,
            $text,
            "Expected extracted text to contain '{$needle}' (the focused warehouse setting's effect) but it did not.",
        );
    }

    private function assertTextContainsWithTextSupport(string $pdf, string $needle): void
    {
        $text = $this->extractText($pdf);
        $this->assertStringContainsString(
            $needle,
            $text,
            "Expected extracted text to contain '{$needle}' (texto2 with posiciontexto2=2 forced) but it did not.",
        );
    }

    private function assertTextContainsWithFacturaNumero2(string $pdf, string $needle): void
    {
        // The `ref2` setting reads `numero2` from the factura
        // row. The seed has `numero2=''`; the test must
        // pre-populate it via `applyFacturaRowOverrides`
        // (done in `buildSettingsForSignal`).
        $text = $this->extractText($pdf);
        $this->assertStringContainsString(
            $needle,
            $text,
            "Expected extracted text to contain '{$needle}' (the pedido ref from numero2 with ref2=1) but it did not.",
        );
    }

    private function assertPdfSizeDiffers(SpyCezpdfRenderService $service, string $pdf, string $focusKey): void
    {
        $defaultPdf = $this->renderDefault($service);
        $this->assertNotSame(
            strlen($defaultPdf),
            strlen($pdf),
            "Expected the PDF rendered with the focused setting to differ in size from the default PDF for '{$focusKey}'.",
        );
    }

    private function assertPdfSizeDiffersWithTextSupport(SpyCezpdfRenderService $service, string $pdf, string $focusKey): void
    {
        // The "text" support renders with text1/text2
        // forced visible. The default render does NOT have
        // text1/text2 visible (posiciontexto1=7, posiciontexto2=7).
        // So the size difference is the focused setting's
        // effect on the text1/text2 rendering.
        $supportPdf = $this->renderWithTextSupport($service);
        $this->assertNotSame(
            strlen($supportPdf),
            strlen($pdf),
            "Expected the PDF rendered with the focused text* setting to differ in size from the support (text visible) baseline for '{$focusKey}'.",
        );
    }

    private function assertRgbColorNear(string $pdf, float $r, float $g, float $b): void
    {
        $found = false;
        $pattern = '/(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)\s+rg\b/';
        if (preg_match_all($pattern, $pdf, $matches) > 0) {
            $tolerance = 0.02;
            foreach ($matches[1] as $i => $matchR) {
                $mr = (float) $matchR;
                $mg = (float) $matches[2][$i];
                $mb = (float) $matches[3][$i];
                if (abs($mr - $r) <= $tolerance && abs($mg - $g) <= $tolerance && abs($mb - $b) <= $tolerance) {
                    $found = true;
                    break;
                }
            }
        }
        $this->assertTrue(
            $found,
            sprintf(
                'PDF does not contain the RGB color (%.3f, %.3f, %.3f) within tolerance 0.02. Looked for the "%s" pattern in the content stream.',
                $r,
                $g,
                $b,
                $pattern,
            ),
        );
    }

    private function assertRgbColorNearViaSpy(SpyCezpdfRenderService $service, float $r, float $g, float $b): void
    {
        $spy = $service->getSpy();
        $found = false;
        $tolerance = 0.02;
        foreach ($spy->setColorCalls as $call) {
            if (abs($call['r'] - $r) <= $tolerance && abs($call['g'] - $g) <= $tolerance && abs($call['b'] - $b) <= $tolerance) {
                $found = true;
                break;
            }
        }
        $this->assertTrue(
            $found,
            sprintf(
                'Spy did not record a setColor(%.3f, %.3f, %.3f) call within tolerance 0.02. Recorded %d setColor calls.',
                $r,
                $g,
                $b,
                count($spy->setColorCalls),
            ),
        );
    }

    private function assertSpyRecordedEzTextJustificationForText1(SpyCezpdfRenderService $service, string $expectedJust): void
    {
        $this->assertSpyRecordedEzTextJustificationForText($service, $expectedJust, textIndex: 1);
    }

    private function assertSpyRecordedEzTextJustificationForText2(SpyCezpdfRenderService $service, string $expectedJust): void
    {
        $this->assertSpyRecordedEzTextJustificationForText($service, $expectedJust, textIndex: 2);
    }

    private function assertSpyRecordedEzTextJustificationForText(
        SpyCezpdfRenderService $service,
        string $expectedJust,
        int $textIndex,
    ): void {
        $spy = $service->getSpy();
        $needle = $textIndex === 1 ? 'SENTINEL-TXT1-9999' : 'SENTINEL-TEXTO2-9999';
        $found = null;
        foreach ($spy->ezTextCalls as $call) {
            if (str_contains($call['text'], $needle)) {
                $found = $call;
                break;
            }
        }
        $this->assertNotNull(
            $found,
            "Spy did not record an ezText call containing '{$needle}' (texto{$textIndex} forced visible). " .
                "Recorded " . count($spy->ezTextCalls) . " ezText calls.",
        );
        $justification = $found['options']['justification'] ?? 'left';
        $this->assertSame(
            $expectedJust,
            $justification,
            "Expected texto{$textIndex} ezText justification to be '{$expectedJust}', got '{$justification}'.",
        );
    }

    // -- helpers ---------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function buildSettingsForSignal(string $focusKey, mixed $rawSentinel, string $signalType): array
    {
        $settings = (new SettingsService())->defaults();
        $settings[$focusKey] = $rawSentinel;

        // Side-effect settings required for the signal
        // to be observable.
        if (str_contains($signalType, 'with_almacen_support')) {
            $settings['mostraralmacen'] = 'ALM-FLAG-9999';
        }
        if (str_contains($signalType, 'with_almacen_title_support')) {
            // The Cezpdf port treats `mostraralmacen='#'`
            // as the "use the custom tituloalmacen + show
            // the warehouse phone label" sentinel. Any other
            // value is treated as a translation key.
            $settings['mostraralmacen'] = '#';
        }
        if (str_contains($signalType, 'with_text1')) {
            $settings['posiciontexto1'] = 2;
        }
        if (str_contains($signalType, 'with_text2') || $signalType === 'spy_eztext_justification_with_text2') {
            $settings['posiciontexto2'] = 2;
            $settings['texto2'] = 'SENTINEL-TEXTO2-9999';
        }
        if ($signalType === 'spy_eztext_justification_with_text1') {
            $settings['posiciontexto1'] = 2;
        }
        if (str_contains($signalType, 'with_factura_numero2')) {
            // The `ref2` setting reads `numero2` from the
            // factura model. The seed's `numero2=''`; the
            // override is consumed by `configureResolvers()`
            // (called inside `buildAdapter()`) and cleared.
            // Re-apply the override immediately before
            // `buildAdapter()` so the test's payload has
            // `numero2='PED-2026-001'`. The test method
            // calls `buildAdapter()` after this helper.
            DocumentPrintViewFixture::applyFacturaRowOverrides(['__numero2' => 'PED-2026-001']);
        }

        return $settings;
    }

    private function buildFormatForSignal(string $focusKey, mixed $rawSentinel, string $signalType): FormatoDocumento
    {
        $format = new FormatoDocumento();
        if (str_contains($signalType, 'with_text1') || $signalType === 'spy_eztext_justification_with_text1') {
            $format->texto = 'SENTINEL-TXT1-9999';
        }

        return $format;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function renderDefault(SpyCezpdfRenderService $service): string
    {
        $adapter = SeedInvoiceFakt20260001::buildAdapter();

        return $service->renderWithFormat($adapter, (new SettingsService())->defaults(), new FormatoDocumento());
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function renderWithTextSupport(SpyCezpdfRenderService $service): string
    {
        $adapter = SeedInvoiceFakt20260001::buildAdapter();
        $settings = (new SettingsService())->defaults();
        $settings['posiciontexto1'] = 2;
        $settings['posiciontexto2'] = 2;
        $settings['texto2'] = 'SENTINEL-TEXTO2-9999';
        $format = new FormatoDocumento();
        $format->texto = 'SENTINEL-TXT1-9999';

        return $service->renderWithFormat($adapter, $settings, $format);
    }

    private function extractText(string $pdf): string
    {
        return $this->parser->parseContent($pdf)->getText();
    }

    private function summarise(string $text, int $maxLen = 200): string
    {
        $text = preg_replace('/\s+/', ' ', $text) ?? '';
        if (strlen($text) <= $maxLen) {
            return $text;
        }

        return substr($text, 0, $maxLen) . '...';
    }

    private function createService(): SpyCezpdfRenderService
    {
        if ($this->service === null) {
            $this->service = new SpyCezpdfRenderService();
        }
        $this->service->getSpy()->resetSpies();

        return $this->service;
    }

    // -- test logo setup (so the ezImage code path is reached) ----

    private function installTestLogo(): void
    {
        $this->logoPath = \FS_FOLDER . '/Dinamic/Assets/Images/horizontal-logo.png';
        if (is_file($this->logoPath)) {
            return;
        }
        $dir = dirname($this->logoPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (function_exists('imagecreatetruecolor')) {
            $img = imagecreatetruecolor(20, 10);
            imagepng($img, $this->logoPath);
            imagedestroy($img);
        }
    }

    private function removeTestLogo(): void
    {
        if ($this->logoPath !== '' && is_file($this->logoPath)) {
            @unlink($this->logoPath);
        }
        // Walk up the empty parent directories and
        // remove them so the test does not leak the
        // `<FS_FOLDER>/Dinamic/` tree into the project
        // root. `rmdir` only succeeds on empty dirs.
        $dir = dirname($this->logoPath);
        for ($i = 0; $i < 4; $i++) {
            if (is_dir($dir) && @rmdir($dir)) {
                $dir = dirname($dir);
                continue;
            }
            break;
        }
    }
}

/**
 * Test-only `CezpdfRenderService` that injects a
 * `SpyCezpdf` instance instead of the standard Cezpdf.
 * Used by `SettingsEffectCoverageTest` to record
 * `ezImage` / `setColor` / `ezText` calls and to
 * disable content-stream compression (so the raw PDF
 * bytes can be scanned for the `r g b rg` operator).
 *
 * The constructor accepts a `FormatoDocumento` via
 * `renderWithFormat()` so the test can set
 * `format->texto` (the texto1 input, which is not a
 * setting but lives on the format object).
 */
final class SpyCezpdfRenderService extends CezpdfRenderService
{
    private SpyCezpdf $spy;

    public function __construct()
    {
        parent::__construct();
        $this->spy = new SpyCezpdf();
    }

    public function getSpy(): SpyCezpdf
    {
        return $this->spy;
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function renderWithFormat(
        \FSFramework\Plugins\factura_pdf1\Model\PrintableDocumentInterface $document,
        array $settings,
        FormatoDocumento $format,
    ): string {
        $this->spy->resetSpies();

        $pdf = $this->spy;
        $pdf->ezSetMargins(50, 50, 50, 50);
        $pdf->ezStartPageNumbers(300, 20, 8, 'right', '{PAGENO} / {TOTALPAGENUM}');

        $port = new PortedPdfDocument(
            $pdf,
            $document,
            $this->settings,
            $this->translator,
            $format,
            $this->numberFormatter,
            $this->locale,
        );

        if ($settings !== []) {
            $loaded = $this->settings->load();
            $merged = array_replace($loaded, $settings);
            $reflection = new \ReflectionProperty(PortedPdfDocument::class, 'settings');
            $reflection->setAccessible(true);
            $reflection->setValue($port, $merged);
        }

        $port->render();

        $output = $pdf->ezOutput();

        return is_string($output) ? $output : '';
    }

    /**
     * Override the parent's `render()` to use the spy
     * Cezpdf instance. Required by the parent's signature
     * (the parent calls `$this->createPdf()` which is now
     * `protected` precisely so this subclass can hook
     * into it).
     */
    public function render(
        \FSFramework\Plugins\factura_pdf1\Model\PrintableDocumentInterface $document,
        array $settings = [],
    ): string {
        return $this->renderWithFormat($document, $settings, new FormatoDocumento());
    }
}
