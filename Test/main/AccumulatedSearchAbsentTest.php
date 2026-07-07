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
 * Escenario SIN el plugin BuscadorAcumulado activado (install-plugins.txt = OpenServBus).
 *
 * @description
 * ## Sufijo de búsqueda acumulada — BuscadorAcumulado **desactivado**
 *
 * Verifica que, con `BuscadorAcumulado` desactivado, `Init::init()` de OpenServBus **no**
 * registra `Extension\Controller\ListController` y, por tanto, el pipe `loadData` real de
 * `ListVehicle` **no** añade el sufijo `||count||total||campo:Etiqueta...` al título de la vista.
 */
final class AccumulatedSearchAbsentTest extends TestCase
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
     * Precondición de la suite: BuscadorAcumulado no está activo (install-plugins.txt de esta
     * carpeta solo contiene OpenServBus).
     */
    public function testBuscadorAcumuladoDesactivado(): void
    {
        $this->assertFalse(
            Plugins::isEnabled('BuscadorAcumulado'),
            'Esta suite (Test/main) debe ejecutarse con BuscadorAcumulado desactivado'
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
        $this->createViews($controller);

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
     * Invariante de registro/gating: con BuscadorAcumulado desactivado, la extensión no está
     * enganchada al pipe loadData, así que ejecutar el pipe real de ListVehicle deja el título
     * de la vista SIN el separador "||" (no se enriquece).
     */
    public function testPipeLoadDataNoEnriqueceElTituloSinBuscadorAcumulado(): void
    {
        $controller = new \FacturaScripts\Dinamic\Controller\ListVehicle('ListVehicle');
        $this->createViews($controller);

        $view = $controller->views['ListVehicle'];
        $view->count = 3;
        $before = $view->title;

        $this->assertTrue($controller->pipeFalse('loadData', 'ListVehicle', $view));

        $this->assertSame(
            $before,
            $view->title,
            'Sin BuscadorAcumulado activo, el pipe loadData no debe modificar el título de la vista'
        );
        $this->assertStringNotContainsString(
            '||',
            $view->title,
            'Sin BuscadorAcumulado activo, el título no debe llevar el sufijo de contadores'
        );
    }

    /** Invoca createViews() (protegido) del controlador por reflexión para poblar $controller->views. */
    private function createViews($controller): void
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
