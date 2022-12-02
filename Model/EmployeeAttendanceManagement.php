<?php

namespace FacturaScripts\Plugins\OpenServBus\Model;

use FacturaScripts\Core\Model\Base;
use FacturaScripts\Core\Session;

class EmployeeAttendanceManagement extends Base\ModelClass
{
    use Base\ModelTrait;
    use OpenServBusModelTrait;

    /** @var bool */
    public $activo;

    /** @var string */
    public $fecha;

    /** @var string */
    public $fechaalta;

    /** @var string */
    public $fechabaja;

    /** @var string */
    public $fechamodificacion;

    /** @var int */
    public $idabsence_reason;

    /** @var int */
    public $idemployee;

    /** @var int */
    public $idemployee_attendance_management;

    /** @var int */
    public $ididentification_mean;

    /** @var string */
    public $motivobaja;

    /** @var string */
    public $observaciones;

    /** @var int */
    public $origen;

    /** @var int */
    public $tipoFichaje;

    /** @var string */
    public $useralta;

    /** @var string */
    public $userbaja;

    /** @var string */
    public $usermodificacion;

    public function clear()
    {
        parent::clear();
        $this->activo = true;
        $this->fecha = date(static::DATETIME_STYLE);
        $this->fechaalta = date(static::DATETIME_STYLE);
        $this->origen = 1;
        $this->useralta = Session::get('user')->nick ?? null;
    }

    public function install(): string
    {
        new Employee();
        return parent::install();
    }

    public static function primaryColumn(): string
    {
        return 'idemployee_attendance_management';
    }

    public static function tableName(): string
    {
        return 'employee_attendance_managements';
    }

    public function test(): bool
    {
        if ($this->comprobarSiActivo() === false) {
            return false;
        }

        $utils = $this->toolBox()->utils();
        $this->observaciones = $utils->noHtml($this->observaciones);
        $this->motivobaja = $utils->noHtml($this->motivobaja);
        return parent::test();
    }

    protected function saveUpdate(array $values = []): bool
    {
        $this->usermodificacion = Session::get('user')->nick ?? null;
        $this->fechamodificacion = date(static::DATETIME_STYLE);
        return parent::saveUpdate($values);
    }
}