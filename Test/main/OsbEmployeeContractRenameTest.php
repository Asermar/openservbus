<?php
/**
 * This file is part of OpenServBus plugin for FacturaScripts
 * Copyright (C) 2026 Oko Digital Experts, S.L.L. (Okodex)
 * @author Alexis Serafín <alexis@okodex.com>
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

namespace FacturaScripts\Test\Plugins;

use FacturaScripts\Core\Base\ControllerPermissions;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Dinamic\Model\OsbEmployeeContract;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Escenario con SOLO OpenServBus activado (install-plugins.txt = OpenServBus).
 *
 * @description
 * ## Renombrado de EmployeeContract a OsbEmployeeContract
 *
 * El modelo de contratos de OpenServBus se llamaba `EmployeeContract`, igual que el de
 * HumanResources, y en la capa `Dinamic` solo puede quedar uno. Se renombró a
 * `OsbEmployeeContract` (con su controlador y sus dos XMLView). Esta suite fija la parte
 * que se puede comprobar sin HumanResources delante: que TODO lo de OpenServBus apunta a la
 * clase nueva y que no queda ningún cabo suelto colgando del nombre viejo.
 *
 * La convivencia real de los dos plugins se prueba en `Test/with-humanresources/`.
 */
final class OsbEmployeeContractRenameTest extends TestCase
{
    use DefaultSettingsTrait;
    use LogErrorsTrait;

    public static function setUpBeforeClass(): void
    {
        // el constructor de los controladores llama a Empresas::default()
        self::setDefaultSettings();
    }

    /**
     * @description
     * El controlador de edición acompaña al modelo: se llama `EditOsbEmployeeContract`, edita
     * `OsbEmployeeContract` y tiene su XMLView. Que `getModelClassName()` devolviera el nombre
     * viejo sería peor que un error visible: editaría los contratos de HumanResources.
     */
    public function testControladorYVistaAcompananAlModelo(): void
    {
        $className = '\\FacturaScripts\\Dinamic\\Controller\\EditOsbEmployeeContract';
        $this->assertTrue(class_exists($className), 'Falta el controlador EditOsbEmployeeContract en Dinamic');

        $controller = new $className('EditOsbEmployeeContract');
        $this->assertSame(
            'OsbEmployeeContract',
            $controller->getModelClassName(),
            'EditOsbEmployeeContract no edita el modelo renombrado'
        );

        $this->assertFileExists(
            FS_FOLDER . '/Dinamic/XMLView/EditOsbEmployeeContract.xml',
            'Falta la XMLView del controlador renombrado'
        );
    }

    /**
     * @description
     * **Guarda contra un fallo silencioso.** `OsbEmployeeContract::url()` no escribe el destino a
     * mano: compone `ListEmployeeOpen?activetab=List` **más el nombre de la clase**. Renombrar el
     * modelo sin renombrar la pestaña dejaría el enlace apuntando a un `activetab` inexistente —sin
     * error, solo una pestaña que no abre—. Aquí se ata una cosa con la otra: el destino que genera
     * `url()` tiene que ser una vista realmente registrada en `ListEmployeeOpen`.
     */
    public function testElEnlaceDelModeloApuntaAUnaPestanaQueExiste(): void
    {
        $url = (new OsbEmployeeContract())->url('list');
        $this->assertSame('ListEmployeeOpen?activetab=ListOsbEmployeeContract', $url);

        // extraemos el activetab del enlace y comprobamos que esa vista existe de verdad
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
        $activeTab = $query['activetab'] ?? '';

        $controller = $this->createController('ListEmployeeOpen');
        $this->assertArrayHasKey(
            $activeTab,
            $controller->views,
            'url() enlaza a la pestaña "' . $activeTab . '", que ListEmployeeOpen no registra'
        );
    }

    /**
     * @description
     * Las dos vistas que listan contratos dentro de los paneles de empleado (`ListEmployeeOpen` y
     * `EditEmployeeOpen`) montan el modelo renombrado, no el de HumanResources.
     */
    public function testLosPanelesDeEmpleadoMontanElModeloRenombrado(): void
    {
        foreach (['ListEmployeeOpen', 'EditEmployeeOpen'] as $controllerName) {
            $controller = $this->createController($controllerName);

            $this->assertArrayHasKey(
                'ListOsbEmployeeContract',
                $controller->views,
                $controllerName . ' no registra la vista ListOsbEmployeeContract'
            );

            $this->assertInstanceOf(
                OsbEmployeeContract::class,
                $controller->views['ListOsbEmployeeContract']->model,
                $controllerName . '::ListOsbEmployeeContract monta un modelo que no es OsbEmployeeContract'
            );
        }
    }

    /**
     * @description
     * El modelo se publica en `Dinamic` con el nombre nuevo y sigue leyendo la MISMA tabla:
     * el renombrado fue de clase, no de esquema, así que no hubo migración de datos.
     */
    public function testModeloPublicadoConElNombreNuevo(): void
    {
        $this->assertTrue(
            class_exists('\\FacturaScripts\\Dinamic\\Model\\OsbEmployeeContract'),
            'El modelo OsbEmployeeContract no llega a Dinamic'
        );

        $model = new OsbEmployeeContract();
        $this->assertSame('employee_contracts', $model::tableName(), 'La tabla NO debe cambiar con el renombrado');
        $this->assertSame('OsbEmployeeContract', $model->modelClassName());
    }

    /** Precondición de la suite. */
    public function testPluginActivo(): void
    {
        $this->assertTrue(
            Plugins::isEnabled('OpenServBus'),
            'Esta suite (Test/main) debe ejecutarse con OpenServBus activado'
        );
    }

    /**
     * @description
     * OpenServBus ya no ocupa el nombre `EmployeeContract`: ni como clase del plugin ni,
     * por tanto, en `Dinamic`. Si alguien reintrodujera el nombre viejo, la colisión con
     * HumanResources volvería y este test lo detecta antes de llegar al servidor.
     */
    public function testYaNoOcupaElNombreViejo(): void
    {
        $this->assertFalse(
            class_exists('\\FacturaScripts\\Plugins\\OpenServBus\\Model\\EmployeeContract'),
            'OpenServBus vuelve a declarar un modelo EmployeeContract: colisiona con HumanResources'
        );
        $this->assertFalse(
            class_exists('\\FacturaScripts\\Plugins\\OpenServBus\\Controller\\EditEmployeeContract'),
            'OpenServBus vuelve a declarar un controlador EditEmployeeContract'
        );

        // con solo OpenServBus activo, el nombre viejo no debe existir en Dinamic
        $this->assertFalse(
            class_exists('\\FacturaScripts\\Dinamic\\Model\\EmployeeContract'),
            'Alguien sigue publicando EmployeeContract en Dinamic desde OpenServBus'
        );
    }

    private function createController(string $controllerName)
    {
        $className = '\\FacturaScripts\\Dinamic\\Controller\\' . $controllerName;
        $controller = new $className($controllerName);
        $controller->permissions = new ControllerPermissions();

        $method = new ReflectionMethod($controller, 'createViews');
        $method->setAccessible(true);
        $method->invoke($controller);

        return $controller;
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
