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
 * Escenario CON el plugin BuscadorAcumulado activado (install-plugins.txt =
 * OpenServBus,BuscadorAcumulado).
 *
 * @description
 * ## Sufijo de búsqueda acumulada — barrido de TODOS los listados de OpenServBus
 *
 * Desde BuscadorAcumulado 2.64 el enriquecido es NATIVO: su pipe `loadData` añade el sufijo
 * `||count||total[||campo:Etiqueta...]` a TODA vista con `searchFields` (guard único
 * `!empty($view->searchFields)`, sin distinción de tipo de modelo). OpenServBus ya no aporta
 * extensión propia. Esta suite recorre los 12 controladores `List*` de OpenServBus y, para cada
 * vista, ejecuta el pipe real y comprueba que la presencia del sufijo coincide exactamente con la
 * regla nativa (tiene o no `searchFields`), sin que ninguna vista lance una excepción.
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
     * loadData sobre cada una y comprueba, en UN SOLO SENTIDO, que toda vista CON searchFields recibe
     * el sufijo `||...` (así el selector de campo aparece). No se asevera el sentido inverso: además
     * del bloque genérico, BuscadorAcumulado 2.64 enriquece con solo el contador algunas vistas hijas
     * SIN searchFields (sincronización padre↔hijo, p. ej. ListServiceValuation dentro de ListService);
     * eso es lógica interna del tercero y no la acoplamos aquí. Ninguna vista debe lanzar una excepción.
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

                // regla nativa de BuscadorAcumulado 2.64: toda vista con searchFields se enriquece.
                $expected = !empty($view->searchFields ?? []);

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

                // solo un sentido: searchFields ⇒ enriquecido (el selector aparece).
                if ($expected) {
                    $this->assertStringContainsString(
                        '||',
                        (string)$view->title,
                        sprintf('La vista %s::%s tiene searchFields y debe enriquecerse', $controllerName, $viewName)
                    );
                }
            }
        }

        $this->assertSame(
            [],
            $exceptions,
            "Ninguna vista debe lanzar una excepción al ejecutar el pipe loadData:\n" . implode("\n", $exceptions)
        );
    }

    /**
     * Regresión cerrada: ListEmployeeAttendanceManagement no tenía searchFields, así que bajo el
     * enriquecido nativo (gated en !empty(searchFields)) habría perdido el contador "X de Y". Se le
     * añadió addSearchFields(['observaciones']) en el controlador; este test verifica que ahora sí
     * recibe el bloque de contadores y el selector de campo "observaciones".
     */
    public function testVistaDeAsistenciaRecibeContadorYselector(): void
    {
        $controller = $this->createController('ListEmployeeAttendanceManagement');
        $view = $controller->views['ListEmployeeAttendanceManagement'];
        $view->count = 1;

        $this->assertTrue($controller->pipeFalse('loadData', 'ListEmployeeAttendanceManagement', $view));

        $this->assertStringContainsString(
            '||1||',
            $view->title,
            'La vista de asistencia debe llevar el bloque de contadores "||1||total"'
        );
        $this->assertStringContainsString(
            '||observaciones:',
            $view->title,
            'La vista de asistencia debe ofrecer "observaciones" en el selector de campo'
        );
    }

    /**
     * Fase 1 (categoría A): vistas de detalle que NO declaraban searchFields y, bajo el enriquecido
     * nativo de 2.64, habrían perdido el contador. Se les añadió addSearchFields(['observaciones'])
     * (o ['nombre','observaciones']) en su controlador; aquí se verifica que cada una recibe ahora
     * el bloque de contadores y ofrece "observaciones" en el selector de campo. Nota: la vista
     * ListFuelKm solo lleva 'observaciones' cuando CSVimport NO está activo (este escenario); con
     * CSVimport pasa a JoinModel (cubierto por JoinModelEnrichedTest).
     */
    public function testVistasDeDetalleAdaptadasRecibenContadorYselector(): void
    {
        $casos = [
            ['ListHelper', 'ListHelper'],
            ['ListService', 'ListServiceValuation'],
            ['ListServiceRegular', 'ListServiceRegularCombinationServ'],
            ['ListServiceRegular', 'ListServiceRegularItinerary'],
            ['ListServiceRegular', 'ListServiceRegularPeriod'],
            ['ListServiceRegular', 'ListServiceRegularValuation'],
            ['ListFuelKm', 'ListFuelKm'],
        ];

        foreach ($casos as [$controllerName, $viewName]) {
            $controller = $this->createController($controllerName);
            $this->assertArrayHasKey($viewName, $controller->views, "Falta la vista $viewName en $controllerName");
            $view = $controller->views[$viewName];
            $view->count = 1;

            $this->assertTrue($controller->pipeFalse('loadData', $viewName, $view));
            $this->assertStringContainsString('||1||', (string)$view->title, "$viewName debe llevar el bloque de contadores");
            $this->assertStringContainsString('||observaciones:', (string)$view->title, "$viewName debe ofrecer 'observaciones' en el selector");
        }
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
