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
 * ## Sufijo de búsqueda acumulada — BuscadorAcumulado **activado**
 *
 * Desde BuscadorAcumulado 2.64 el enriquecido de títulos es **nativo**: su propia extensión de
 * `ListController` (registrada por su `Init::init()`, sin gate) añade a TODA vista con `searchFields`
 * el sufijo `||count||total||campo:Etiqueta...`. OpenServBus ya no aporta ninguna extensión propia
 * para esto. Esta suite verifica, por integración (pipe real `loadData`), que:
 *
 * 1. Con BuscadorAcumulado activo, la vista principal de `ListVehicle` (modelo de OpenServBus con
 *    searchFields) recibe el sufijo con el selector de sus tres campos.
 * 2. La presencia del sufijo coincide con el estado del plugin (red de alarma: si una futura versión
 *    de BuscadorAcumulado dejara de enriquecer, esta invariante lo detectaría).
 */
final class AccumulatedSearchPresentTest extends TestCase
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
     * Precondición de la suite: BuscadorAcumulado está activo (install-plugins.txt de esta
     * carpeta lo incluye).
     */
    public function testBuscadorAcumuladoActivado(): void
    {
        $this->assertTrue(
            Plugins::isEnabled('BuscadorAcumulado'),
            'Esta suite (Test/with-buscadoracumulado) debe ejecutarse con BuscadorAcumulado activado'
        );
    }

    /**
     * Invariante: que el pipe real ListVehicle::loadData enriquezca el título (le añada "||") debe
     * coincidir exactamente con Plugins::isEnabled('BuscadorAcumulado'). El enriquecido lo aporta el
     * pipe NATIVO de BuscadorAcumulado (ListVehicle tiene searchFields), no una extensión de OpenServBus.
     */
    public function testDetectionMatchesEnabledState(): void
    {
        $controller = new \FacturaScripts\Dinamic\Controller\ListVehicle('ListVehicle');
        $this->invokeCreateViews($controller);

        $view = $controller->views['ListVehicle'];
        $view->count = 1;

        $this->assertTrue($controller->pipeFalse('loadData', 'ListVehicle', $view));

        $this->assertSame(
            Plugins::isEnabled('BuscadorAcumulado'),
            strpos($view->title, '||') !== false,
            'El enriquecido real del pipe debe coincidir exactamente con Plugins::isEnabled(\'BuscadorAcumulado\')'
        );
    }

    /**
     * El pipe loadData nativo de BuscadorAcumulado debe añadir el sufijo "||count||total||campo:Etiqueta"
     * al título de la vista principal de ListVehicle, con el selector de sus tres searchFields.
     */
    public function testPipeLoadDataEnriqueceElTituloDeListVehicle(): void
    {
        $this->assertTrue(
            Plugins::isEnabled('BuscadorAcumulado'),
            'Esta suite (Test/with-buscadoracumulado) debe ejecutarse con BuscadorAcumulado activado'
        );

        $controller = new \FacturaScripts\Dinamic\Controller\ListVehicle('ListVehicle');
        $this->invokeCreateViews($controller);

        $view = $controller->views['ListVehicle'];
        $view->count = 3;
        $before = $view->title;

        $this->assertTrue($controller->pipeFalse('loadData', 'ListVehicle', $view));

        $this->assertStringStartsWith(
            $before,
            $view->title,
            'El título enriquecido debe conservar el título original como prefijo'
        );
        $this->assertStringContainsString(
            '||',
            $view->title,
            'Con BuscadorAcumulado activo, el título de ListVehicle debe llevar el sufijo de contadores'
        );

        // el bloque de count debe empezar justo tras el título original: "<title>||3||<total>"
        $this->assertStringStartsWith(
            $before . '||3||',
            $view->title,
            'El sufijo debe incluir el count fijado en la vista justo tras el título original'
        );

        // selector por campo: los tres searchFields de ListVehicle deben aparecer como pares campo:Etiqueta
        $this->assertStringContainsString('||cod_vehicle:', $view->title, 'Falta el selector de campo "cod_vehicle"');
        $this->assertStringContainsString('||nombre:', $view->title, 'Falta el selector de campo "nombre"');
        $this->assertStringContainsString('||matricula:', $view->title, 'Falta el selector de campo "matricula"');
    }

    /** Invoca createViews() (protegido) del controlador por reflexión para poblar $controller->views. */
    private function invokeCreateViews($controller): void
    {
        $method = new ReflectionMethod($controller, 'createViews');
        $method->setAccessible(true);
        $method->invoke($controller);
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
