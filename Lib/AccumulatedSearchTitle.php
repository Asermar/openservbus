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
 * BuscadorAcumulado v2.61 ya compone ese sufijo para toda vista con searchFields,
 * pero su selector de campo lo construye cruzando los searchFields con las COLUMNAS
 * VISIBLES de la vista (Extension/Controller/ListController.php: BAFields::build).
 * Por eso los searchFields que NO tienen columna visible quedan fuera del selector.
 * Ocurre en dos situaciones habituales de OpenServBus:
 *   - JoinModel: campos calculados de tablas unidas (p. ej. 'd.nombre_conductor' en
 *     Model/Join/FuelKm, listado ListFuelKm).
 *   - modelos normales: campos reales que se buscan pero no se muestran como columna
 *     (p. ej. 'provincia'/'codpostal' en ListStop, 'direccion' en ListEmployeeOpen).
 * Este helper reproduce el formato EXACTO de BuscadorAcumulado y, además, ofrece en
 * el selector esos searchFields SIN columna (fase 2 de fieldLabels), de modo que la
 * extensión de OpenServBus complete lo que BuscadorAcumulado no puede, para cualquier
 * vista propia (con modelo normal o JoinModel).
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
     * partir de las columnas de la vista y sus searchFields. La clave emitida es
     * siempre el searchField COMPLETO (prefijado 'tabla.campo' en un JoinModel)
     * para que el WHERE generado sea SQL válido. Dos fases:
     *
     *   1. searchFields que casan con una columna VISIBLE — por coincidencia
     *      directa o por sufijo tras el punto (JoinModel) — con la etiqueta de la
     *      columna. Réplica de BAFields::build de BuscadorAcumulado.
     *   2. searchFields SIN columna visible (campos calculados de un JoinModel,
     *      p. ej. 'd.nombre_conductor'): se ofrecen igualmente, con etiqueta
     *      Tools::trans() del nombre de campo sin el prefijo 'tabla.'. Esto es lo
     *      que BuscadorAcumulado no hace y por lo que OpenServBus lo completa.
     *
     * Sin duplicados y respetando el orden de las columnas (fase 1) y de los
     * searchFields (fase 2).
     *
     * @param array $columns columnas de la vista ($view->getColumns())
     * @param array $searchFields campos de búsqueda de la vista ($view->searchFields)
     * @return string[] lista de "searchField:Etiqueta"
     */
    public static function fieldLabels(array $columns, array $searchFields): array
    {
        $sFields = self::normalizeSearchFields($searchFields);
        $labels = [];
        $seen = [];

        // Fase 1: searchFields con columna visible (match directo o por sufijo tras el punto).
        foreach ($columns as $col) {
            if (method_exists($col, 'hidden') && $col->hidden()) {
                continue;
            }
            $fn = $col->widget->fieldname ?? '';
            if ($fn === '') {
                continue;
            }
            $match = null;
            foreach ($sFields as $sf) {
                if ($sf === $fn) {
                    $match = $sf;
                    break;
                }
                $dot = strrpos($sf, '.');
                if ($dot !== false && substr($sf, $dot + 1) === $fn) {
                    $match = $sf;
                    break;
                }
            }
            if ($match !== null && !isset($seen[$match])) {
                $labels[] = $match . ':' . Tools::trans($col->title);
                $seen[$match] = true;
            }
        }

        // Fase 2: searchFields sin columna visible (JoinModel o campos no mostrados).
        foreach (self::searchFieldsWithoutColumn($columns, $searchFields) as $sf) {
            if (isset($seen[$sf])) {
                continue;
            }
            $dot = strrpos($sf, '.');
            $key = $dot !== false ? substr($sf, $dot + 1) : $sf;
            $labels[] = $sf . ':' . Tools::trans($key);
            $seen[$sf] = true;
        }

        return $labels;
    }

    /**
     * Devuelve los searchFields que NO casan con ninguna columna visible de la vista
     * (ni por coincidencia directa ni por sufijo tras el punto). Son los que
     * BuscadorAcumulado deja fuera del selector y que OpenServBus completa: campos
     * calculados de un JoinModel o campos reales que la vista no muestra como columna.
     *
     * @param array $columns columnas de la vista ($view->getColumns())
     * @param array $searchFields campos de búsqueda de la vista ($view->searchFields)
     * @return string[] searchFields completos sin columna visible asociada
     */
    public static function searchFieldsWithoutColumn(array $columns, array $searchFields): array
    {
        $visible = self::visibleFieldnames($columns);
        $out = [];
        foreach (self::normalizeSearchFields($searchFields) as $sf) {
            $dot = strrpos($sf, '.');
            $suffix = $dot !== false ? substr($sf, $dot + 1) : $sf;
            if (!isset($visible[$sf]) && !isset($visible[$suffix])) {
                $out[] = $sf;
            }
        }
        return $out;
    }

    /**
     * Decide si el título de la vista debe enriquecerse:
     *   - el modelo debe provenir de OpenServBus (su clase padre está en MODEL_NS;
     *     en runtime $view->model es un FacturaScripts\Dinamic\Model\Xxx cuyo padre
     *     es FacturaScripts\Plugins\OpenServBus\Model\Xxx). Esto incluye a los
     *     JoinModel del plugin, cuyo padre es Model\Join\Xxx (empieza por MODEL_NS),
     *     y descarta los modelos del core u otros plugins.
     *   - el modelo debe exponer count() (lo cumplen tanto ModelClass como
     *     Core\Template\JoinModel, con firma estática).
     *   - si el título YA está enriquecido (contiene '||'), solo reenriquecemos cuando
     *     aportamos algo que BuscadorAcumulado no puede: searchFields sin columna
     *     visible. Si no los hay, respetamos el sufijo existente. La reconstrucción
     *     (recorte + recomposición) la hace el pipe loadData.
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

        if (!method_exists($view->model, 'count')) {
            return false;
        }

        if (strpos((string)$view->title, '||') !== false) {
            return self::viewHasSearchFieldsWithoutColumn($view);
        }

        return true;
    }

    /** Normaliza searchFields (permiten separador '|' interno), sin vacíos ni duplicados. */
    private static function normalizeSearchFields(array $searchFields): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode('|', implode('|', $searchFields))))));
    }

    /** True si la vista expone columnas/searchFields y alguno de estos no tiene columna. */
    private static function viewHasSearchFieldsWithoutColumn($view): bool
    {
        if (!method_exists($view, 'getColumns') || !isset($view->searchFields) || !is_array($view->searchFields)) {
            return false;
        }
        return [] !== self::searchFieldsWithoutColumn($view->getColumns(), $view->searchFields);
    }

    /** Conjunto (fieldname => true) de las columnas VISIBLES de la vista. */
    private static function visibleFieldnames(array $columns): array
    {
        $out = [];
        foreach ($columns as $col) {
            if (method_exists($col, 'hidden') && $col->hidden()) {
                continue;
            }
            $fn = $col->widget->fieldname ?? '';
            if ($fn !== '') {
                $out[$fn] = true;
            }
        }
        return $out;
    }
}
