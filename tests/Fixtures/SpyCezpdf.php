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

namespace FacturaPdf1\Tests\Fixtures;

use Cezpdf;

/**
 * Test double for the vendored Cezpdf class used by
 * `SettingsEffectCoverageTest` to record draw calls
 * (`ezImage`, `setColor`, `ezText`) and to disable
 * content-stream compression so the raw PDF bytes can
 * be scanned for the `r g b rg` colour operator.
 *
 * The class name is intentionally NOT a `Mock` suffix
 * because it is a hand-rolled spy (a real subclass that
 * records args then forwards to the parent), not a
 * PHPUnit mock object.
 */
final class SpyCezpdf extends Cezpdf
{
    /** @var list<array{image: string, pad: mixed, width: mixed, resize: mixed, just: string, border: mixed}> */
    public array $ezImageCalls = [];

    /** @var list<array{r: float, g: float, b: float, stroke: bool}> */
    public array $setColorCalls = [];

    /** @var list<array{text: string, size: mixed, options: array<string, mixed>}> */
    public array $ezTextCalls = [];

    public function __construct($paper = 'a4', $orientation = 'portrait', $type = 'none', $options = [])
    {
        parent::__construct($paper, $orientation, $type, $options);
        // Disable content-stream compression so the raw PDF
        // bytes can be scanned for the "r g b rg" colour
        // operator in `SettingsEffectCoverageTest`. The
        // default `compression=7` would FlateDecode the
        // stream and hide the operator.
        $this->options['compression'] = 0;
    }

    public function ezImage($image, $pad = 5, $width = 0, $resize = 'full', $just = 'center', $angle = 0, $border = '')
    {
        $this->ezImageCalls[] = [
            'image' => (string) $image,
            'pad' => $pad,
            'width' => $width,
            'resize' => $resize,
            'just' => (string) $just,
            'angle' => $angle,
            'border' => $border,
        ];

        return parent::ezImage($image, $pad, $width, $resize, $just, $border);
    }

    public function setColor($r, $g, $b, $force = false)
    {
        $this->setColorCalls[] = [
            'r' => (float) $r,
            'g' => (float) $g,
            'b' => (float) $b,
            'stroke' => false,
        ];

        return parent::setColor($r, $g, $b, $force);
    }

    public function setStrokeColor($r, $g, $b, $force = false)
    {
        $this->setColorCalls[] = [
            'r' => (float) $r,
            'g' => (float) $g,
            'b' => (float) $b,
            'stroke' => true,
        ];

        return parent::setStrokeColor($r, $g, $b, $force);
    }

    public function ezText($text, $size = 0, $options = [], $test = 0)
    {
        $this->ezTextCalls[] = [
            'text' => (string) $text,
            'size' => $size,
            'options' => is_array($options) ? $options : [],
        ];

        return parent::ezText($text, $size, $options, $test);
    }

    public function resetSpies(): void
    {
        $this->ezImageCalls = [];
        $this->setColorCalls = [];
        $this->ezTextCalls = [];
    }
}
