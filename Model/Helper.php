<?php

namespace FacturaScripts\Plugins\OpenServBus\Model; 

//use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Model\Base;

class Helper extends Base\ModelClass {
    use Base\ModelTrait;

    public $idhelper;
        
    public $user_fecha;
    public $user_nick;
    public $fechaalta;
    public $useralta;
    public $fechamodificacion;
    public $usermodificacion;
    public $activo;
    public $fechabaja;
    public $userbaja;
    public $motivobaja;

    public $idemployee;
    public $idcollaborator;
    
    public $observaciones;
    public $nombre;
    
    // función que inicializa algunos valores antes de la vista del controlador
    public function clear() {
        parent::clear();
        $this->activo = true; // Por defecto estará activo
    }
    
    // función que devuelve el id principal
    public static function primaryColumn(): string {
        return 'idhelper';
    }
    
    // función que devuelve el nombre de la tabla
    public static function tableName(): string {
        return 'helpers';
    }
    
    // Para realizar cambios en los datos antes de guardar por modificación
    protected function saveUpdate(array $values = [])
    {
        // Siendo un alta o una modificación, siempre guardamos los datos de modificación
        $this->usermodificacion = $this->user_nick; 
        $this->fechamodificacion = $this->user_fecha; 
        
        if ($this->comprobarSiActivo() == false){
            return false;
        }
        
        return parent::saveUpdate($values);
    }

    // Para realizar cambios en los datos antes de guardar por alta
    protected function saveInsert(array $values = [])
    {
        // Creamos el nuevo id
        if (empty($this->idhelper)) {
            $this->idhelper = $this->newCode();
        }
        
        // Rellenamos los datos de alta
        $this->useralta = $this->user_nick; 
        $this->fechaalta = $this->user_fecha; 
        
        // Siendo un alta o una modificación, siempre guardamos los datos de modificación
        $this->usermodificacion = $this->user_nick; 
        $this->fechamodificacion = $this->user_fecha; 
        
        if ($this->comprobarSiActivo() == false){
            return false;
        }

        return parent::saveInsert($values);
    }
    
    public function test()
    {
        // Exijimos que se introduzca idempresa o idcollaborator
        if ( (empty($this->idemployee)) 
         and (empty($this->idcollaborator))
           ) 
        {
            $this->toolBox()->i18nLog()->error('Debe de confirmar si es un empleado nuestro o de una empresa colaboradora');
            return false;
        }
        
        // No debe de elegir empleado y colaborador a la vez
        if ( (!empty($this->idemployee)) 
         and (!empty($this->idcollaborator))
           ) 
        {
            $this->toolBox()->i18nLog()->error('O es un empleado nuestro o de una empresa colaboradora, pero ambos no');
            return false;
        }
        
        // Para evitar posible inyección de sql
        $utils = $this->toolBox()->utils();
        $this->observaciones = $utils->noHtml($this->observaciones);

        $this->completarCampoNombre();
        
        return parent::test();
    }


    // ** ********************************** ** //
    // ** FUNCIONES CREADAS PARA ESTE MODELO ** //
    // ** ********************************** ** //
    private function comprobarSiActivo()
    {
        $a_devolver = true;
        
        if ($this->activo == false) {
            $this->fechabaja = $this->fechamodificacion;
            $this->userbaja = $this->usermodificacion;
            
            if (empty($this->motivobaja)){
                $a_devolver = false;
                $this->toolBox()->i18nLog()->error('Si el registro no está activo, debe especificar el motivo.');
            }
        } else { // Por si se vuelve a poner Activo = true
            $this->fechabaja = null;
            $this->userbaja = null;
            $this->motivobaja = null;
        }
        return $a_devolver;
    }
    
    private function completarCampoNombre()
    {
        // Rellenamos el campo nombre de este modelo pues está ligado con campo nombre de tabla empleados
        // no hace falta actualizarlo siempre. porque la tabla employees es de este mismo pluggin y desde el test de employee.php actualizo el campo nombre de tabla dirvers
        $sql = '';
        
        if (!empty($this->idemployee)) {
            $sql = ' SELECT employees.nombre AS title '
                 . ' FROM employees '
                 . ' WHERE employees.idemployee = ' . $this->idemployee
                 ;
        }

        if (!empty($this->idcollaborator)) {
            $sql = ' SELECT collaborators.NOMBRE AS title '
                 . ' FROM collaborators '
                 . ' WHERE collaborators.idcollaborator = ' . $this->idcollaborator
                 ;
        }

        if (!$sql == '') {
            $registros = self::$dataBase->select($sql); // Para entender su funcionamiento visitar ... https://facturascripts.com/publicaciones/acceso-a-la-base-de-datos-818

            foreach ($registros as $fila) {
                $this->nombre = $fila['title'];
            }
        } else {
            $this->nombre = null;
        }
        
    }
    
}