<?php

namespace FacturaScripts\Plugins\OpenServBus\Model; 

//use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Model\Base;

class Vehicle_documentation extends Base\ModelClass {
    use Base\ModelTrait;
    
    public $idvehicle_documentation;
        
    public $user_fecha;
    public $user_nick;
    public $fechaalta;
    public $useralta;
    public $fechamodificacion;
    public $usermodificacion;
    public $activo;
    public $fechabaja;
    public $userbaja;

    public $nombre;
    public $idvehicle;
    public $iddocumentation_type;
    public $fecha_caducidad;
    
    public $observaciones;
    
    
    // función que inicializa algunos valores antes de la vista del controlador
    public function clear() {
        parent::clear();
        
        $this->activo = true; // Por defecto estará activo
    }
    
    // función que devuelve el id principal
    public static function primaryColumn(): string {
        return 'idvehicle_documentation';
    }
    
    // función que devuelve el nombre de la tabla
    public static function tableName(): string {
        return 'vehicle_documentations';
    }

    // Para realizar algo antes o después del borrado ... todo depende de que se ponga antes del parent o después
    public function delete()
    {
        $parent_devuelve = parent::delete();
        
        // $this->Actualizar_idempresa_en_employees();
                
        return $parent_devuelve;
        // return parent::delete();
    }

    // Para realizar cambios en los datos antes de guardar por modificación
    protected function saveUpdate(array $values = [])
    {
        // Siendo un alta o una modificación, siempre guardamos los datos de modificación
        $this->usermodificacion = $this->user_nick; 
        $this->fechamodificacion = $this->user_fecha; 
        
        $this->comprobarSiActivo();
        
        $parent_devuelve = parent::saveUpdate($values);
        
        // $this->Actualizar_idempresa_en_employees();
        
        return $parent_devuelve;
    }

    // Para realizar cambios en los datos antes de guardar por alta
    protected function saveInsert(array $values = [])
    {
        // Creamos el nuevo id
        if (empty($this->idvehicle_documentation)) {
            $this->idvehicle_documentation = $this->newCode();
        }
        
        // Rellenamos los datos de alta
        $this->useralta = $this->user_nick; 
        $this->fechaalta = $this->user_fecha; 
        
        // Siendo un alta o una modificación, siempre guardamos los datos de modificación
        $this->usermodificacion = $this->user_nick; 
        $this->fechamodificacion = $this->user_fecha; 
        
        $this->comprobarSiActivo();
        
        $parent_devuelve = parent::saveInsert($values);
        
        // $this->Actualizar_idempresa_en_employees();
        
        return $parent_devuelve;
        //return parent::saveInsert($values);
    }
    
    public function test()
    {
        if (empty($this->fecha_caducidad)) {
            if ($this->ComprobarSiEsObligadaFechaCaducidad() == 1) {
                $this->toolBox()->i18nLog()->error('Para el tipo de documento elegido, necesitamos rellenar la fecha de caducidad');
                return false;
            }
        }
        
        // Código para evitar la inyección de sql
        $utils = $this->toolBox()->utils();
        $this->observaciones = $utils->noHtml($this->observaciones);
        $this->nombre = $utils->noHtml($this->nombre);

        return parent::test();
    }


    // ** ********************************** ** //
    // ** FUNCIONES CREADAS PARA ESTE MODELO ** //
    // ** ********************************** ** //
    private function comprobarSiActivo()
    {
        if ($this->activo == false) {
            $this->fechabaja = $this->fechamodificacion;
            $this->userbaja = $this->usermodificacion;
        } else { // Por si se vuelve a poner Activo = true
            $this->fechabaja = null;
            $this->userbaja = null;
        }
    }
    
    private function ComprobarSiEsObligadaFechaCaducidad()
    {
        $sql = ' SELECT documentation_types.fechacaducidad_obligarla '
             . ' FROM documentation_types '
             . ' WHERE documentation_types.iddocumentation_type = ' . $this->iddocumentation_type . " "
             ;

        $registros = self::$dataBase->select($sql); // Para entender su funcionamiento visitar ... https://facturascripts.com/publicaciones/acceso-a-la-base-de-datos-818

        foreach ($registros as $fila) {
            return $fila['fechacaducidad_obligarla'];
        }
    }
    
}