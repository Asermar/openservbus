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
use FacturaScripts\Plugins\OpenServBus\Lib\AccumulatedSearchTitle;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Escenario CON el plugin BuscadorAcumulado activado (install-plugins.txt =
 * OpenServBus,BuscadorAcumulado).
 *
 * @description
 * ## Sufijo de búsqueda acumulada — barrido de TODOS los listados de OpenServBus
 *
 * Con `BuscadorAcumulado` activado, recorre los 12 controladores `List*` de OpenServBus y, para
 * cada una de sus vistas, ejecuta el pipe real `loadData` (el mismo que registra `Init::init()`)
 * y comprueba que el enriquecido del título (`||count||total[||campo:Etiqueta...]`) coincide
 * exactamente con lo que predice `AccumulatedSearchTitle::shouldEnrich()` a partir del modelo de
 * la vista, sin que ninguna vista lance una excepción al procesarla.
 */
final class AllListViewsEnrichedTest extends TestCase
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

    /**
     * Refuerzo concreto (modelo NORMAL, no JoinModel): la vista ListStop (modelo Stop) tiene
     * searchFields 'provincia' y 'codpostal' que NO se muestran como columna en su XMLView. Deben
     * ofrecerse igualmente en el selector, aunque BuscadorAcumulado (que arma el selector desde
     * columnas) no los incluya. Verifica que la adaptación no es exclusiva de los JoinModel.
     */
    public function testListStopIncluyeCamposSinColumnaEnElSelector(): void
    {
        $controller = $this->createController('ListService');

        $this->assertArrayHasKey('ListStop', $controller->views, 'ListService debe registrar la vista "ListStop"');

        $view = $controller->views['ListStop'];
        $view->count = 1;

        $this->assertTrue($controller->pipeFalse('loadData', 'ListStop', $view));

        $this->assertStringContainsString('||provincia:', $view->title, 'Falta el campo sin columna "provincia" en el selector de ListStop');
        $this->assertStringContainsString('||codpostal:', $view->title, 'Falta el campo sin columna "codpostal" en el selector de ListStop');
    }

    /**
     * Refuerzo concreto: la vista principal de ListVehicle (modelo Vehicle, propio de
     * OpenServBus) debe llevar el selector de campo con sus tres searchFields.
     */
    public function testListVehicleIncluyeSelectorDeSusTresCampos(): void
    {
        $controller = $this->createController('ListVehicle');
        $view = $controller->views['ListVehicle'];
        $view->count = 1;

        $this->assertTrue($controller->pipeFalse('loadData', 'ListVehicle', $view));

        $this->assertStringContainsString('||cod_vehicle:', $view->title, 'Falta el selector de campo "cod_vehicle" en ListVehicle');
        $this->assertStringContainsString('||nombre:', $view->title, 'Falta el selector de campo "nombre" en ListVehicle');
        $this->assertStringContainsString('||matricula:', $view->title, 'Falta el selector de campo "matricula" en ListVehicle');
    }

    /** Precondición de la suite: BuscadorAcumulado y OpenServBus están activos. */
    public function testPluginsActivos(): void
    {
        $this->assertTrue(
            Plugins::isEnabled('OpenServBus'),
            'Esta suite (Test/with-buscadoracumulado) debe ejecutarse con OpenServBus activado'
        );
        $this->assertTrue(
            Plugins::isEnabled('BuscadorAcumulado'),
            'Esta suite (Test/with-buscadoracumulado) debe ejecutarse con BuscadorAcumulado activado'
        );
    }

    /**
     * Barre TODOS los controladores List* de OpenServBus y TODAS sus vistas: ejecuta el pipe real
     * loadData sobre cada una y comprueba que el enriquecido del título coincide exactamente con
     * lo que predice AccumulatedSearchTitle::shouldEnrich() evaluado sobre el modelo de la vista
     * antes de aplicar el pipe. Ninguna vista debe lanzar una excepción al procesarla.
     */
    public function testTodosLosControladoresEnriquecenSegunElModelo(): void
    {
        $exceptions = [];

        foreach (self::LIST_CONTROLLERS as $controllerName) {
            $controller = $this->createController($controllerName);

            foreach ($controller->views as $viewName => $view) {
                if (false === is_object($view) || false === property_exists($view, 'title')) {
                    continue;
                }

                // la expectativa se calcula ANTES de ejecutar el pipe, sobre el modelo/título
                // originales de la vista (shouldEnrich() es una función pura sin efectos).
                $expected = AccumulatedSearchTitle::shouldEnrich($view);

                $view->count = 1;

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

                $this->assertSame(
                    $expected,
                    strpos((string)$view->title, '||') !== false,
                    sprintf(
                        'El enriquecido del título de %s::%s debe coincidir con shouldEnrich() del modelo',
                        $controllerName,
                        $viewName
                    )
                );
            }
        }

        $this->assertSame(
            [],
            $exceptions,
            "Ninguna vista debe lanzar una excepción al ejecutar el pipe loadData:\n" . implode("\n", $exceptions)
        );
    }

    /**
     * Refuerzo concreto: una vista sin searchFields (ListEmployeeAttendanceManagement) debe
     * recibir únicamente el bloque de contadores "||count||total", sin ningún par "campo:"
     * adicional (no hay selector de campo porque no hay searchFields que ofrecer).
     */
    public function testVistaSinSearchFieldsSoloLlevaContadores(): void
    {
        $controller = $this->createController('ListEmployeeAttendanceManagement');
        $view = $controller->views['ListEmployeeAttendanceManagement'];
        $view->count = 1;
        $before = $view->title;

        $this->assertTrue($controller->pipeFalse('loadData', 'ListEmployeeAttendanceManagement', $view));

        $this->assertStringContainsString(
            '||1||',
            $view->title,
            'La vista debe llevar el bloque de contadores "||1||total"'
        );

        // tras "<title original>||1||<total>" no debe quedar ningún segmento "||" más (sin
        // selector de campo, porque la vista no tiene searchFields).
        $suffix = substr($view->title, strlen($before));
        $this->assertMatchesRegularExpression(
            '/^\|\|1\|\|\d+$/',
            $suffix,
            'Sin searchFields, el sufijo debe ser exactamente "||count||total", sin pares "campo:" adicionales'
        );
    }

    /** Instancia un controlador List* de OpenServBus, le fija permisos y puebla $controller->views. */
    private function createController(string $controllerName)
    {
        $className = '\\FacturaScripts\\Dinamic\\Controller\\' . $controllerName;
        $controller = new $className($controllerName);
        $controller->permissions = new ControllerPermissions();

        $method = new ReflectionMethod($controller, 'createViews');
        $method->setAccessible(true);
        $method->invoke($controller);

        return $controller;
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
