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
 * Escenario SIN el plugin CSVimport activado (install-plugins.txt = OpenServBus).
 *
 * @description
 * ## Importación de repostajes — CSVimport **desactivado**
 *
 * Verifica que, con el plugin `CSVimport` desactivado, la funcionalidad de importación de
 * repostajes de `ListFuelKm` **no** se incluye:
 *
 * - `csvImportAvailable()` refleja `Plugins::isEnabled('CSVimport')`.
 * - Aunque las clases de CSVimport existan en disco, la importación queda **off**.
 */
final class CsvImportAbsentTest extends TestCase
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

    /**
     * Esta suite se ejecuta con CSVimport desactivado (install-plugins.txt = OpenServBus).
     * Confirma que, en ese escenario, la funcionalidad de importación NO se incluye.
     * Si la importación estuviera presente, el test debe fallar (no omitirse).
     */
    public function testImportDisabledWhenCsvImportNotEnabled(): void
    {
        // precondición de la suite: CSVimport no está activado.
        $this->assertFalse(
            Plugins::isEnabled('CSVimport'),
            'Esta suite (Test/main) debe ejecutarse con CSVimport desactivado'
        );

        // las clases de CSVimport siguen existiendo en disco (por eso la comprobación
        // antigua con class_exists daba un falso positivo)...
        $this->assertTrue(
            class_exists('\FacturaScripts\Plugins\CSVimport\Model\CSVfile'),
            'Las clases de CSVimport deberían existir en disco aunque el plugin esté desactivado'
        );

        // ...pero la funcionalidad de importación debe quedar desactivada.
        $this->assertFalse(
            $this->csvImportAvailable(),
            'Con CSVimport desactivado, la importación de repostajes no debe estar disponible'
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
