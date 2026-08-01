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

namespace FacturaPdf1\Tests;

use FSFramework\Event\FSEventDispatcher;
use FSFramework\Plugins\factura_pdf1\Init;
use FSFramework\Plugins\factura_pdf1\Model\FacturaPdf1Setting;
use FSFramework\Plugins\factura_pdf1\Services\SettingsService;
use PHPUnit\Framework\TestCase;

final class InitTest extends TestCase
{
    protected function setUp(): void
    {
        FSEventDispatcher::reset();
        Init::resetDependenciesForTests();
        FacturaPdf1Setting::enableTestStorage();
    }

    protected function tearDown(): void
    {
        FSEventDispatcher::reset();
        Init::resetDependenciesForTests();
        FacturaPdf1Setting::disableTestStorage();
    }

    /**
     * PR-1 of `factura-pdf1-czpdf-pixel-parity` drops the
     * `registerTwigPaths()` call: the new Cezpdf engine does not
     * consume any Twig template, so the global Twig loader listener
     * is no longer needed. Init::init() must therefore register ZERO
     * event listeners.
     */
    public function testInitRegistersNoEventListeners(): void
    {
        Init::setSettingsServiceForTests(new SettingsService());
        $dispatcher = FSEventDispatcher::getInstance();
        $before = $this->countListeners($dispatcher);

        $init = new Init();
        $init->init();

        $this->assertSame($before, $this->countListeners($dispatcher));
    }

    /**
     * Companion assertion: even after multiple invocations of
     * Init::init(), the listener count stays at zero (no global
     * Twig registration is performed and no state leaks between
     * calls).
     */
    public function testDoubleInitIsIdempotent(): void
    {
        Init::setSettingsServiceForTests(new SettingsService());
        $dispatcher = FSEventDispatcher::getInstance();
        $before = $this->countListeners($dispatcher);

        $init = new Init();
        $init->init();
        $init->init();

        $this->assertSame($before, $this->countListeners($dispatcher));
    }

    private function countListeners(FSEventDispatcher $dispatcher): int
    {
        $total = 0;
        foreach ($dispatcher->getListeners() as $listeners) {
            $total += count($listeners);
        }

        return $total;
    }
}
