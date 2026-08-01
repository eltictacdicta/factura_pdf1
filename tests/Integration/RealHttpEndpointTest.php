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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * PR-3 of `factura-pdf1-czpdf-pixel-parity` (Task 3.2):
 * the TRUE HTTP integration test. Closes the test-bypass
 * gap left by `PublicEndpointTest` (which uses
 * `Request::create()` + the `setDependenciesForTests()`
 * seam to inject a mock `documentFactory` and bypass
 * the DB). This test hits the live `index.php` via
 * `file_get_contents()` over the ddev router and asserts
 * the real HTTP response.
 *
 * The test is marked `@group integration` and excluded
 * from the default phpunit run. To opt in, run:
 *
 *   ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml --group integration
 *
 * Skipping semantics:
 *
 *  - If `ddev` is not running (or the URL is not
 *    reachable from the test runner), the test is
 *    marked skipped.
 *  - If the endpoint redirects to the login page
 *    (HTTP 302, no session cookie), the test is marked
 *    skipped (the test environment does not have a
 *    logged-in admin user). Authenticated integration
 *    tests are out of scope for this PR — they require
 *    a real user fixture + a session-cookie dance that
 *    belongs in a follow-up SDD.
 *  - If the endpoint returns HTTP 500, the test fails
 *    (broken pipeline).
 *  - If the endpoint returns HTTP 200 with a valid PDF
 *    body (no auth required, or auth-bypass active),
 *    the test asserts the PDF content.
 *
 * The PR-3 deliverable is the test seam itself, not a
 * logged-in user fixture. The task brief explicitly
 * scopes the test to "real HTTP" — the auth gap is
 * a known limitation and is documented in
 * `apply-progress.md` and as a follow-up SDD.
 */
#[Group('integration')]
final class RealHttpEndpointTest extends TestCase
{
    /**
     * Base URL for the live endpoint. DDEV exposes
     * `DDEV_PRIMARY_URL`; fall back to `http://localhost`
     * for non-ddev runners.
     */
    private function baseUrl(): string
    {
        $env = getenv('DDEV_PRIMARY_URL');
        if (is_string($env) && $env !== '') {
            return rtrim($env, '/');
        }

        return 'http://localhost';
    }

    /**
     * Hit the live `index.php?page=factura_detallada&id=N`
     * endpoint via real HTTP. Returns `null` if the
     * request cannot be made (e.g. ddev is down);
     * returns the raw response body otherwise. The
     * response status is stored in `$this->lastStatus`
     * so the caller can inspect it after the call.
     *
     * Uses `curl` (when available) instead of
     * `file_get_contents` because the PHP HTTPS stream
     * wrapper does NOT reliably populate
     * `$http_response_header` over the ddev TLS
     * terminator (the headers get consumed by the SSL
     * layer and only the body reaches the caller).
     */
    private function fetch(string $path): ?string
    {
        $url = $this->baseUrl() . $path;
        if (function_exists('curl_init')) {
            return $this->fetchCurl($url);
        }

        return $this->fetchFile($url);
    }

    private function fetchCurl(string $url): ?string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT => 'factura_pdf1-integration-test',
            CURLOPT_HEADER => true,
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $this->lastHeaders = [];
            $this->lastStatus = 0;
            curl_close($ch);

            return null;
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $rawHeaders = substr((string) $raw, 0, $headerSize);
        $body = substr((string) $raw, $headerSize);
        curl_close($ch);

        $this->lastHeaders = $rawHeaders === '' ? [] : preg_split('/\r\n|\r|\n/', $rawHeaders);
        $this->lastStatus = $status;

        return $body === '' ? null : $body;
    }

    private function fetchFile(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'ignore_errors' => true,
                'timeout' => 5,
                'method' => 'GET',
                'header' => "User-Agent: factura_pdf1-integration-test\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $this->lastHeaders = $GLOBALS['http_response_header'] ?? [];
        $this->lastStatus = 0;
        foreach ($this->lastHeaders as $h) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $h, $m)) {
                $this->lastStatus = (int) $m[1];
                break;
            }
        }
        if ($body === false) {
            return null;
        }

        return $body;
    }

    /** @var list<string> */
    private array $lastHeaders = [];

    private int $lastStatus = 0;

    /**
     * Read the HTTP status line from the most recent
     * stream context response. Returns 0 if no
     * response header is available.
     */
    private function lastHttpStatus(): int
    {
        return $this->lastStatus;
    }

    private function skipIfDdevUnavailable(): bool
    {
        $ping = $this->fetch('/index.php?page=login');
        if ($ping === null) {
            $this->markTestSkipped(sprintf(
                'ddev not reachable at %s — skipping TRUE HTTP integration test. ' .
                'Run with: ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml --group integration',
                $this->baseUrl(),
            ));

            return true;
        }

        return false;
    }

    public function testFacturaEndpointReturnsValidPdf(): void
    {
        if ($this->skipIfDdevUnavailable()) {
            return;
        }

        $body = $this->fetch('/index.php?page=factura_detallada&id=1');
        $status = $this->lastHttpStatus();

        if ($status === 302 || $status === 0) {
            $this->markTestSkipped(sprintf(
                'factura_detallada endpoint requires authentication (HTTP %d). ' .
                'Auth-bypass for integration tests is a follow-up SDD.',
                $status,
            ));

            return;
        }

        $this->assertSame(200, $status, 'factura_detallada should return HTTP 200 for an authenticated request.');
        $this->assertNotNull($body, 'factura_detallada should return a non-empty body.');
        $this->assertStringStartsWith('%PDF-', $body, 'factura_detallada should return a PDF binary.');
        $this->assertGreaterThanOrEqual(1024, strlen($body), 'factura_detallada should return a PDF >= 1 KB.');
    }

    public function testAlbaranEndpointReturnsValidPdf(): void
    {
        if ($this->skipIfDdevUnavailable()) {
            return;
        }

        $body = $this->fetch('/index.php?page=factura_detallada&tipo=albaran&id=1');
        $status = $this->lastHttpStatus();

        if ($status === 302 || $status === 0) {
            $this->markTestSkipped(sprintf(
                'albaran endpoint requires authentication (HTTP %d).',
                $status,
            ));

            return;
        }

        $this->assertSame(200, $status, 'albaran endpoint should return HTTP 200 for an authenticated request.');
        $this->assertNotNull($body, 'albaran endpoint should return a non-empty body.');
        $this->assertStringStartsWith('%PDF-', $body, 'albaran endpoint should return a PDF binary.');
        $this->assertGreaterThanOrEqual(1024, strlen($body), 'albaran endpoint should return a PDF >= 1 KB.');
    }

    public function testPedidoEndpointReturnsValidPdf(): void
    {
        if ($this->skipIfDdevUnavailable()) {
            return;
        }

        $body = $this->fetch('/index.php?page=factura_detallada&tipo=pedido&id=1');
        $status = $this->lastHttpStatus();

        if ($status === 302 || $status === 0) {
            $this->markTestSkipped(sprintf(
                'pedido endpoint requires authentication (HTTP %d).',
                $status,
            ));

            return;
        }

        $this->assertSame(200, $status, 'pedido endpoint should return HTTP 200 for an authenticated request.');
        $this->assertNotNull($body, 'pedido endpoint should return a non-empty body.');
        $this->assertStringStartsWith('%PDF-', $body, 'pedido endpoint should return a PDF binary.');
        $this->assertGreaterThanOrEqual(1024, strlen($body), 'pedido endpoint should return a PDF >= 1 KB.');
    }

    public function testPresupuestoEndpointReturnsValidPdf(): void
    {
        if ($this->skipIfDdevUnavailable()) {
            return;
        }

        $body = $this->fetch('/index.php?page=factura_detallada&tipo=presupuesto&id=1');
        $status = $this->lastHttpStatus();

        if ($status === 302 || $status === 0) {
            $this->markTestSkipped(sprintf(
                'presupuesto endpoint requires authentication (HTTP %d).',
                $status,
            ));

            return;
        }

        $this->assertSame(200, $status, 'presupuesto endpoint should return HTTP 200 for an authenticated request.');
        $this->assertNotNull($body, 'presupuesto endpoint should return a non-empty body.');
        $this->assertStringStartsWith('%PDF-', $body, 'presupuesto endpoint should return a PDF binary.');
        $this->assertGreaterThanOrEqual(1024, strlen($body), 'presupuesto endpoint should return a PDF >= 1 KB.');
    }

    public function testLiveEndpointDdevReachable(): void
    {
        // Lightweight smoke test: the ddev router
        // responds to the login page (HTTP 200). This
        // is the pre-condition for the other four
        // integration tests; running it in isolation
        // lets the test report show "ddev is up" vs
        // "ddev is down" without invoking the full
        // PDF pipeline.
        $body = $this->fetch('/index.php?page=login');
        if ($body === null) {
            $this->markTestSkipped('ddev not reachable at ' . $this->baseUrl());

            return;
        }

        $this->assertSame(200, $this->lastHttpStatus(), 'ddev router should respond 200 to the login page.');
        $this->assertNotEmpty($body, 'ddev router should return a non-empty body for the login page.');
    }
}
