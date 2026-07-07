<?php
/**
 * This file is part of OpenServBus plugin for FacturaScripts
 * Copyright (C) 2026 Oko Digital Experts, S.L.L. (Okodex)
 * @author Alexis Serafín <alexis@okodex.com>
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
 * along with this program. If not, see http://www.gnu.org/licenses/.
 */

namespace FacturaScripts\Test\Plugins;

use FacturaScripts\Core\Base\ControllerPermissions;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Escenario SIN el plugin BuscadorAcumulado activado (install-plugins.txt = OpenServBus).
 *
 * @description
 * ## Sufijo de búsqueda acumulada — barrido de TODOS los listados sin BuscadorAcumulado
 *
 * Verifica, a escala global, el gating de `Init.php`: sin `BuscadorAcumulado` activado la
 * extensión `Extension\Controller\ListController` no se registra, así que ejecutar el pipe real
 * `loadData` de los 12 controladores `List*` de OpenServBus (y todas sus vistas) **no** debe
 * enriquecer ningún título con el separador `||`, sea cual sea el modelo de la vista.
 */
final class AllListViewsNotEnrichedTest extends TestCase
{
    use DefaultSettingsTrait;
    use LogErrorsTrait;

    /** Controladores List* de OpenServBus registrados en Dinamic\Controller. */
    private const LIST_CONTROLLERS = [
        'ListAdvertismentUser',
        'ListDriver',
        'ListEmployeeAttendanceManagement',
        'ListEmployeeOpen',
        'ListFuelKm',
        'ListHelper',
        'ListServiceAssembly',
        'ListService',
        'ListServiceRegular',
        'ListTarjeta',
        'ListVehicleDocumentation',
        'ListVehicle',
    ];

    public static function setUpBeforeClass(): void
    {
        // aseguramos empresa y ajustes por defecto (el constructor del controlador
        // llama a Empresas::default()).
        self::setDefaultSettings();
    }

    /** Precondición de la suite: BuscadorAcumulado no está activo. */
    public function testBuscadorAcumuladoDesactivado(): void
    {
        $this->assertFalse(
            Plugins::isEnabled('BuscadorAcumulado'),
            'Esta suite (Test/main) debe ejecutarse con BuscadorAcumulado desactivado'
        );
    }

    /**
     * Barre TODOS los controladores List* de OpenServBus y TODAS sus vistas: sin BuscadorAcumulado
     * activo, el pipe loadData real no debe añadir el separador "||" al título de ninguna vista,
     * independientemente de cuál sea su modelo. Además, ninguna vista debe lanzar una excepción.
     */
    public function testNingunaVistaSeEnriqueceSinBuscadorAcumulado(): void
    {
        $exceptions = [];
        $enriched = [];

        foreach (self::LIST_CONTROLLERS as $controllerName) {
            $className = '\\FacturaScripts\\Dinamic\\Controller\\' . $controllerName;
            $controller = new $className($controllerName);
            $controller->permissions = new ControllerPermissions();

            $method = new ReflectionMethod($controller, 'createViews');
            $method->setAccessible(true);
            $method->invoke($controller);

            foreach ($controller->views as $viewName => $view) {
                if (false === is_object($view) || false === property_exists($view, 'title')) {
                    continue;
                }

                $view->count = 1;
                $before = $view->title;

                try {
                    $controller->pipeFalse('loadData', $viewName, $view);
                } catch (\Throwable $e) {
                    $exceptions[] = sprintf(
                        '%s::%s lanzó %s: %s',
                        $controllerName,
                        $viewName,
                        get_class($e),
                        $e->getMessage()
                    );
                    continue;
                }

                if ($view->title !== $before || strpos((string)$view->title, '||') !== false) {
                    $enriched[] = $controllerName . '::' . $viewName;
                }
            }
        }

        $this->assertSame(
            [],
            $exceptions,
            "Ninguna vista debe lanzar una excepción al ejecutar el pipe loadData:\n" . implode("\n", $exceptions)
        );

        $this->assertSame(
            [],
            $enriched,
            "Sin BuscadorAcumulado activo, ninguna vista debe enriquecerse, pero sí lo hicieron:\n" . implode("\n", $enriched)
        );
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
