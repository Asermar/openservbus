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
 * Renombra la tabla `employees` a `employees_open` (v3.1): `employees` colisionaba
 * con otro plugin. Solo actúa en instalaciones legadas donde aún exista la tabla
 * antigua y no exista ya la nueva (guarda añadida por robustez).
 *
 * Antes vivía en Init::changeNameEmployee() y se reintentaba en cada update();
 * ahora se ejecuta una sola vez (registrada en MyFiles/migrations.json).
 *
 * @author Alexis Serafín <alexis@okodex.com>
 */
class RenameEmployeesTable extends MigrationClass
{
    const MIGRATION_NAME = 'rename_employees_table_v3.1';

    public function run(): void
    {
        $db = $this->db();

        if ($db->tableExists('employees') && false === $db->tableExists('employees_open')) {
            $db->exec('ALTER TABLE employees RENAME employees_open');
        }
    }
}
