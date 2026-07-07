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

use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Join\FuelKm as FuelKmJoin;
use FacturaScripts\Dinamic\Model\Vehicle;
use FacturaScripts\Plugins\OpenServBus\Lib\AccumulatedSearchTitle;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;

/**
 * @description
 * ## Sufijo de búsqueda acumulada — helper puro `AccumulatedSearchTitle`
 *
 * Tests unitarios (sin depender del plugin `BuscadorAcumulado`, se ejecutan siempre) de la
 * lógica pura que replica el formato de título `Título||count||total||campo:Etiqueta...` que
 * consume ese plugin:
 *
 * - `buildSuffix()`: formato exacto del sufijo, con y sin selector de campo.
 * - `fieldLabels()`: filtra columnas visibles cuyo campo esté en `searchFields`, sin duplicados
 *   y respetando el orden de las columnas.
 * - `shouldEnrich()`: solo autoriza el enriquecido para modelos propios de OpenServBus con un
 *   título aún sin sufijo.
 */
final class AccumulatedSearchTitleTest extends TestCase
{
    use LogErrorsTrait;

    /** buildSuffix() con selector de campo: añade el bloque "||campo:Etiqueta..." final. */
    public function testBuildSuffixConEtiquetasDeCampo(): void
    {
        $suffix = AccumulatedSearchTitle::buildSuffix(3, 66, ['cod_vehicle:Código', 'nombre:Nombre']);

        $this->assertSame(
            '||3||66||cod_vehicle:Código||nombre:Nombre',
            $suffix,
            'El sufijo con etiquetas de campo debe respetar el formato exacto que consume BuscadorAcumulado'
        );
    }

    /** buildSuffix() sin etiquetas de campo: solo el bloque "||count||total", sin el "||" final extra. */
    public function testBuildSuffixSinEtiquetasDeCampo(): void
    {
        $suffix = AccumulatedSearchTitle::buildSuffix(3, 66, []);

        $this->assertSame(
            '||3||66',
            $suffix,
            'Sin etiquetas de campo, el sufijo no debe añadir un "||" final vacío'
        );
    }

    /**
     * fieldLabels() debe incluir solo las columnas VISIBLES cuyo fieldname está en searchFields,
     * excluir las ocultas, excluir las que no son searchFields, y respetar el orden de columnas.
     */
    public function testFieldLabelsFiltraVisiblesYSearchFields(): void
    {
        $columns = [
            $this->makeColumn('cod_vehicle', 'titulo-ficticio-code', false),
            $this->makeColumn('nombre', 'titulo-ficticio-name', false),
            $this->makeColumn('idvehicle', 'titulo-ficticio-id', true), // oculta: se excluye
            $this->makeColumn('matricula', 'titulo-ficticio-plate', false),
            $this->makeColumn('fechaalta', 'titulo-ficticio-date', false), // visible pero no es searchField
        ];
        $searchFields = ['cod_vehicle', 'nombre', 'matricula'];

        $labels = AccumulatedSearchTitle::fieldLabels($columns, $searchFields);

        $this->assertSame(
            [
                'cod_vehicle:titulo-ficticio-code',
                'nombre:titulo-ficticio-name',
                'matricula:titulo-ficticio-plate',
            ],
            $labels,
            'Debe incluir solo las columnas visibles que son searchFields, en el orden de las columnas'
        );
    }

    /** fieldLabels() con una lista de columnas vacía debe devolver una lista vacía. */
    public function testFieldLabelsListaVacia(): void
    {
        $this->assertSame(
            [],
            AccumulatedSearchTitle::fieldLabels([], ['cod_vehicle']),
            'Sin columnas no puede haber etiquetas'
        );
    }

    /** fieldLabels() no debe duplicar la etiqueta cuando dos columnas comparten fieldname. */
    public function testFieldLabelsSinDuplicados(): void
    {
        $columns = [
            $this->makeColumn('cod_vehicle', 'titulo-ficticio-primero', false),
            $this->makeColumn('cod_vehicle', 'titulo-ficticio-repetido', false),
        ];
        $searchFields = ['cod_vehicle'];

        $labels = AccumulatedSearchTitle::fieldLabels($columns, $searchFields);

        $this->assertSame(
            ['cod_vehicle:titulo-ficticio-primero'],
            $labels,
            'No debe duplicar la etiqueta de un mismo fieldname aunque haya varias columnas'
        );
    }

    /**
     * shouldEnrich() debe rechazar un JoinModel aunque su clase padre caiga bajo el namespace de
     * OpenServBus (MODEL_NS). Caso real: FacturaScripts\Dinamic\Model\Join\FuelKm extiende a
     * FacturaScripts\Plugins\OpenServBus\Model\Join\FuelKm (empieza por MODEL_NS), pero al
     * extender a su vez de Core\Template\JoinModel no expone primaryColumn()/tableName(), que es
     * precisamente lo que usa shouldEnrich() para excluir los JoinModel (ver ListFuelKm, que
     * sustituye el modelo de su vista de importación por este JoinModel cuando CSVimport está
     * activo; BuscadorAcumulado tampoco enriquece JoinModel).
     */
    public function testShouldEnrichFalseParaJoinModelDeOpenServBus(): void
    {
        $joinModel = new FuelKmJoin();

        // confirmamos la premisa: el padre del JoinModel cae bajo el namespace de OpenServBus...
        $this->assertStringStartsWith(
            AccumulatedSearchTitle::MODEL_NS,
            (string)get_parent_class($joinModel),
            'El padre de Dinamic\Model\Join\FuelKm debe caer bajo el namespace de modelos de OpenServBus'
        );
        // ...pero no expone primaryColumn()/tableName(), a diferencia de un ModelClass normal.
        $this->assertFalse(
            method_exists($joinModel, 'primaryColumn') && method_exists($joinModel, 'tableName'),
            'Un JoinModel no debe exponer primaryColumn()/tableName()'
        );

        $view = $this->makeView($joinModel, 'refueling-kms');

        $this->assertFalse(
            AccumulatedSearchTitle::shouldEnrich($view),
            'shouldEnrich() debe excluir los JoinModel aunque su padre esté en el namespace de OpenServBus'
        );
    }

    /** shouldEnrich() debe rechazar modelos que no pertenecen a OpenServBus (p. ej. el core). */
    public function testShouldEnrichFalseParaModeloAjenoAOpenServBus(): void
    {
        $view = $this->makeView(new Cliente(), 'customers');

        $this->assertFalse(
            AccumulatedSearchTitle::shouldEnrich($view),
            'No debe enriquecerse un modelo que no pertenece a OpenServBus'
        );
    }

    /** shouldEnrich() debe rechazar un título que ya contiene el separador "||" (guarda anti-duplicado). */
    public function testShouldEnrichFalseSiElTituloYaEstaEnriquecido(): void
    {
        $view = $this->makeView(new Vehicle(), 'vehicles||3||66');

        $this->assertFalse(
            AccumulatedSearchTitle::shouldEnrich($view),
            'No debe reenriquecer un título que ya contiene el separador "||"'
        );
    }

    /** shouldEnrich() debe rechazar una vista sin modelo asignado. */
    public function testShouldEnrichFalseSinModelo(): void
    {
        $view = $this->makeView(null, 'vehicles');

        $this->assertFalse(
            AccumulatedSearchTitle::shouldEnrich($view),
            'Sin modelo asignado no se puede determinar el namespace, así que no debe enriquecerse'
        );
    }

    /**
     * shouldEnrich() debe autorizar el enriquecido para un modelo real de OpenServBus
     * (su clase padre está en el namespace FacturaScripts\Plugins\OpenServBus\Model\)
     * cuando el título todavía no tiene el sufijo "||".
     */
    public function testShouldEnrichTrueParaModeloDeOpenServBus(): void
    {
        $view = $this->makeView(new Vehicle(), 'vehicles');

        $this->assertTrue(
            AccumulatedSearchTitle::shouldEnrich($view),
            'Debe enriquecerse una vista de un modelo de OpenServBus con título aún sin sufijo'
        );
    }

    /** Crea un stub de columna con las propiedades/métodos mínimos que usa fieldLabels(). */
    private function makeColumn(string $fieldname, string $title, bool $hidden): object
    {
        return new class($fieldname, $title, $hidden) {
            public $title;
            public $widget;
            private $hiddenFlag;

            public function __construct(string $fieldname, string $title, bool $hidden)
            {
                $this->title = $title;
                $this->hiddenFlag = $hidden;
                $this->widget = new class($fieldname) {
                    public $fieldname;

                    public function __construct(string $fieldname)
                    {
                        $this->fieldname = $fieldname;
                    }
                };
            }

            public function hidden(): bool
            {
                return $this->hiddenFlag;
            }
        };
    }

    /** Crea un stub de $view con las propiedades públicas mínimas que usa shouldEnrich(). */
    private function makeView($model, string $title): object
    {
        return new class($model, $title) {
            public $model;
            public $title;

            public function __construct($model, string $title)
            {
                $this->model = $model;
                $this->title = $title;
            }
        };
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
