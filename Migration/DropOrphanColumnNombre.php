<?php
/**
 * This file is part of OpenServBus plugin for FacturaScripts
 * Copyright (C) 2021-2026 Carlos Garcia Gomez            <carlos@facturascripts.com>
 * Copyright (C) 2021-2026 Jerónimo Pedro Sánchez Manzano <socger@gmail.com>
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

namespace FacturaScripts\Plugins\OpenServBus\Migration;

use FacturaScripts\Core\Template\MigrationClass;

/**
 * Elimina la columna huérfana `nombre` de las tablas donde quedó tras la v3.0.
 * NOTA: 'drivers' NO se incluye: drivers.nombre es una columna vigente
 * (Table/drivers.xml, poblada por Driver::test(), usada por el Join de
 * OSBFuelImport en ListFuelKm). Borrarla rompe ese listado.
 *
 * Antes vivía en Init::deleteColumnFromTable() y se reintentaba en cada update();
 * ahora se ejecuta una sola vez (registrada en MyFiles/migrations.json).
 *
 * @author Alexis Serafín <alexis@okodex.com>
 */
class DropOrphanColumnNombre extends MigrationClass
{
    const MIGRATION_NAME = 'drop_orphan_column_nombre_v3.0';

    public function run(): void
    {
        $db = $this->db();
        $tables = ['employee_contracts', 'employees_attendance_management_yn', 'helpers', 'collaborators'];

        foreach ($tables as $table) {
            if (false === $db->tableExists($table)) {
                continue;
            }
            foreach ($db->getColumns($table) as $column) {
                if ('nombre' === $column['name']) {
                    $db->exec('ALTER TABLE ' . $table . ' DROP COLUMN nombre');
                }
            }
        }
    }
}
