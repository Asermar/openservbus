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
use FacturaScripts\Core\Tools;
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
 * ## Sufijo de búsqueda acumulada — enriquecido de JoinModel (importación de repostajes)
 *
 * Con `CSVimport` activo, `ListFuelKm::createViewImportKms()` sustituye el modelo de la vista
 * `ListFuelKm` por el `JoinModel` `FacturaScripts\Dinamic\Model\Join\FuelKm` (para poder buscar en
 * las tablas relacionadas: conductor, vehículo, surtidor). Desde BuscadorAcumulado 2.64 el
 * enriquecido de títulos es nativo y **sí** cubre los JoinModel: esa vista debe recibir el sufijo
 * `||count||total||campo:Etiqueta...`, con el selector de TODOS sus searchFields, incluidos los que
 * no tienen columna visible (`d.nombre_conductor`, `v.nombre_vehiculo`, `fp.nombre_surtidor`), que
 * OpenServBus traduce con etiquetas propias. Otra vista con modelo normal (`ListFuelPump`) también
 * se enriquece.
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
     * Los searchFields sin columna visible (conductor/vehículo/surtidor) se etiquetan con
     * Tools::trans() del nombre de campo; sin claves de traducción propias saldría el fallback
     * "Nombre_conductor". Este test (desacoplado del idioma) garantiza que existen las traducciones.
     */
    public function testEtiquetasDeCamposSinColumnaEstanTraducidas(): void
    {
        foreach (['nombre_conductor', 'nombre_vehiculo', 'nombre_surtidor'] as $key) {
            $this->assertNotSame(
                $key,
                Tools::trans($key),
                sprintf('Falta la traducción de "%s" (el selector mostraría el nombre de campo crudo)', $key)
            );
        }
    }

    /**
     * En el mismo escenario, otra vista de ListFuelKm con un modelo normal de OpenServBus
     * (ListFuelPump -> FuelPump) también debe enriquecerse con el sufijo de contadores.
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
     * La vista ListFuelKm queda con un modelo JoinModel (sustituido por createViewImportKms al estar
     * CSVimport activo). Bajo BuscadorAcumulado 2.64 su título debe recibir el sufijo de contadores y
     * el selector con sus cinco searchFields (los prefijados sin columna incluidos).
     */
    public function testVistaConJoinModelSiSeEnriquece(): void
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

        $this->assertTrue($controller->pipeFalse('loadData', $joinViewName, $joinView));

        $this->assertStringContainsString(
            '||1||',
            $joinView->title,
            'La vista con JoinModel debe llevar el bloque de contadores "||1||total"'
        );

        // selector de campo: los cinco searchFields del Join deben aparecer como pares campo:Etiqueta
        foreach (['fk.km:', 'fk.litros:', 'd.nombre_conductor:', 'v.nombre_vehiculo:', 'fp.nombre_surtidor:'] as $needle) {
            $this->assertStringContainsString(
                $needle,
                $joinView->title,
                sprintf('Falta el selector de campo "%s" en la vista JoinModel de ListFuelKm', rtrim($needle, ':'))
            );
        }
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
