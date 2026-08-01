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

use FSFramework\Translation\FSTranslator;
use PHPUnit\Framework\TestCase;

final class TranslationLoadingTest extends TestCase
{
    /** @var list<string> */
    private const KEYS = [
        'factura-pdf1.admin.title',
        'factura-pdf1.admin.save',
        'factura-pdf1.admin.reset',
        'factura-pdf1.admin.saved',
        'factura-pdf1.admin.invalid-color',
        'factura-pdf1.group.logo',
        'factura-pdf1.group.layout',
        'factura-pdf1.setting.posicionlogo',
        'factura-pdf1.setting.texto2',
        'factura-pdf1.text-block-1-position-1',
        'factura-pdf1.text-block-1-position-2',
        'factura-pdf1.text-block-1-position-3',
        'factura-pdf1.text-block-1-position-4',
        'factura-pdf1.text-block-1-position-5',
        'factura-pdf1.text-block-1-position-6',
        'factura-pdf1.text-block-1-position-7',
        'factura-pdf1.text-block-2-position-1',
        'factura-pdf1.text-block-2-position-2',
        'factura-pdf1.text-block-2-position-3',
        'factura-pdf1.text-block-2-position-4',
        'factura-pdf1.text-block-2-position-5',
        'factura-pdf1.text-block-2-position-6',
        'factura-pdf1.text-block-2-position-7',
    ];

    protected function setUp(): void
    {
        FSTranslator::reset();
        FSTranslator::initialize(FS_FOLDER);
        FSTranslator::loadPluginTranslations('factura_pdf1', FS_FOLDER . '/plugins/factura_pdf1');
    }

    public function testSpanishTranslationsResolve(): void
    {
        FSTranslator::setLocale('es_ES');
        foreach (self::KEYS as $key) {
            $value = FSTranslator::trans($key);
            $this->assertNotSame('', trim($value), 'Missing es_ES translation for ' . $key);
            $this->assertNotSame($key, $value, 'Untranslated key returned for ' . $key);
        }
    }

    public function testEnglishTranslationsResolve(): void
    {
        FSTranslator::setLocale('en_EN');
        foreach (self::KEYS as $key) {
            $value = FSTranslator::trans($key);
            $this->assertNotSame('', trim($value), 'Missing en_EN translation for ' . $key);
            $this->assertNotSame($key, $value, 'Untranslated key returned for ' . $key);
        }
    }

    public function testMissingKeyFallsBackToLiteralKey(): void
    {
        FSTranslator::setLocale('es_ES');
        $missing = 'factura-pdf1.nonexistent.key';
        $this->assertSame($missing, FSTranslator::trans($missing));
    }
}
