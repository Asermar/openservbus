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
 *
 * ============================================================================
 * PROPÓSITO
 * ============================================================================
 * Lógica pura (sin dependencias del ciclo de request) que enriquece el título
 * de una vista de lista con el sufijo que consume el plugin de terceros
 * BuscadorAcumulado para pintar los contadores "X de Y" y el selector por campo:
 *
 *     Título||count||total||campo:Etiqueta||campo:Etiqueta...
 *
 * BuscadorAcumulado solo construye ese sufijo para las vistas que reconoce
 * (líneas de documento, recibos, variantes, stock, contacto, cuentabanco) y hace
 * un `return` temprano para cualquier otra vista. Los listados standalone de
 * OpenServBus (ListVehicle, etc.) se quedan sin ese sufijo → sin contadores ni
 * selector de campo. Este helper reproduce el formato EXACTO de BuscadorAcumulado
 * (Extension/Controller/ListController.php: buildFieldLabels y el append del title)
 * para que la extensión de OpenServBus lo aplique a sus propias vistas.
 *
 * Se aísla aquí (y no como método de la clase de extensión) por dos motivos:
 *   1. El sistema de extensiones registra por Reflection TODOS los métodos de la
 *      clase de extensión como pipes y los invoca sin argumentos: solo pueden
 *      existir métodos que devuelvan Closure. Cualquier helper debe ir fuera.
 *   2. Al ser lógica pura es directamente testeable por unit tests.
 * ============================================================================
 */

namespace FacturaScripts\Plugins\OpenServBus\Lib;

use FacturaScripts\Core\Tools;

final class AccumulatedSearchTitle
{
    /** Prefijo de namespace de los modelos propios de OpenServBus. */
    public const MODEL_NS = 'FacturaScripts\\Plugins\\OpenServBus\\Model\\';

    /**
     * Construye el sufijo "||count||total[||campo:Etiqueta...]" con el formato
     * exacto que espera el JS de BuscadorAcumulado (SincronizaLineas.js para los
     * contadores; getFields() de BuscadorAcumulado.js para el selector).
     *
     * @param int $count registros del resultado actual (filtrado)
     * @param int $total registros totales sin filtros
     * @param string[] $fieldLabels lista de "fieldname:Etiqueta" (puede ir vacía)
     */
    public static function buildSuffix(int $count, int $total, array $fieldLabels): string
    {
        $suffix = '||' . $count . '||' . $total;
        if (!empty($fieldLabels)) {
            $suffix .= '||' . implode('||', $fieldLabels);
        }
        return $suffix;
    }

    /**
     * Construye la lista de pares "campo:Etiqueta" para el selector de campo, a
     * partir de las columnas de la vista y sus searchFields. Solo se incluyen los
     * searchFields que son columnas VISIBLES (con su etiqueta traducida), sin
     * duplicados y respetando el orden de las columnas. Réplica de buildFieldLabels
     * de BuscadorAcumulado.
     *
     * @param array $columns columnas de la vista ($view->getColumns())
     * @param array $searchFields campos de búsqueda de la vista ($view->searchFields)
     * @return string[] lista de "fieldname:Etiqueta"
     */
    public static function fieldLabels(array $columns, array $searchFields): array
    {
        $sFields = array_unique(array_filter(array_map('trim', explode('|', implode('|', $searchFields)))));
        $labels = [];
        $seen = [];
        foreach ($columns as $col) {
            if (method_exists($col, 'hidden') && $col->hidden()) {
                continue;
            }
            $fn = $col->widget->fieldname ?? '';
            if ($fn !== '' && in_array($fn, $sFields, true) && !isset($seen[$fn])) {
                $labels[] = $fn . ':' . Tools::trans($col->title);
                $seen[$fn] = true;
            }
        }
        return $labels;
    }

    /**
     * Decide si el título de la vista debe enriquecerse:
     *   - el modelo debe provenir de OpenServBus (su clase padre está en MODEL_NS;
     *     en runtime $view->model es un FacturaScripts\Dinamic\Model\Xxx cuyo padre
     *     es FacturaScripts\Plugins\OpenServBus\Model\Xxx). Esto descarta además los
     *     JoinModel y los modelos del core u otros plugins.
     *   - el título no debe estar ya enriquecido (sin '||'), para no pisar el sufijo
     *     que pudiera haber añadido BuscadorAcumulado u otra pasada del pipe.
     *   - el modelo debe ser un ModelClass respaldado por tabla: exponer count(),
     *     primaryColumn() y tableName(). Esto EXCLUYE los JoinModel, que definen
     *     count() pero no primaryColumn()/tableName() — BuscadorAcumulado tampoco es
     *     compatible con JoinModel (los excluye igual en ListController.php:821), así
     *     que dejamos fuera vistas como la de importación de ListFuelKm.
     */
    public static function shouldEnrich($view): bool
    {
        if (!is_object($view) || !isset($view->model) || !is_object($view->model)) {
            return false;
        }

        $parent = get_parent_class($view->model);
        if (!is_string($parent) || strpos($parent, self::MODEL_NS) !== 0) {
            return false;
        }

        if (strpos((string)$view->title, '||') !== false) {
            return false;
        }

        return method_exists($view->model, 'count')
            && method_exists($view->model, 'primaryColumn')
            && method_exists($view->model, 'tableName');
    }
}
