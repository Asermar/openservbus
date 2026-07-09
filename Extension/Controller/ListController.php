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
 * EXTENSIÓN GENÉRICA DE ListController — integración con BuscadorAcumulado
 * ============================================================================
 * Registrada en Init.php SOLO cuando el plugin BuscadorAcumulado está activo
 * (Plugins::isEnabled). Se aplica a TODOS los controladores List* del sistema,
 * pero su pipe loadData solo actúa sobre las vistas de OpenServBus.
 *
 * Motivo: BuscadorAcumulado añade al título de la pestaña el sufijo
 * "||count||total||campo:Etiqueta..." (que alimenta los contadores "X de Y" y el
 * selector por campo), pero arma el selector cruzando searchFields con COLUMNAS
 * VISIBLES. Los campos calculados de un JoinModel (p. ej. conductor/vehículo/
 * surtidor en ListFuelKm) no tienen columna, así que quedan fuera del selector.
 * Aquí replicamos su formato exacto (ver Lib\AccumulatedSearchTitle) y añadimos
 * también esos campos sin columna, reconstruyendo el sufijo si hiciera falta.
 *
 * RESTRICCIÓN DEL SISTEMA DE EXTENSIONES: el core registra por Reflection todos
 * los métodos de esta clase como pipes y los invoca sin argumentos, así que la
 * clase SOLO puede tener métodos que devuelvan Closure. Toda la lógica auxiliar
 * vive en Lib\AccumulatedSearchTitle (además, así es unit-testeable).
 *
 * @mixin \FacturaScripts\Core\Lib\ExtendedController\ListController
 */

namespace FacturaScripts\Plugins\OpenServBus\Extension\Controller;

use Closure;
use FacturaScripts\Dinamic\Lib\AssetManager;
use FacturaScripts\Plugins\OpenServBus\Lib\AccumulatedSearchTitle;

class ListController
{
    /**
     * Pipe loadData: se ejecuta después de que la vista ha cargado sus datos
     * (el core ya ha fijado $view->count), en secuencia con los pipes de otros
     * plugins. Enriquece el título de las vistas de OpenServBus con el sufijo que
     * consume BuscadorAcumulado y carga el JS de contadores (BuscadorAcumulado.js
     * ya lo carga el propio plugin para todos los List* desde execPreviousAction;
     * SincronizaLineas.js solo lo carga en sus ramas de sync, por eso falta aquí).
     *
     * RECONSTRUCCIÓN DEL SUFIJO: componemos el sufijo sobre el título base (lo que
     * hay antes del primer '||'), no concatenando. Así, si BuscadorAcumulado ya
     * añadió un sufijo parcial a un JoinModel (solo los campos con columna visible),
     * lo recortamos y lo recomponemos con la lista COMPLETA — incluidos los campos
     * sin columna (conductor/vehículo/surtidor). El orden de ejecución de los pipes
     * de ambos plugins no es controlable, y esto hace que nuestra lista gane sea cual
     * sea ese orden; el guard strpos('||') de BuscadorAcumulado evita que reañada.
     * Para modelos normales sin sufijo previo, la base es el propio título (el
     * resultado es idéntico a concatenar).
     */
    public function loadData(): Closure
    {
        return function (string $viewName, $view) {
            if (false === AccumulatedSearchTitle::shouldEnrich($view)) {
                return;
            }

            $baseTitle = explode('||', (string)$view->title, 2)[0];
            $fieldLabels = AccumulatedSearchTitle::fieldLabels($view->getColumns(), $view->searchFields);
            $total = (int)$view->model->count();
            $view->title = $baseTitle . AccumulatedSearchTitle::buildSuffix((int)$view->count, $total, $fieldLabels);

            AssetManager::addJs(FS_ROUTE . '/Plugins/BuscadorAcumulado/Assets/JS/SincronizaLineas.js');
        };
    }
}
