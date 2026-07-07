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
 * ## Sufijo de búsqueda acumulada — exclusión de JoinModel (importación de repostajes)
 *
 * Con `CSVimport` activo, `ListFuelKm::createViewImportKms()` sustituye el modelo de la vista
 * `ListFuelKm` por el `JoinModel` `FacturaScripts\Dinamic\Model\Join\FuelKm` (para poder buscar en
 * las tablas relacionadas). `AccumulatedSearchTitle::shouldEnrich()` excluye expresamente los
 * `JoinModel` (no exponen `primaryColumn()`/`tableName()`), así que, aunque `BuscadorAcumulado`
 * esté activo, esa vista **no** debe recibir el sufijo de contadores. Otra vista de la misma
 * suite con un modelo normal de OpenServBus (`ListFuelPump`) sí debe enriquecerse.
 */
final class JoinModelNotEnrichedTest extends TestCase
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
     * En el mismo escenario (CSVimport + BuscadorAcumulado), otra vista de ListFuelKm con un
     * modelo normal de OpenServBus (ListFuelPump -> FuelPump) sí debe enriquecerse con el sufijo
     * de contadores, confirmando que la exclusión es específica de los JoinModel.
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
     * estar CSVimport activo) y, pese a que BuscadorAcumulado está activo, su título no debe
     * llevar el sufijo "||count||total..." porque shouldEnrich() excluye los JoinModel.
     */
    public function testVistaConJoinModelNoSeEnriquece(): void
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

        $this->assertSame(
            $before,
            $joinView->title,
            'El pipe loadData no debe modificar el título de una vista cuyo modelo es un JoinModel'
        );
        $this->assertStringNotContainsString(
            '||',
            $joinView->title,
            'Una vista con JoinModel no debe llevar el sufijo de contadores de BuscadorAcumulado'
        );
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
