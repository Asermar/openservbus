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

namespace FacturaScripts\Plugins\OpenServBus\Worker;

use FacturaScripts\Core\Model\WorkEvent;
use FacturaScripts\Core\Template\WorkerClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\OpenServBus\Model\FuelKm;

/**
 * Recalcula en segundo plano las estadísticas (km recorridos y consumo) de
 * todos los repostajes. Se dispara desde el área de mantenimiento.
 */
class RecalculateFuelKmStats extends WorkerClass
{
    public function run(WorkEvent $event): bool
    {
        $total = FuelKm::recalcularTodas();
        Tools::log()->notice('statistics-recalculated', ['%count%' => $total]);
        return $this->done();
    }
}
