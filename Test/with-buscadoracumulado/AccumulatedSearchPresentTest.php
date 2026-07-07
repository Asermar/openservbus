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
 * ## Sufijo de búsqueda acumulada — BuscadorAcumulado **activado**
 *
 * Verifica, por integración (pipe real `loadData` registrado por `Init::init()`), que con
 * `BuscadorAcumulado` activado:
 *
 * 1. La vista principal de `ListVehicle` (modelo propio de OpenServBus) recibe el sufijo
 *    `||count||total||campo:Etiqueta...` en su título.
 * 2. Ese enriquecido queda restringido a las vistas de OpenServBus: una lista **del core**
 *    (`ListCliente`, ajena a OpenServBus) no se ve afectada por esta extensión.
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
     * Invariante válida en cualquier estado del plugin: que el pipe real ListVehicle::loadData
     * enriquezca el título (le añada el separador "||") debe coincidir exactamente con
     * Plugins::isEnabled('BuscadorAcumulado'). Nótese que esto depende del REGISTRO de la
     * extensión en Init.php, no de AccumulatedSearchTitle::shouldEnrich() (que es una función
     * pura sin conocimiento del estado de los plugins: solo mira el modelo y el título).
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
     * Aislamiento de scope: una lista del CORE ajena a OpenServBus (ListCliente) no debe verse
     * afectada por esta extensión, aunque el pipe loadData esté enganchado globalmente a todos
     * los List* del sistema.
     */
    public function testListaDelCoreNoSeVeAfectada(): void
    {
        $this->assertTrue(
            Plugins::isEnabled('BuscadorAcumulado'),
            'Esta suite (Test/with-buscadoracumulado) debe ejecutarse con BuscadorAcumulado activado'
        );

        $controller = new \FacturaScripts\Dinamic\Controller\ListCliente('ListCliente');
        // createViews() de ListCliente consulta $this->permissions->onlyOwnerData; sin privateCore()
        // esa propiedad es null, así que la fijamos con un valor por defecto (acceso completo).
        $controller->permissions = new ControllerPermissions();
        $this->invokeCreateViews($controller);

        $view = $controller->views['ListCliente'];

        // primero comprobamos con el helper: el modelo de ListCliente (Cliente) no es de OpenServBus.
        $this->assertFalse(
            AccumulatedSearchTitle::shouldEnrich($view),
            'shouldEnrich() debe rechazar la vista de Cliente por no pertenecer a OpenServBus'
        );

        $view->count = 1;
        $before = $view->title;

        $this->assertTrue($controller->pipeFalse('loadData', 'ListCliente', $view));

        $this->assertSame(
            $before,
            $view->title,
            'La extensión de OpenServBus no debe modificar el título de una lista del core'
        );
        $this->assertStringNotContainsString(
            '||',
            $view->title,
            'La lista de Cliente no debe llevar el sufijo de contadores de OpenServBus'
        );
    }

    /**
     * El pipe loadData real de ListVehicle debe añadir el sufijo "||count||total||campo:Etiqueta"
     * al título de la vista principal, con el selector de campo de sus tres searchFields.
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
