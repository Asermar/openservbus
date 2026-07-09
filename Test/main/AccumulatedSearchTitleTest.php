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
 * - `fieldLabels()`: incluye los searchFields que casan con una columna visible (por coincidencia
 *   directa o por sufijo tras el punto) y también los searchFields SIN columna (fase 2), sin
 *   duplicados y respetando el orden.
 * - `shouldEnrich()`: autoriza el enriquecido para modelos propios de OpenServBus (incluidos sus
 *   JoinModel); para modelos normales no reenriquece si el título ya tiene sufijo.
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
     * fieldLabels() debe ofrecer igualmente los searchFields SIN columna visible (fase 2), típicos
     * de un JoinModel, con la clave completa del searchField y una etiqueta traducida.
     */
    public function testFieldLabelsCampoSinColumnaSeOfreceIgual(): void
    {
        $labels = AccumulatedSearchTitle::fieldLabels([], ['cod_vehicle']);

        $this->assertCount(1, $labels, 'Un searchField sin columna debe ofrecerse en el selector');
        $this->assertStringStartsWith(
            'cod_vehicle:',
            $labels[0],
            'La clave del par debe ser el searchField completo, seguido de su etiqueta'
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

    /**
     * fieldLabels() para un JoinModel: casa los searchFields prefijados 'tabla.campo' con columnas
     * por el sufijo tras el punto (emitiendo la clave completa) y añade los que no tienen columna.
     */
    public function testFieldLabelsJoinPrefijadosYSinColumna(): void
    {
        $columns = [
            $this->makeColumn('km', 'titulo-ficticio-kms', false),
            $this->makeColumn('litros', 'titulo-ficticio-litros', false),
            $this->makeColumn('iddriver', 'titulo-ficticio-driver', false), // no es searchField
        ];
        $searchFields = ['fk.km', 'fk.litros', 'd.nombre_conductor'];

        $labels = AccumulatedSearchTitle::fieldLabels($columns, $searchFields);

        // km/litros casan por sufijo con su columna; nombre_conductor no tiene columna (fase 2).
        $this->assertSame('fk.km:titulo-ficticio-kms', $labels[0]);
        $this->assertSame('fk.litros:titulo-ficticio-litros', $labels[1]);
        $this->assertStringStartsWith('d.nombre_conductor:', $labels[2]);
        $this->assertCount(3, $labels);
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
     * shouldEnrich() debe autorizar un JoinModel AUNQUE su título ya esté enriquecido: se reconstruye
     * el sufijo para añadir sus campos sin columna, que BuscadorAcumulado no ofrece. Para un modelo
     * normal ya enriquecido, en cambio, devuelve false (testShouldEnrichFalseSiElTituloYaEstaEnriquecido).
     */
    public function testShouldEnrichTrueParaJoinModelAunqueTituloYaEnriquecido(): void
    {
        $view = $this->makeView(new FuelKmJoin(), 'refueling-kms||3||66||fk.km:Kms');

        $this->assertTrue(
            AccumulatedSearchTitle::shouldEnrich($view),
            'Un JoinModel ya enriquecido (p. ej. sufijo parcial de BuscadorAcumulado) debe reenriquecerse'
        );
    }

    /**
     * shouldEnrich() debe AUTORIZAR un JoinModel de OpenServBus. Caso real:
     * FacturaScripts\Dinamic\Model\Join\FuelKm extiende a
     * FacturaScripts\Plugins\OpenServBus\Model\Join\FuelKm (empieza por MODEL_NS) y, aunque no
     * expone primaryColumn()/tableName(), sí expone count(): eso basta para enriquecerlo (ver
     * ListFuelKm, que sustituye el modelo de su vista de importación por este JoinModel cuando
     * CSVimport está activo). Así el selector incluye sus campos sin columna visible.
     */
    public function testShouldEnrichTrueParaJoinModelDeOpenServBus(): void
    {
        $joinModel = new FuelKmJoin();

        // confirmamos la premisa: el padre del JoinModel cae bajo el namespace de OpenServBus...
        $this->assertStringStartsWith(
            AccumulatedSearchTitle::MODEL_NS,
            (string)get_parent_class($joinModel),
            'El padre de Dinamic\Model\Join\FuelKm debe caer bajo el namespace de modelos de OpenServBus'
        );
        // ...y, aunque no expone primaryColumn()/tableName(), sí expone count().
        $this->assertFalse(
            method_exists($joinModel, 'primaryColumn') && method_exists($joinModel, 'tableName'),
            'Un JoinModel no expone primaryColumn()/tableName()'
        );
        $this->assertTrue(
            method_exists($joinModel, 'count'),
            'Un JoinModel sí expone count()'
        );

        $view = $this->makeView($joinModel, 'refueling-kms');

        $this->assertTrue(
            AccumulatedSearchTitle::shouldEnrich($view),
            'shouldEnrich() debe autorizar un JoinModel de OpenServBus con título aún sin sufijo'
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
