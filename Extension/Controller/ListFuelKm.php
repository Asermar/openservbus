<?php

namespace FacturaScripts\Plugins\OSBFuelImport\Extension\Controller;

use Closure;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * Para modificar el comportamiento o añadir pestañas o secciones a controladores de otros plugins (o del core)
 * podemos crear una extensión de ese controlador.
 *
 * https://facturascripts.com/publicaciones/extensiones-de-modelos
 */
class ListFuelKm
{
    public function createViews(): Closure
    {
        return function() {
            // tu código aquí
            // createViews() se ejecuta una vez realizado el createViews() del controlador.

            // import button
            if ($this->permissions->allowImport) {
                $this->addButton('ListFuelKm', [
                    'action' => 'upload-kms',
                    'icon' => 'fa-solid fa-file-import',
                    'label' => 'import',
                    'type' => 'modal'
                ]);
            }

            // Filtro para ordenar los vehículos por fecha y kilometraje
            $view = $this->listView('ListFuelKm');
            if ($view) {
                // Parámetros: [campos_sql], etiqueta, default (0=no, 1=ASC, 2=DESC)
                $view->addOrderBy(['idvehicle', 'fecha', 'hora', 'km'], 'compuesto', 1);
            }

            // Agregar un filtro para los conductores
            $this->listView('ListFuelKm')
                ->addFilterAutocomplete('ListFuelKm', 'driver', 'iddriver', 'drivers', 'iddriver', 'nombre');

        };
    }

    public function execAfterAction(): Closure
    {
        return function($action) {
            // tu código aquí
            // execAfterAction() se ejecuta tras el execAfterAction() del controlador.
            switch ($action) {
                case 'import-kms':
                    $this->importKmsAction();
                    break;

                case 'upload-kms':
                    $this->uploadKmsAction();
                    break;
            }
        };
    }

    public function execPreviousAction(): Closure
    {
        return function($action) {
            // tu código aquí
            // execPreviousAction() se ejecuta después del execPreviousAction() del controlador.
            // Si devolvemos false detenemos la ejecución del controlador.
        };
    }

    public function loadData(): Closure
    {
        return function($viewName, $view) {
            if ($viewName !== 'ListFuelKm') {
                return;
            }

            // rellenamos el select de plantillas del modal
            $column = $view->columnModalForName('template');
            if ($column && $column->widget->getType() === 'select') {
                // añadimos las opciones automatic y new
                $customValues = [
                    ['value' => 'automatic', 'title' => Tools::lang()->trans('automatic-template')],
                    ['value' => 'new-template', 'title' => Tools::lang()->trans('new-template')]
                ];

                // añadimos las plantillas manuales registradas para este perfil
                $manualTemplates = \FacturaScripts\Plugins\CSVimport\Model\CSVfile::getManualTemplates();
                if (isset($manualTemplates['FuelKm'])) {
                    $customValues[] = ['value' => '', 'title' => '------'];
                    $customValues[] = ['value' => 'FuelKm', 'title' => 'FuelKm'];
                }

                // añadimos la lista de plantillas guardadas para este perfil
                $templates = \FacturaScripts\Plugins\CSVimport\Model\CSVfile::all([\FacturaScripts\Core\Where::column('profile', 'FuelKm')], ['template' => 'ASC']);
                if ($templates) {
                    $customValues[] = ['value' => '', 'title' => '------'];
                    foreach ($templates as $csv) {
                        $customValues[] = ['value' => $csv->id, 'title' => $csv->template];
                    }
                }

                $column->widget->setValuesFromArray($customValues);
            }
        };
    }

    public function selectAction(): Closure
    {
        return function($data, $required) {
            // tu código aquí
            // selectAction() se ejecuta antes de cargar datos en el widget select.
        };
    }

    public function importKmsAction(): Closure
    {
        return function () {
            // obtenemos la ruta completa del archivo
            $fileName = $this->request->get('import-filename');
            $filePath = \FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools::getFilePath($fileName);
            if (empty($filePath)) {
                return true;
            }

            // se ha elegido crea nueva plantilla
            $template = $this->request->get('import-template', \FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools::NEW_TEMPLATE);
            if ($template === \FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools::NEW_TEMPLATE) {
                $newCsvFile = \FacturaScripts\Plugins\CSVimport\Model\CSVfile::newTemplate($fileName, 'FuelKm');
                $newCsvFile->mode = $this->request->get('import-mode');
                if ($newCsvFile->save()) {
                    $this->redirect($newCsvFile->url());
                }
                return true;
            }

            // seleccionamos la plantilla
            $templateModel = null;
            if ($template === \FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools::AUTOMATIC) {
                $templateModel = \FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools::getFileTemplate($filePath, null, 'FuelKm');
            } else {
                $templateModel = \FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools::getFileTemplate($filePath, $template, 'FuelKm');
            }

            if (is_null($templateModel)) {
                Tools::log()->warning('template-not-found');

                // creamos una nueva plantilla
                $newCsvFile = \FacturaScripts\Plugins\CSVimport\Model\CSVfile::newTemplate($fileName, 'FuelKm');
                $newCsvFile->mode = $this->request->get('import-mode');
                if ($newCsvFile->save()) {
                    $this->redirect($newCsvFile->url(), 1);
                }
                return true;
            }

            // procesamos el archivo
            $mode = $this->request->get('import-mode');
            $offset = (int)$this->request->get('import-offset', 0);
            $saveLines = (int)$this->request->get('save-lines', 0);
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
        };
    }
    public function uploadKmsAction(): Closure
    {
        return function () {
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
            } catch (Exception $exc) {
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
        };
    }
}
