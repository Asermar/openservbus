<?php

namespace FacturaScripts\Plugins\OSBFuelImport\Model\Join;

use FacturaScripts\Dinamic\Model\Base\JoinModel;
use FacturaScripts\Dinamic\Model\FuelKm as parentModel;

class FuelKm extends JoinModel
{
    public function __construct($data = [])
    {
        parent::__construct($data);
        $this->setMasterModel(new parentModel());
    }

    public function primaryColumnValue()
    {
        return $this->idfuel_km;
    }

    protected function getFields(): array
    {
        return [
            'activo'                  => 'fk.activo',
            'codproveedor'            => 'fk.codproveedor',
            'deposito_lleno'          => 'fk.deposito_lleno',
            'fecha'                   => 'fk.fecha',
            'fechaalta'               => 'fk.fechaalta',
            'fechabaja'               => 'fk.fechabaja',
            'fechamodificacion'       => 'fk.fechamodificacion',
            'fecha_exportacion_cae'   => 'fk.fecha_exportacion_cae',
            'fichero_exportacion_cae' => 'fk.fichero_exportacion_cae',
            'hora'                    => 'fk.hora',
            'iddriver'                => 'fk.iddriver',
            'idempresa'               => 'fk.idempresa',
            'idemployee'              => 'fk.idemployee',
            'idfuel_km'               => 'fk.idfuel_km',
            'idfuel_pump'             => 'fk.idfuel_pump',
            'idfuel_type'             => 'fk.idfuel_type',
            'idtarjeta'               => 'fk.idtarjeta',
            'idvehicle'               => 'fk.idvehicle',
            'km'                      => 'fk.km',
            'litros'                  => 'fk.litros',
            'motivobaja'              => 'fk.motivobaja',
            'observaciones'           => 'fk.observaciones',
            'pvp_litro'               => 'fk.pvp_litro',
            'useralta'                => 'fk.useralta',
            'userbaja'                => 'fk.userbaja',
            'usermodificacion'        => 'fk.usermodificacion',
            'nombre_conductor'        => 'd.nombre_conductor',
            'nombre_vehiculo'         => 'v.nombre_vehiculo',
            'nombre_surtidor'         => 'fp.nombre_surtidor',
        ];
    }

    protected function getSQLFrom(): string
    {
        return 'fuel_kms AS fk'
            . ' LEFT JOIN (SELECT iddriver AS d_id, nombre AS nombre_conductor FROM drivers) AS d ON d.d_id = fk.iddriver'
            . ' LEFT JOIN (SELECT idvehicle AS v_id, nombre AS nombre_vehiculo FROM vehicles) AS v ON v.v_id = fk.idvehicle'
            . ' LEFT JOIN (SELECT idfuel_pump AS fp_id, nombre AS nombre_surtidor FROM fuel_pumps) AS fp ON fp.fp_id = fk.idfuel_pump';
    }

    protected function getTables(): array
    {
        return ['fuel_kms', 'drivers', 'vehicles', 'fuel_pumps'];
    }
}