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

namespace FSFramework\Plugins\factura_pdf1\Controller;

require_once dirname(__DIR__, 3) . '/base/fs_controller.php';
require_once dirname(__DIR__, 3) . '/base/fs_db2.php';
require_once dirname(__DIR__, 3) . '/base/fs_core_log.php';
require_once dirname(__DIR__, 3) . '/base/fs_session_manager.php';

use FSFramework\Plugins\factura_pdf1\Model\Adapters\AlbaranClienteAdapter;
use FSFramework\Plugins\factura_pdf1\Model\Adapters\FacturaClienteAdapter;
use FSFramework\Plugins\factura_pdf1\Model\Adapters\PedidoClienteAdapter;
use FSFramework\Plugins\factura_pdf1\Model\Adapters\PresupuestoClienteAdapter;
use FSFramework\Plugins\factura_pdf1\Model\Exception\PrintableDocumentNotFoundException;
use FSFramework\Plugins\factura_pdf1\Model\HasPrintView;
use FSFramework\Plugins\factura_pdf1\Model\PrintableDocumentInterface;
use FSFramework\Plugins\factura_pdf1\Services\CezpdfRenderService;
use FSFramework\Plugins\factura_pdf1\Services\SettingsService;
use FSFramework\Security\UserAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FacturaPdf1Controller extends \fs_controller
{
    public const PAGE_NAME = 'factura_detallada';

    private static ?CezpdfRenderService $pdfRenderServiceForTests = null;

    private static ?SettingsService $settingsServiceForTests = null;

    /** @var callable(int, Request): PrintableDocumentInterface|null */
    private static $documentFactoryForTests = null;

    /** @var callable(object): UserAdapter|null */
    private static $userAdapterFactoryForTests = null;

    private ?Response $pendingResponse = null;

    public function __construct()
    {
        parent::__construct(self::PAGE_NAME, 'Factura PDF1', 'ventas', false, false);
    }

    public static function resetDependenciesForTests(): void
    {
        self::$pdfRenderServiceForTests = null;
        self::$settingsServiceForTests = null;
        self::$documentFactoryForTests = null;
        self::$userAdapterFactoryForTests = null;
    }

    /**
     * @param callable(int, Request): PrintableDocumentInterface|null $documentFactory
     * @param callable(object): UserAdapter|null $userAdapterFactory
     */
    public static function setDependenciesForTests(
        ?CezpdfRenderService $pdfRenderService = null,
        ?SettingsService $settingsService = null,
        ?callable $documentFactory = null,
        ?callable $userAdapterFactory = null,
    ): void {
        self::$pdfRenderServiceForTests = $pdfRenderService;
        self::$settingsServiceForTests = $settingsService;
        self::$documentFactoryForTests = $documentFactory;
        self::$userAdapterFactoryForTests = $userAdapterFactory;
    }

    public function processRequest(?Request $request = null, ?object $legacyUser = null): Response
    {
        $request ??= $this->request;
        $legacyUser ??= $this->user;

        $idParam = $request->query->get('id');
        if (!is_numeric($idParam)) {
            $this->auditPrint($legacyUser, 0, 'not_found');

            return new JsonResponse(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        $id = (int) $idParam;
        if ($id <= 0) {
            $this->auditPrint($legacyUser, $id, 'not_found');

            return new JsonResponse(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $document = $this->loadDocument($id, $request);
        } catch (PrintableDocumentNotFoundException) {
            $this->auditPrint($legacyUser, $id, 'not_found');

            return new JsonResponse(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        $adapter = $this->createUserAdapter($legacyUser);
        if (!$adapter->hasAccessTo('ventas_factura')) {
            $this->auditPrint($legacyUser, $id, 'forbidden');

            return new JsonResponse(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        $settings = $this->createSettingsService()->load();
        $pdf = $this->createPdfRenderService()->render($document, $settings);
        $this->auditPrint($legacyUser, $id, 'ok');

        $filename = sprintf('%s-%d.pdf', $this->resolveDocumentSlug($request), $id);

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
        ]);
    }

    protected function private_core(): void
    {
        if (!$this->isActivePageRequest()) {
            return;
        }

        $this->template = false;
        $this->pendingResponse = $this->processRequest();
        $this->pendingResponse->send();
        exit;
    }

    private function isActivePageRequest(): bool
    {
        $page = filter_input(INPUT_GET, 'page', FILTER_UNSAFE_RAW);

        return is_string($page) && $page === self::PAGE_NAME;
    }

    public function getPendingResponse(): ?Response
    {
        return $this->pendingResponse;
    }

    private function loadDocument(int $id, Request $request): PrintableDocumentInterface
    {
        if (self::$documentFactoryForTests !== null) {
            return (self::$documentFactoryForTests)($id, $request);
        }

        $adapterClass = $this->resolveAdapter($request);

        /** @var callable(int): PrintableDocumentInterface $factory */
        $factory = [$adapterClass, 'fromId'];

        return $factory($id);
    }

    /**
     * @return class-string<PrintableDocumentInterface&HasPrintView>
     */
    private function resolveAdapter(Request $request): string
    {
        $tipo = strtolower(trim((string) $request->query->get('tipo', 'factura')));

        return match ($tipo) {
            'albaran' => AlbaranClienteAdapter::class,
            'pedido' => PedidoClienteAdapter::class,
            'presupuesto' => PresupuestoClienteAdapter::class,
            default => FacturaClienteAdapter::class,
        };
    }

    private function resolveDocumentSlug(Request $request): string
    {
        $tipo = strtolower(trim((string) $request->query->get('tipo', 'factura')));

        return match ($tipo) {
            'albaran' => 'albaran',
            'pedido' => 'pedido',
            'presupuesto' => 'presupuesto',
            default => 'factura',
        };
    }

    private function createPdfRenderService(): CezpdfRenderService
    {
        return self::$pdfRenderServiceForTests ?? new CezpdfRenderService();
    }

    private function createSettingsService(): SettingsService
    {
        return self::$settingsServiceForTests ?? new SettingsService();
    }

    private function createUserAdapter(object $legacyUser): UserAdapter
    {
        if (self::$userAdapterFactoryForTests !== null) {
            return (self::$userAdapterFactoryForTests)($legacyUser);
        }

        return new UserAdapter($legacyUser);
    }

    private function auditPrint(object $legacyUser, int $id, string $status): void
    {
        $nick = property_exists($legacyUser, 'nick') ? (string) $legacyUser->nick : 'anonymous';
        $message = sprintf('print_factura user=%s id=%d status=%s', $nick, $id, $status);
        (new \fs_core_log('factura_pdf1'))->new_message($message);
    }
}
