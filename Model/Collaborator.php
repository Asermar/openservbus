<?php

namespace FacturaScripts\Plugins\OpenServBus\Model; 

// use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
// use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Core\Model\Base;

class Collaborator extends Base\ModelClass {
    use Base\ModelTrait;

    public $idcollaborator;
        
    public $user_fecha;
    public $user_nick;
    public $fechaalta;
    public $useralta;
    public $fechamodificacion;
    public $usermodificacion;
    public $activo;
    public $fechabaja;
    public $userbaja;

    public $codproveedor;
    public $observaciones;
    public $nombre;
    
    // función que inicializa algunos valores antes de la vista del controlador
    public function clear() {
        parent::clear();
        
        $this->activo = true; // Por defecto estará activo
    }
    
    // función que devuelve el id principal
    public static function primaryColumn(): string {
        return 'idcollaborator';
    }
    
    // función que devuelve el nombre de la tabla
    public static function tableName(): string {
        return 'collaborators';
    }
    
    protected function comprobarSiActivo()
    {
        if ($this->activo == false) {
            $this->fechabaja = $this->fechamodificacion;
            $this->userbaja = $this->usermodificacion;
        } else { // Por si se vuelve a poner Activo = true
            $this->fechabaja = null;
            $this->userbaja = null;
        }
    }
    
    // Para realizar cambios en los datos antes de guardar por modificación
    protected function saveUpdate(array $values = [])
    {
        // Siendo un alta o una modificación, siempre guardamos los datos de modificación
        $this->usermodificacion = $this->user_nick; 
        $this->fechamodificacion = $this->user_fecha; 
        
        $this->comprobarSiActivo();
        
        return parent::saveUpdate($values);
    }

    // Para realizar cambios en los datos antes de guardar por alta
    protected function saveInsert(array $values = [])
    {
        // Creamos el nuevo id
        if (empty($this->idcollaborator)) {
            $this->idcollaborator = $this->newCode();
        }
        
        // Rellenamos los datos de alta
        $this->useralta = $this->user_nick; 
        $this->fechaalta = $this->user_fecha; 
        
        // Siendo un alta o una modificación, siempre guardamos los datos de modificación
        $this->usermodificacion = $this->user_nick; 
        $this->fechamodificacion = $this->user_fecha; 
        
        $this->comprobarSiActivo();
        
        return parent::saveInsert($values);
    }
    
    public function test()
    {
        $utils = $this->toolBox()->utils();

        $this->codproveedor = $utils->noHtml($this->codproveedor);
        $this->observaciones = $utils->noHtml($this->observaciones);

        // Rellenamos el campo nombre de este modelo pues está ligado con campo nombre de tabla proveedores
        if (empty($this->nombre)) {
            if (!empty($this->codproveedor)) {
                /* Esta podría ser una manera, pero implica hacer un uses al principio contra el modelo proveedor
                 
                $proveedorModel = new Proveedor(); // Tengo que poner en el uses la clase modelo Proveedor
                $where = [new DataBaseWhere('codproveedor', $this->codproveedor)]; // Para entender su funcionamiento visitar ... https://facturascripts.com/publicaciones/databasewhere-478
                $prov_Buscar = $proveedorModel->all($where);

                foreach ($prov_Buscar as $prov) {
                   $this->nombre = $prov->nombre; 
                }
                */

                $sql = ' SELECT PROVEEDORES.NOMBRE AS title '
                     . ' FROM PROVEEDORES '
                     //. ' WHERE (PROVEEDORES.CODPROVEEDOR = ' . $this->codproveedor
                     ;
                $registros = self::$dataBase->select($sql); // Para entender su funcionamiento visitar ... https://facturascripts.com/publicaciones/acceso-a-la-base-de-datos-818

                foreach ($registros as $fila) {
                    $this->nombre = $fila['title'];
                }
            }
        }

        return parent::test();
    }

    
}
