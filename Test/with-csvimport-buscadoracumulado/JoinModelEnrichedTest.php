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
use FacturaScripts\Core\Template\JoinModel;
use FacturaScripts\Dinamic\Controller\ListFuelKm;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Escenario CON los plugins CSVimport y BuscadorAcumulado activados (install-plugins.txt =
 * OpenServBus,CSVimport,BuscadorAcumulado).
 *
 * @description
 * ## Sufijo de búsqueda acumulada — JoinModel (importación de repostajes)
 *
 * Con `CSVimport` activo, `ListFuelKm::createViewImportKms()` sustituye el modelo de la vista
 * `ListFuelKm` por el `JoinModel` `FacturaScripts\Dinamic\Model\Join\FuelKm` (para poder buscar en
 * las tablas relacionadas: conductor, vehículo y surtidor). Esa vista debe recibir el sufijo de
 * contadores `||count||total||campo:Etiqueta...` Y su selector de campo debe ofrecer los cinco
 * searchFields, incluidos los que NO tienen columna visible en el XMLView (`d.nombre_conductor`,
 * `v.nombre_vehiculo`, `fp.nombre_surtidor`), que BuscadorAcumulado por sí solo no puede ofrecer
 * porque arma el selector desde columnas: los completa la extensión de OpenServBus.
 */
final class JoinModelEnrichedTest extends TestCase
{
    use DefaultSettingsTrait;
    use LogErrorsTrait;

    public static function setUpBeforeClass(): void
    {
        // aseguramos empresa y ajustes por defecto (el constructor del controlador
        // llama a Empresas::default()).
        self::setDefaultSettings();
    }

    /**
     * Otra vista de ListFuelKm con un modelo normal de OpenServBus (ListFuelPump -> FuelPump)
     * también debe enriquecerse con el sufijo de contadores.
     */
    public function testOtraVistaConModeloNormalSiSeEnriquece(): void
    {
        $controller = new ListFuelKm('ListFuelKm');
        $controller->permissions = new ControllerPermissions();

        $method = new ReflectionMethod($controller, 'createViews');
        $method->setAccessible(true);
        $method->invoke($controller);

        $this->assertArrayHasKey(
            'ListFuelPump',
            $controller->views,
            'ListFuelKm debe registrar la vista "ListFuelPump" con el modelo FuelPump'
        );

        $view = $controller->views['ListFuelPump'];
        $view->count = 1;
        $before = $view->title;

        $this->assertTrue($controller->pipeFalse('loadData', 'ListFuelPump', $view));

        $this->assertStringStartsWith(
            $before,
            $view->title,
            'El título enriquecido debe conservar el título original como prefijo'
        );
        $this->assertStringContainsString(
            '||',
            $view->title,
            'ListFuelPump usa un modelo normal de OpenServBus, así que sí debe enriquecerse'
        );
    }

    /** Precondición de la suite: los tres plugins están activos. */
    public function testPluginsActivos(): void
    {
        $this->assertTrue(
            Plugins::isEnabled('OpenServBus'),
            'Esta suite (Test/with-csvimport-buscadoracumulado) debe ejecutarse con OpenServBus activado'
        );
        $this->assertTrue(
            Plugins::isEnabled('CSVimport'),
            'Esta suite (Test/with-csvimport-buscadoracumulado) debe ejecutarse con CSVimport activado'
        );
        $this->assertTrue(
            Plugins::isEnabled('BuscadorAcumulado'),
            'Esta suite (Test/with-csvimport-buscadoracumulado) debe ejecutarse con BuscadorAcumulado activado'
        );
    }

    /**
     * La vista ListFuelKm queda con un modelo JoinModel (sustituido por createViewImportKms al
     * estar CSVimport activo). Tras el pipe loadData su título debe llevar el sufijo de contadores
     * y el selector debe incluir los cinco searchFields, incluidos los tres campos calculados sin
     * columna visible.
     */
    public function testVistaConJoinModelSeEnriqueceConTodosSusCampos(): void
    {
        $controller = new ListFuelKm('ListFuelKm');
        $controller->permissions = new ControllerPermissions();

        $method = new ReflectionMethod($controller, 'createViews');
        $method->setAccessible(true);
        $method->invoke($controller);

        $joinView = null;
        $joinViewName = null;
        foreach ($controller->views as $viewName => $view) {
            if (is_object($view) && isset($view->model) && $view->model instanceof JoinModel) {
                $joinView = $view;
                $joinViewName = $viewName;
                break;
            }
        }

        $this->assertNotNull(
            $joinView,
            'No se ha encontrado ninguna vista de ListFuelKm con un modelo JoinModel; '
            . 'se esperaba que createViewImportKms() sustituyera el modelo de "ListFuelKm" '
            . 'por FacturaScripts\Dinamic\Model\Join\FuelKm al estar CSVimport activo'
        );

        $joinView->count = 1;
        $before = $joinView->title;

        $this->assertTrue($controller->pipeFalse('loadData', $joinViewName, $joinView));

        $this->assertStringStartsWith(
            $before,
            $joinView->title,
            'El título enriquecido debe conservar el título original como prefijo'
        );
        $this->assertStringContainsString(
            '||',
            $joinView->title,
            'Una vista con JoinModel de OpenServBus sí debe llevar el sufijo de contadores'
        );

        // selector de campo: los cinco searchFields deben aparecer como pares "searchField:Etiqueta",
        // usando la clave COMPLETA (prefijada) para que el WHERE sea SQL válido en el Join.
        foreach (['fk.km:', 'fk.litros:', 'd.nombre_conductor:', 'v.nombre_vehiculo:', 'fp.nombre_surtidor:'] as $pair) {
            $this->assertStringContainsString(
                '||' . $pair,
                $joinView->title,
                'Falta el campo "' . $pair . '" en el selector del JoinModel de ListFuelKm'
            );
        }
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
