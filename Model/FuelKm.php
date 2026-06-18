<?php
/**
 * This file is part of OpenServBus plugin for FacturaScripts
 * Copyright (C) 2021-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Copyright (C) 2021-2026 Jerónimo Pedro Sánchez Manzano <socger@gmail.com>
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

namespace FacturaScripts\Plugins\OpenServBus\Model;

use FacturaScripts\Core\Session;
use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;

class FuelKm extends ModelClass
{
    use ModelTrait;
    use OpenServBusModelTrait;

    /** @var bool */
    public $activo;

    /** @var string */
    public $codproveedor;

    /** @var float */
    public $consumo;

    /** @var bool */
    public $deposito_lleno;

    /** @var string */
    public $fecha;

    /** @var string */
    public $fechaalta;

    /** @var string */
    public $fechabaja;

    /** @var string */
    public $fechamodificacion;

    /** @var int */
    public $iddriver;

    /** @var int */
    public $idemployee;

    /** @var int */
    public $idempresa;

    /** @var int */
    public $idfuel_km;

    /** @var int */
    public $idfuel_km_anterior;

    /** @var int */
    public $idfuel_pump;

    /** @var int */
    public $idfuel_type;

    /** @var int */
    public $ididentification_mean;

    /** @var int */
    public $idtarjeta;

    /** @var int */
    public $idvehicle;

    /** @var int */
    public $km;

    /** @var int */
    public $km_recorridos;

    /** @var int */
    public $litros;

    /** @var string */
    public $motivobaja;

    /** @var string */
    public $observaciones;

    /** @var float */
    public $pvp_litro;

    /** @var string */
    public $useralta;

    /** @var string */
    public $userbaja;

    /** @var string */
    public $usermodificacion;

    /** Evita la cascada infinita al recalcular repostajes vecinos. */
    private static $recalculando = false;

    public function clear(): void
    {
        parent::clear();
        $this->activo = true;
        $this->fechaalta = Tools::dateTime();
        $this->useralta = Session::get('user')->nick ?? null;
    }

    public function install(): string
    {
        new Vehicle();
        new Driver();
        new EmployeeOpen();
        new FuelType();
        new FuelPump();
        new Tarjeta();
        return parent::install();
    }

    public static function primaryColumn(): string
    {
        return 'idfuel_km';
    }

    public static function tableName(): string
    {
        return 'fuel_kms';
    }

    public function test(): bool
    {
        if ($this->comprobarSiActivo() === false) {
            return false;
        }

        if ($this->comprobar_Surtidor_Proveedor() === false) {
            return false;
        }

        if ($this->comprobar_Empleado_Conductor() === false) {
            return false;
        }

        if ($this->comprobar_Tarjeta__Identificacion_mean() === false) {
            return false;
        }

        $this->comprobarEmpresa();

        
        $this->observaciones = Tools::noHtml($this->observaciones);
        $this->motivobaja = Tools::noHtml($this->motivobaja);

        // calculamos las estadísticas (km recorridos y consumo) antes de guardar
        $this->calcularEstadisticas();

        return parent::test();
    }

    protected function comprobar_Empleado_Conductor(): bool
    {
        // Exigimos que se introduzca iddriver o idemployee
        if ((empty($this->iddriver)) && (empty($this->idemployee))) {
            Tools::log()->error('confirm-refueling-done-employee-or-driver');
            return false;
        }

        if ((!empty($this->iddriver)) && (!empty($this->idemployee))) {
            Tools::log()->error('refueling-has-employee-or-driver-bat-not-both');
            return false;
        }

        return true;
    }

    protected function comprobar_Surtidor_Proveedor(): bool
    {
        // Exigimos que se introduzca idempresa o idcollaborator
        if ((empty($this->idfuel_pump)) && (empty($this->codproveedor))) {
            Tools::log()->error('confirm-internal-or-external-refueling');
            return false;
        }

        if ((!empty($this->idfuel_pump)) && (!empty($this->codproveedor))) {
            Tools::log()->error('internal-or-external-refueling-bat-not-both');
            return false;
        }

        return true;
    }

    private function comprobar_Tarjeta__Identificacion_mean(): bool
    {
        // La obligatoriedad del medio de pago depende de la configuración de OpenServBus
        if (Tools::settings('openservbus', 'obligar_medio_pago_repostaje')
            && empty($this->idtarjeta) && empty($this->ididentification_mean)) {
            Tools::log()->error('confirm-card-used-this-refueling');
            return false;
        }

        // La tarjeta y el medio de identificación no pueden indicarse ambos a la vez
        if ((!empty($this->idtarjeta)) && (!empty($this->ididentification_mean))) {
            Tools::log()->error('refueling-use-card-or-identification-bat-not-both');
            return false;
        }

        return true;
    }

    protected function comprobarEmpresa(): void
    {
        // Comprobamos la empresa del empleado o del conductor
        if (!empty($this->idemployee)) {
            $sql = ' SELECT employees_open.idempresa '
                . ' , empresas.nombrecorto '
                . ' FROM employees_open '
                . ' LEFT JOIN empresas ON (empresas.idempresa = employees_open.idempresa) '
                . ' WHERE employees_open.idemployee = ' . $this->idemployee;
        } else {
            $sql = ' SELECT employees_open.idempresa '
                . ' , empresas.nombrecorto '
                . ' FROM drivers '
                . ' LEFT JOIN employees_open ON (employees_open.idemployee = drivers.idemployee) '
                . ' LEFT JOIN empresas ON (empresas.idempresa = employees_open.idempresa) '
                . ' WHERE drivers.iddriver = ' . $this->iddriver;
        }

        $registros = static::db()->select($sql);

        foreach ($registros as $fila) {
            $idempresa = $fila['idempresa'];
            $nombreEmpresa = $fila['nombrecorto'];
        }

        if (!empty($this->idempresa)) {
            if (!empty($idempresa)) {
                if ($idempresa <> $this->idempresa) {
                    Tools::log()->info('company-not-equals-company-of-driver', ['%company%' => $nombreEmpresa]);
                }
            }
        }

        // Ahora comprobamos la empresa del vehículo
        if (!empty($this->idvehicle)) {
            $sql = ' SELECT vehicles.idempresa '
                . ' , empresas.nombrecorto '
                . ' FROM vehicles '
                . ' LEFT JOIN empresas ON (empresas.idempresa = vehicles.idempresa) '
                . ' WHERE vehicles.idvehicle = ' . $this->idvehicle;

            $registros = static::db()->select($sql);

            foreach ($registros as $fila) {
                $idempresa = $fila['idempresa'];
                $nombreEmpresa = $fila['nombrecorto'];
            }

            if (!empty($this->idempresa)) {
                if (!empty($idempresa)) {
                    if ($idempresa <> $this->idempresa) {
                        Tools::log()->info('company-not-equals-company-of-vehicle', ['%company%' => $nombreEmpresa]);
                    }
                }
            }
        }
    }

    protected function saveUpdate(array $values = []): bool
    {
        $this->usermodificacion = Session::get('user')->nick ?? null;
        $this->fechamodificacion = Tools::dateTime();
        return parent::saveUpdate();
    }

    protected function onInsert(): void
    {
        parent::onInsert();
        $this->recalcularVecinos();
    }

    protected function onUpdate(): void
    {
        parent::onUpdate();
        $this->recalcularVecinos();
    }

    protected function onDelete(): void
    {
        parent::onDelete();
        $this->recalcularVecinos();
    }

    /**
     * Devuelve el repostaje del mismo vehículo con fecha inmediatamente anterior.
     */
    protected function repostajeAnterior(): ?FuelKm
    {
        if (empty($this->idvehicle) || empty($this->fecha)) {
            return null;
        }

        $where = [
            Where::eq('idvehicle', $this->idvehicle),
            Where::lt('fecha', $this->fecha),
        ];
        if (!empty($this->idfuel_km)) {
            $where[] = Where::notEq('idfuel_km', $this->idfuel_km);
        }

        return static::findWhere($where, ['fecha' => 'DESC', 'idfuel_km' => 'DESC']);
    }

    /**
     * Devuelve el repostaje del mismo vehículo con fecha inmediatamente posterior.
     */
    protected function repostajeSiguiente(): ?FuelKm
    {
        if (empty($this->idvehicle) || empty($this->fecha)) {
            return null;
        }

        $where = [
            Where::eq('idvehicle', $this->idvehicle),
            Where::gt('fecha', $this->fecha),
        ];
        if (!empty($this->idfuel_km)) {
            $where[] = Where::notEq('idfuel_km', $this->idfuel_km);
        }

        return static::findWhere($where, ['fecha' => 'ASC', 'idfuel_km' => 'ASC']);
    }

    /**
     * Fija el enlace al repostaje anterior y calcula km recorridos y consumo (L/100km).
     */
    protected function calcularEstadisticas(): void
    {
        $anterior = $this->repostajeAnterior();
        $this->idfuel_km_anterior = $anterior->idfuel_km ?? null;

        if ($anterior !== null && $this->km !== null && $anterior->km !== null) {
            $recorridos = (int)$this->km - (int)$anterior->km;
            $this->km_recorridos = $recorridos;
            $this->consumo = ($recorridos > 0 && !empty($this->litros))
                ? round((float)$this->litros / $recorridos * 100, 2)
                : null;
            return;
        }

        $this->km_recorridos = null;
        $this->consumo = null;
    }

    /**
     * Tras guardar o eliminar, recalcula los repostajes directamente afectados:
     * el siguiente cronológico y el que tenía a este como anterior.
     */
    protected function recalcularVecinos(): void
    {
        if (self::$recalculando) {
            return;
        }

        self::$recalculando = true;

        $afectados = [];

        $siguiente = $this->repostajeSiguiente();
        if ($siguiente !== null) {
            $afectados[$siguiente->idfuel_km] = $siguiente;
        }

        if (!empty($this->idfuel_km)) {
            $referenciando = static::findWhere([Where::eq('idfuel_km_anterior', $this->idfuel_km)]);
            if ($referenciando !== null) {
                $afectados[$referenciando->idfuel_km] = $referenciando;
            }
        }

        foreach ($afectados as $repostaje) {
            $repostaje->save();
        }

        self::$recalculando = false;
    }

    /**
     * Recalcula la cadena completa de estadísticas de un vehículo escribiendo
     * únicamente las columnas calculadas (sin tocar metadatos ni disparar hooks).
     * Devuelve el número de repostajes actualizados.
     */
    public static function recalcularCadenaVehiculo(int $idvehicle): int
    {
        $repostajes = static::all(
            [Where::eq('idvehicle', $idvehicle)],
            ['fecha' => 'ASC', 'idfuel_km' => 'ASC']
        );

        $actualizados = 0;
        $anterior = null;
        foreach ($repostajes as $repostaje) {
            $idAnterior = $anterior->idfuel_km ?? null;
            $recorridos = null;
            $consumo = null;
            if ($anterior !== null && $repostaje->km !== null && $anterior->km !== null) {
                $recorridos = (int)$repostaje->km - (int)$anterior->km;
                $consumo = ($recorridos > 0 && !empty($repostaje->litros))
                    ? round((float)$repostaje->litros / $recorridos * 100, 2)
                    : null;
            }

            static::table()
                ->whereEq('idfuel_km', $repostaje->idfuel_km)
                ->update([
                    'idfuel_km_anterior' => $idAnterior,
                    'km_recorridos' => $recorridos,
                    'consumo' => $consumo,
                ]);
            $actualizados++;

            $anterior = $repostaje;
        }

        return $actualizados;
    }

    /**
     * Recalcula la cadena de estadísticas de todos los vehículos con repostajes.
     * Devuelve el número total de repostajes actualizados.
     */
    public static function recalcularTodas(): int
    {
        $total = 0;
        $sql = 'SELECT DISTINCT idvehicle FROM ' . static::tableName() . ' WHERE idvehicle IS NOT NULL';
        foreach (static::db()->select($sql) as $fila) {
            $total += static::recalcularCadenaVehiculo((int)$fila['idvehicle']);
        }

        return $total;
    }
}