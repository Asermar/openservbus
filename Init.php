<?php
/**
 * This file is part of OpenServBus plugin for FacturaScripts
 * Copyright (C) 2021-2026 Carlos Garcia Gomez            <carlos@facturascripts.com>
 * Copyright (C) 2021-2026      Jerónimo Pedro Sánchez Manzano <socger@gmail.com>
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

namespace FacturaScripts\Plugins\OpenServBus;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Cache;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Where;
use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Core\WorkQueue;
use FacturaScripts\Dinamic\Lib\Maintenance;
use FacturaScripts\Dinamic\Model\Role;
use FacturaScripts\Dinamic\Model\RoleAccess;
use FacturaScripts\Dinamic\Model\Service;
use FacturaScripts\Dinamic\Model\ServiceRegular;

final class Init extends InitClass
{
    const ROLE_NAME = 'OpenServbus';

    public function init(): void
    {
        // se ejecuta cada vez que carga FacturaScripts (si este plugin está activado).
        $this->loadExtension(new Extension\Controller\EditRole());
        $this->loadExtension(new Extension\Controller\EditUser());

        // importación de repostajes desde CSV: solo si el plugin CSVimport está activado
        // (declarado como "compatible" en facturascripts.ini).
        if (Plugins::isEnabled('CSVimport')) {
            \FacturaScripts\Plugins\CSVimport\Model\CSVfile::addManualTemplate(
                'FuelKm',
                new \FacturaScripts\Plugins\OpenServBus\Lib\ManualTemplates\KmsManual()
            );
        }

        // área de mantenimiento: registramos el proceso de recálculo de estadísticas
        // de repostajes y su worker (se ejecuta en segundo plano al pulsar el botón).
        WorkQueue::addWorker('RecalculateFuelKmStats', 'OpenServBus.recalculate-fuelkm-stats');
        Maintenance::addJob([
            'event' => 'OpenServBus.recalculate-fuelkm-stats',
            'label' => 'recalculate-statistics',
            'help' => 'recalculate-statistics-help',
            'icon' => 'fa-solid fa-calculator',
            'color' => 'warning',
            'confirm' => true,
        ]);
    }

    public function uninstall(): void
    {
    }

    public function update(): void
    {
        new Service();
        new ServiceRegular();
        $this->deleteColumnFromTable();
        $this->changeNameEmployee();
        $this->createRoleForPlugin();

        // limpiamos la caché para que se regenere la lista de campos de los modelos
        // tras sincronizar columnas nuevas (p.ej. estadísticas de repostajes)
        Cache::clear();
    }

    protected function changeNameEmployee(): void
    {
        // cambiamos el nombre de la tabla employees por employees_open
        // al actualizar a la versión 3.1
        $dataBase = new DataBase();
        if ($dataBase->tableExists('employees')) {
            $sql = "ALTER TABLE employees RENAME employees_open";
            $dataBase->exec($sql);
        }
    }

    protected function createRoleForPlugin(): void
    {
        new Role();
        new RoleAccess();

        $dataBase = new DataBase();
        $dataBase->beginTransaction();

        // creates the role if not exists
        $role = new Role();
        if (false === $role->load(self::ROLE_NAME)) {
            $role->codrole = $role->descripcion = self::ROLE_NAME;
            if (false === $role->save()) {
                // rollback and exit on fail
                $dataBase->rollback();
                return;
            }
        }

        // checks the role permissions
        $nameControllers = [
            'ConfigOpenServBus',
            'EditAbsenceReason',
            'EditAdvertismentUser',
            'EditCollaborator',
            'EditDepartment',
            'EditDocumentationType',
            'EditDriver',
            'EditEmployeeAttendanceManagement',
            'EditEmployeeAttendanceManagementYn',
            'EditEmployeeContract',
            'EditEmployeeContractType',
            'EditEmployeeDocumentation',
            'EditEmployeeOpen',
            'EditFuelKm',
            'EditFuelPump',
            'EditFuelType',
            'EditGarage',
            'EditHelper',
            'EditIdentificationMean',
            'EditService',
            'EditServiceAssembly',
            'EditServiceItinerary',
            'EditServiceRegular',
            'EditServiceRegularCombination',
            'EditServiceRegularCombinationServ',
            'EditServiceRegularItinerary',
            'EditServiceRegularPeriod',
            'EditServiceRegularValuation',
            'EditServiceType',
            'EditServiceValuation',
            'EditServiceValuationType',
            'EditStop',
            'EditTarjeta',
            'EditTarjetaType',
            'EditUser',
            'EditVehicle',
            'EditVehicleDocumentation',
            'EditVehicleEquipament',
            'EditVehicleEquipamentType',
            'EditVehicleType',
            'ListAdvertismentUser',
            'ListDriver',
            'ListEmployeeAttendanceManagement',
            'ListEmployeeOpen',
            'ListFuelKm',
            'ListHelper',
            'ListService',
            'ListServiceAssembly',
            'ListServiceRegular',
            'ListTarjeta',
            'ListVehicle',
            'ListVehicleDocumentation'
        ];
        foreach ($nameControllers as $nameController) {
            $roleAccess = new RoleAccess();
            $where = [
                Where::eq('codrole', self::ROLE_NAME),
                Where::eq('pagename', $nameController)
            ];
            if ($roleAccess->loadWhere($where)) {
                // permission exists? Then skip
                continue;
            }

            // creates the permission if not exists
            $roleAccess->allowdelete = true;
            $roleAccess->allowupdate = true;
            $roleAccess->codrole = self::ROLE_NAME;
            $roleAccess->pagename = $nameController;
            $roleAccess->onlyownerdata = false;
            if (false === $roleAccess->save()) {
                // rollback and exit on fail
                $dataBase->rollback();
                return;
            }
        }

        // without problems = Commit
        $dataBase->commit();
    }

    protected function deleteColumnFromTable(): void
    {
        // eliminamos las columnas deseadas de las tablas seleccionadas
        // al actualizar a la versión 3.0
        // NOTA: 'drivers' NO se incluye: drivers.nombre es una columna vigente
        // (definida en Table/drivers.xml y poblada por Driver::test()) que usa el
        // Join de OSBFuelImport en ListFuelKm. Borrarla rompe ese listado.
        $dataBase = new DataBase();
        $columns = ['nombre'];
        $tables = ['employee_contracts', 'employees_attendance_management_yn', 'helpers', 'collaborators'];
        foreach ($tables as $table) {
            // preguntamos si existe la tabla
            if (false === $dataBase->tableExists($table)) {
                continue;
            }
            foreach ($dataBase->getColumns($table) as $column) {
                if (in_array($column['name'], $columns)) {
                    $sql = 'ALTER TABLE ' . $table . ' DROP COLUMN ' . $column['name'];
                    $dataBase->exec($sql);
                }
            }
        }
    }
}