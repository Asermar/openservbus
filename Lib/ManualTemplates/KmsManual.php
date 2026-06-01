<?php

namespace FacturaScripts\Plugins\OSBFuelImport\Lib\ManualTemplates;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Plugins\CSVimport\Contract\ManualTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\ManualTemplates\ManualTemplateClass;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;
use FacturaScripts\Plugins\OpenServBus\Model\FuelKm;
use FacturaScripts\Plugins\OpenServBus\Model\Vehicle;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;

class KmsManual extends ManualTemplateClass implements ManualTemplateInterface
{
    /**
     * Aquí especificaremos todas las columnas disponibles que el usuario podrá seleccionar para vincular en su csv
     */
    public function getDataFields(): array
    {
        return [
            'fuel_kms.fecha' => ['title' => 'fecha'],
            'fuel_kms.hora' => ['title' => 'hora'],
            'fuel_kms.idfuel_pump' => ['title' => 'surtidor'],
            'fuel_kms.codvehicle' => ['title' => 'vehiculo'],
            'fuel_kms.iddriver' => ['title' => 'driver'],
            'fuel_kms.km' => ['title' => 'kilometraje'],
            'fuel_kms.litros' => ['title' => 'litros'],
            'fuel_kms.idempresa' => ['title' => 'empresa'],
            'fuel_kms.idvehicle' => ['title' => 'idvehiculo'],
            'fuel_kms.observaciones' => ['title' => 'observaciones'],
            'fuel_kms.idfuel_type' => ['title' => 'tipo de combustible']

        ];
    }

    /**
     * Aquí podemos indicar si alguna de las columnas anteriores va relacionada con alguna columna del modelo, en este caso el modelo de Clientes. Es necesario por ejemplo para cargar los productos de un provoeedor, previamente necesitamos el código del proveedor y saber en que columna del modelo va ese código, si no, no podremos importar los proveedores.
     */
    public function getFieldsToColumn(): array
    {
        return [];
    }

    /**
     * Sirve para obtener a qué perfil pertenece esta clase de importación, debe ser igual a lo que pongamos en el archivo init
     */
    public static function getProfile(): string
    {
        return 'FuelKm';
    }

    /**
     * Aquí podemos indicar que columnas del modelo son obligatorias, por ejemplo, las columnas "nombre" y "cifnif", sin ella no se puede importar nada. Las columnas son combinadas, osea si por ejemplo hemos peusto dos columnas, las dos tendrán que estar rellenadas.
     */
    public function getRequiredFieldsAnd(): array
    {
        return ['fuel_kms.fecha', 'fuel_kms.hora', 'fuel_kms.idfuel_pump', 'fuel_kms.codvehicle', 'fuel_kms.iddriver', 'fuel_kms.km', 'litros'];
    }

    /**
     * Parecido al anterior, pero usando la clave "OR", quiere decir que es obligatoria rellenar una de las columnas, por ejemplo, rellenar el "cifnif" o "razonsocial".
     */
    public function getRequiredFieldsOr(): array
    {
        return [];
    }

    /**
     * Aquí es donde haremos la comprobación de los datos y guardaremos
     */
    public function importItem(array $item): bool
    {
        $where = [];

        // obtener el idvehicle a partir del codvehicle y comprobar que existe
        if (isset($item['fuel_kms.codvehicle'])) {
            $item['fuel_kms.codvehicle'] = str_pad($item['fuel_kms.codvehicle'], 3, '0', STR_PAD_LEFT);
            $vehicle = new Vehicle();
            if ($vehicle->loadWhere([Where::eq('cod_vehicle', $item['fuel_kms.codvehicle'])])) {
                $item['fuel_kms.idvehicle'] = $vehicle->idvehicle;
            } else {
                return false;
            }
        }

        if (isset($item['fuel_kms.fecha']) && !empty($item['fuel_kms.fecha'])
            && isset($item['fuel_kms.hora']) && !empty($item['fuel_kms.hora'])
            && isset($item['fuel_kms.idvehicle']) && !empty($item['fuel_kms.idvehicle'])) {
            $where = [
                Where::eq('fecha', $item['fuel_kms.fecha']),
                Where::eq('hora', $item['fuel_kms.hora']),
                Where::eq('idvehicle', $item['fuel_kms.idvehicle'])];
        }

        $refueling = new FuelKm();
        if (!empty($where)) {
            if (($refueling->loadWhere($where) && $this->model->mode === CsvFileTools::INSERT_MODE )||
                (false === $refueling->loadWhere($where) && $this->model->mode === CsvFileTools::UPDATE_MODE)) {
                return false;
            }
        } elseif ($this->model->mode === CsvFileTools::UPDATE_MODE) {
            return false;
        }

        // Generar un texto para el campo observaciones
        $item['observaciones'] = date('Y-m-d H:i:s') . 'Importación desde archivo' ;

        // si idfuel_type esta vacio, establecerlo a 1 (gasoil)
        if (empty($item['fuel_kms.idfuel_type'])) {
            $item['fuel_kms.idfuel_type'] = 1;
        }

        if (false === $this->setModelValues($refueling, $item, 'fuel_kms.')) {
            return false;
        }

        $saved = $refueling->save();
        if (false === $saved) {
            // Facturascripts agrega un error para los problemas que encuentra. Este lo catalogo como información porque suministra el contexto
            Tools::log('ImportacionRepostajes')->info('No se pudo guardar el repostaje ' . $this->model->path, $item);
        }
        return $saved;
    }
}