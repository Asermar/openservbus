<?php
/**
 * This file is part of OpenServBus plugin for FacturaScripts
 * Copyright (C) 2021-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
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

namespace FacturaScripts\Plugins\OpenServBus\Model\Join;

use FacturaScripts\Core\Template\JoinModel;
use FacturaScripts\Dinamic\Model\FuelKm as parentModel;

/**
 * Modelo Join de repostajes. Sustituye al modelo plano en el listado para habilitar
 * la búsqueda en las tablas relacionadas (conductor, vehículo y surtidor).
 */
class FuelKm extends JoinModel
{
    public function __construct($data = [])
    {
        parent::__construct($data);
        $this->setMasterModel(new parentModel());
    }

    public function id()
    {
        return $this->idfuel_km;
    }

    protected function getFields(): array
    {
        return [
            'activo'                  => 'fk.activo',
            'codproveedor'            => 'fk.codproveedor',
            'consumo'                 => 'fk.consumo',
            'deposito_lleno'          => 'fk.deposito_lleno',
            'fecha'                   => 'fk.fecha',
            'fechaalta'               => 'fk.fechaalta',
            'fechabaja'               => 'fk.fechabaja',
            'fechamodificacion'       => 'fk.fechamodificacion',
            'hora'                    => 'fk.hora',
            'iddriver'                => 'fk.iddriver',
            'idempresa'               => 'fk.idempresa',
            'idemployee'              => 'fk.idemployee',
            'idfuel_km'               => 'fk.idfuel_km',
            'idfuel_km_anterior'      => 'fk.idfuel_km_anterior',
            'idfuel_pump'             => 'fk.idfuel_pump',
            'idfuel_type'             => 'fk.idfuel_type',
            'idtarjeta'               => 'fk.idtarjeta',
            'idvehicle'               => 'fk.idvehicle',
            'km'                      => 'fk.km',
            'km_recorridos'           => 'fk.km_recorridos',
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
