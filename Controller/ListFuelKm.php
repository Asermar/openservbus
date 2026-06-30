<?php
/**
 * This file is part of OpenServBus plugin for FacturaScripts
 * Copyright (C) 2021-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Copyright (C) 2021-2026 Jerónimo Pedro Sánchez Manzano <socger@gmail.com>
 * Copyright (C) 2026 Alexis Serafín (Asermar) <alexis@okodex.com>
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

namespace FacturaScripts\Plugins\OpenServBus\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Join\FuelKm as FuelKmJoin;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;
use FacturaScripts\Plugins\CSVimport\Model\CSVfile;

class ListFuelKm extends ListController
{
    /** Perfil de importación CSV de los repostajes. */
    const IMPORT_PROFILE = 'FuelKm';

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'OpenServBus';
        $pageData['title'] = 'refueling-kms';
        $pageData['icon'] = 'fa-solid fa-gas-pump';
        return $pageData;
    }

    protected function createViewFuel_pump($viewName = 'ListFuelPump'): void
    {
        $this->addView($viewName, 'FuelPump', 'internal-spout', 'fa-solid fa-thumbtack');
        $this->addSearchFields($viewName, ['nombre']);
        $this->addOrderBy($viewName, ['nombre'], 'name', 1);
        $this->addOrderBy($viewName, ['fechaalta', 'fechamodificacion'], 'fhigh-fmodiff');

        $activo = [
            ['code' => '1', 'description' => 'active-yes'],
            ['code' => '0', 'description' => 'active-no'],
        ];
        $this->addFilterSelect($viewName, 'soloActivos', 'active-all', 'activo', $activo);
    }

    protected function createViewFuelKm($viewName = 'ListFuelKm'): void
    {
        $this->addView($viewName, 'FuelKm', 'refueling-kms', 'fa-solid fa-gas-pump');
        $this->addOrderBy($viewName, ['fecha'], 'Fecha', 1);
        $this->addOrderBy($viewName, ['fechaalta', 'fechamodificacion'], 'fhigh-fmodiff');

        // Filtros
        $activo = [
            ['code' => '1', 'description' => 'active-yes'],
            ['code' => '0', 'description' => 'active-no'],
        ];
        $this->addFilterSelect($viewName, 'soloActivos', 'active-all', 'activo', $activo);

        $this->addFilterAutocomplete($viewName, 'xIdEmpresa', 'company', 'idempresa', 'empresas', 'idempresa', 'nombre');
        $this->addFilterAutocomplete($viewName, 'xIdVehicle', 'vehicle', 'idvehicle', 'vehicles', 'idvehicle', 'nombre');
        $this->addFilterAutocomplete($viewName, 'xIdFuel_Type', 'fuel', 'idfuel_type', 'fuel_types', 'idfuel_type', 'nombre');
        $this->addFilterAutocomplete($viewName, 'xIdFuel_Pumps', 'internal-fuel-dispenser', 'idfuel_pump', 'fuel_pumps', 'idfuel_pump', 'nombre');
        $this->addFilterAutocomplete($viewName, 'xIdEmployee', 'employee', 'idemployee', 'employees_open', 'idemployee', 'nombre');
        $this->addFilterAutocomplete($viewName, 'xCodProveedor', 'supplier', 'codproveedor', 'proveedores', 'codproveedor', 'nombre');
        $this->addFilterAutocomplete($viewName, 'xIdTarjeta', 'card', 'idtarjeta', 'tarjetas', 'idtarjeta', 'nombre');
        $this->addFilterPeriod($viewName, 'porFecha', 'refueling-date', 'fecha');

        $esDepositoLleno = [
            ['code' => '1', 'description' => 'full-tank-yes'],
            ['code' => '0', 'description' => 'full-tank-no'],
        ];
        $this->addFilterSelect($viewName, 'esDepositoLleno', 'full-tank-all', 'deposito_lleno', $esDepositoLleno);

        // filtro para mostrar solo repostajes con km recorridos negativos
        $this->addFilterCheckbox($viewName, 'kmsRecorridosNegativos', 'km-traveled-negative', 'km_recorridos', '<', 0);

        // el recálculo de estadísticas de todos los repostajes se ha trasladado a la
        // pestaña "Mantenimiento" de ConfigOpenServBus, donde se ejecuta en segundo
        // plano mediante la cola de trabajos.
    }

    /**
     * Añade al listado de repostajes la búsqueda en tablas relacionadas y el botón
     * de importación desde CSV. Solo se activa si el plugin CSVimport está presente.
     */
    protected function createViewImportKms($viewName = 'ListFuelKm'): void
    {
        if (false === $this->csvImportAvailable() || false === isset($this->views[$viewName])) {
            return;
        }

        // sustituimos el modelo de la vista por el Join para habilitar la búsqueda
        // en las tablas relacionadas (conductor, vehículo y surtidor)
        $this->views[$viewName]->model = new FuelKmJoin();

        $surtidores = $this->codeModel->all('fuel_pumps', 'idfuel_pump', 'nombre');
        $this->listView($viewName)
            ->addSearchFields(['d.nombre_conductor', 'v.nombre_vehiculo', 'fp.nombre_surtidor', 'fk.km', 'fk.litros'])
            ->addFilterSelect('xIdFuel_Pumps', 'internal-fuel-dispenser', 'idfuel_pump', $surtidores)
            ->addOrderBy(['idvehicle', 'fecha', 'hora', 'km'], 'compuesto', 1);

        // filtro por conductor
        $this->addFilterAutocomplete($viewName, 'xIdDriver', 'driver', 'iddriver', 'drivers', 'iddriver', 'nombre');

        // botón de importación
        if ($this->permissions->allowImport) {
            $this->tab($viewName)->addButton([
                'action' => 'upload-kms',
                'icon' => 'fa-solid fa-file-import',
                'label' => 'import',
                'type' => 'modal'
            ]);
        }
    }

    protected function createViews(): void
    {
        $this->createViewFuelKm();
        $this->createViewFuel_pump();

        // funcionalidad de importación de repostajes (requiere el plugin CSVimport)
        $this->createViewImportKms();
    }

    /**
     * Indica si el plugin CSVimport está disponible. La importación de repostajes
     * depende de él (campo "compatible" en facturascripts.ini); si no está instalado
     * la funcionalidad simplemente no se activa.
     */
    protected function csvImportAvailable(): bool
    {
        return class_exists('\FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools');
    }

    protected function execAfterAction($action)
    {
        if ($this->csvImportAvailable()) {
            switch ($action) {
                case 'import-kms':
                    $this->importKmsAction();
                    break;

                case 'upload-kms':
                    $this->uploadKmsAction();
                    break;
            }
        }

        return parent::execAfterAction($action);
    }

    /** Procesa el archivo subido e importa los repostajes por lotes. */
    protected function importKmsAction(): bool
    {
        // obtenemos la ruta completa del archivo
        $fileName = $this->request->queryOrInput('import-filename');
        $filePath = CsvFileTools::getFilePath($fileName);
        if (empty($filePath)) {
            return true;
        }

        // se ha elegido crear nueva plantilla
        $template = $this->request->queryOrInput('import-template', CsvFileTools::NEW_TEMPLATE);
        if ($template === CsvFileTools::NEW_TEMPLATE) {
            $newCsvFile = CSVfile::newTemplate($fileName, self::IMPORT_PROFILE);
            $newCsvFile->mode = $this->request->queryOrInput('import-mode');
            if ($newCsvFile->save()) {
                $this->redirect($newCsvFile->url());
            }
            return true;
        }

        // seleccionamos la plantilla
        if ($template === CsvFileTools::AUTOMATIC) {
            $templateModel = CsvFileTools::getFileTemplate($filePath, null, self::IMPORT_PROFILE);
        } else {
            $templateModel = CsvFileTools::getFileTemplate($filePath, $template, self::IMPORT_PROFILE);
        }

        if (is_null($templateModel)) {
            Tools::log()->warning('template-not-found');

            // creamos una nueva plantilla
            $newCsvFile = CSVfile::newTemplate($fileName, self::IMPORT_PROFILE);
            $newCsvFile->mode = $this->request->queryOrInput('import-mode');
            if ($newCsvFile->save()) {
                $this->redirect($newCsvFile->url(), 1);
            }
            return true;
        }

        // procesamos el archivo
        $mode = $this->request->queryOrInput('import-mode');
        $offset = (int)$this->request->queryOrInput('import-offset', 0);
        $saveLines = (int)$this->request->queryOrInput('save-lines', 0);
        $result = $templateModel->getProfile($offset, $saveLines, $filePath, $mode)->import();
        if ($result['offset'] > 0 && $result['offset'] < $result['total']) {
            Tools::log()->notice(
                'items-save-correctly-to-total',
                ['%lines%' => $result['offset'], '%total%' => $result['total'], '%save%' => $result['save']]
            );
            Tools::log()->notice('importing');
            $this->redirect($this->url() . '?action=import-kms&import-filename=' . urlencode($fileName)
                . '&import-offset=' . $result['offset'] . '&save-lines=' . $result['save']
                . '&import-template=' . $templateModel->id . '&import-mode=' . $mode, 1);
            return true;
        }

        unlink($filePath);
        Tools::log()->notice('items-added-correctly', ['%num%' => $result['save']]);
        return true;
    }

    protected function loadData($viewName, $view)
    {
        parent::loadData($viewName, $view);

        if ($viewName === 'ListFuelKm' && $this->csvImportAvailable()) {
            $this->loadImportTemplates($view);
        }
    }

    /** Rellena el desplegable de plantillas del modal de importación. */
    protected function loadImportTemplates($view): void
    {
        $column = $view->columnModalForName('template');
        if (empty($column) || $column->widget->getType() !== 'select') {
            return;
        }

        // opciones automatic y new
        $customValues = [
            ['value' => 'automatic', 'title' => Tools::lang()->trans('automatic-template')],
            ['value' => 'new-template', 'title' => Tools::lang()->trans('new-template')]
        ];

        // plantillas manuales registradas para este perfil
        $manualTemplates = CSVfile::getManualTemplates();
        if (isset($manualTemplates[self::IMPORT_PROFILE])) {
            $customValues[] = ['value' => '', 'title' => '------'];
            $customValues[] = ['value' => self::IMPORT_PROFILE, 'title' => self::IMPORT_PROFILE];
        }

        // plantillas guardadas para este perfil
        $templates = CSVfile::all([Where::column('profile', self::IMPORT_PROFILE)], ['template' => 'ASC']);
        if ($templates) {
            $customValues[] = ['value' => '', 'title' => '------'];
            foreach ($templates as $csv) {
                $customValues[] = ['value' => $csv->id, 'title' => $csv->template];
            }
        }

        $column->widget->setValuesFromArray($customValues);
    }

    /** Recibe el archivo del modal, lo valida y lo convierte a CSV. */
    protected function uploadKmsAction(): bool
    {
        // comprobamos los permisos de importación
        if (false === $this->permissions->allowImport) {
            Tools::log()->warning('no-import-permission');
            return true;
        }

        // comprobamos el token
        if (false === $this->validateFormToken()) {
            return true;
        }

        // comprobamos el tamaño y tipo del archivo
        $uploadFile = $this->request->files->get('kmsfile');
        if (CsvFileTools::isBigFile($uploadFile) || false === CsvFileTools::isValidFile($uploadFile->getRealPath())) {
            return true;
        }

        try {
            // movemos el archivo
            $path = CsvFileTools::saveUploadFile($uploadFile);

            // convertimos el archivo a CSV, si es necesario
            $filePath = CsvFileTools::convertFileToCsv($path);
        } catch (\Exception $exc) {
            Tools::log()->warning('upload-file-error');
            Tools::log()->warning($uploadFile->getClientOriginalName());
            Tools::log()->warning($exc->getMessage());
            return true;
        }

        // recargamos la página para llamar a la acción de importación
        $fileName = basename($filePath);
        $template = $this->request->request->get('template', CsvFileTools::AUTOMATIC);
        $mode = $this->request->request->get('mode', CsvFileTools::AUTOMATIC);
        $this->redirect($this->url() . '?action=import-kms&import-filename=' . urlencode($fileName)
            . '&import-template=' . $template . '&import-mode=' . $mode);
        return true;
    }
}