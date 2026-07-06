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
use FacturaScripts\Plugins\OpenServBus\Controller\ListFuelKm;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Escenario CON el plugin CSVimport activado (install-plugins.txt = OpenServBus,CSVimport).
 *
 * @description
 * ## Importación de repostajes — CSVimport **activado**
 *
 * Verifica que, con `CSVimport` activado, la importación de repostajes en `ListFuelKm`:
 *
 * 1. Queda **disponible** (`csvImportAvailable()` es `true`).
 * 2. `Init::init()` registra la plantilla manual `FuelKm` en `CSVfile`.
 */
final class CsvImportPresentTest extends TestCase
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
     * Invariante de regresión válida en cualquier estado: la disponibilidad de la
     * importación debe coincidir exactamente con el estado "activado" del plugin.
     */
    public function testDetectionMatchesEnabledState(): void
    {
        $this->assertSame(
            Plugins::isEnabled('CSVimport'),
            $this->csvImportAvailable(),
            'csvImportAvailable() debe reflejar Plugins::isEnabled(\'CSVimport\')'
        );
    }

    /** Con CSVimport activado, la importación de repostajes debe estar disponible. */
    public function testImportEnabledWhenCsvImportEnabled(): void
    {
        // precondición de la suite: CSVimport está activado (install-plugins.txt lo incluye).
        $this->assertTrue(
            Plugins::isEnabled('CSVimport'),
            'Esta suite (Test/with-csvimport) debe ejecutarse con CSVimport activado'
        );

        $this->assertTrue(
            class_exists('\FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools'),
            'Las clases de CSVimport deben estar disponibles cuando el plugin está activado'
        );

        $this->assertTrue(
            $this->csvImportAvailable(),
            'Con CSVimport activado, la importación de repostajes debe estar disponible'
        );
    }

    /**
     * Init::init() de OpenServBus registra la plantilla manual "FuelKm" en CSVfile
     * cuando CSVimport está activado. El init ya se ejecutó durante el arranque de
     * FacturaScripts (Plugins::init), así que la plantilla debe estar registrada.
     */
    public function testInitRegistersFuelKmTemplate(): void
    {
        $this->assertTrue(
            Plugins::isEnabled('CSVimport'),
            'Esta suite (Test/with-csvimport) debe ejecutarse con CSVimport activado'
        );

        $templates = \FacturaScripts\Plugins\CSVimport\Model\CSVfile::getManualTemplates();
        $this->assertArrayHasKey(
            ListFuelKm::IMPORT_PROFILE,
            $templates,
            'Init::init() debe registrar la plantilla manual del perfil FuelKm en CSVfile'
        );
    }

    /** Instancia el controlador e invoca el método protegido csvImportAvailable(). */
    private function csvImportAvailable(): bool
    {
        $controller = new ListFuelKm('ListFuelKm');
        $method = new ReflectionMethod($controller, 'csvImportAvailable');
        $method->setAccessible(true);

        return (bool)$method->invoke($controller);
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
