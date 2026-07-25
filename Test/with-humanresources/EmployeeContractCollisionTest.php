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
use FacturaScripts\Core\Base\MiniLog;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Where;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Escenario CON HumanResources activado (install-plugins.txt = HumanResources,OpenServBus).
 *
 * El orden importa: FacturaScripts asigna a cada plugin `order = maxOrder() + 1` al activarlo,
 * y en `Dinamic` gana el último. Activando OpenServBus en segundo lugar reproducimos la
 * precedencia real de producción, que es la condición bajo la que apareció el fallo.
 *
 * @description
 * ## Convivencia con HumanResources — contratos de empleado
 *
 * Los dos plugins declaraban `EmployeeContract` (modelo, controlador `EditEmployeeContract` y su
 * XMLView). Ganaba OpenServBus por cargarse después, así que el panel EditEmployee de
 * HumanResources montaba su vista de contratos sobre la tabla equivocada y reventaba con
 * *"Unknown column 'startdate' in 'ORDER BY'"*: ordena por `startdate`, que existe en
 * `rrhh_employeescontracts` pero no en `employee_contracts` (que usa `fecha_inicio`/`fecha_fin`).
 * De paso, la página de contratos de HumanResources era inalcanzable.
 *
 * Esta suite comprueba que, con los dos plugins activos a la vez, cada uno se queda con lo suyo.
 */
final class EmployeeContractCollisionTest extends TestCase
{
    use DefaultSettingsTrait;
    use LogErrorsTrait;

    public static function setUpBeforeClass(): void
    {
        self::setDefaultSettings();
    }

    /** Precondición de la suite: sin los dos plugins activos no se prueba nada. */
    public function testAmbosPluginsActivosYEnElOrdenDeProduccion(): void
    {
        $this->assertTrue(
            Plugins::isEnabled('OpenServBus'),
            'Esta suite (Test/with-humanresources) debe ejecutarse con OpenServBus activado'
        );
        $this->assertTrue(
            Plugins::isEnabled('HumanResources'),
            'Esta suite (Test/with-humanresources) debe ejecutarse con HumanResources activado'
        );

        // Plugins::enabled() devuelve los nombres ordenados por `order`, y en Dinamic gana el
        // último. Sin esta comprobación la suite puede pasar en vacío: si OpenServBus cargase
        // primero, reintroducir la colisión no la haría fallar, porque el nombre repetido se lo
        // quedaría HumanResources. `order` se asigna al activar (maxOrder() + 1) y persiste, así
        // que basta con que otra suite haya dejado OpenServBus activo antes para invertirlo.
        $enabled = Plugins::enabled();
        $this->assertGreaterThan(
            array_search('HumanResources', $enabled, true),
            array_search('OpenServBus', $enabled, true),
            'OpenServBus debe cargarse DESPUÉS de HumanResources para reproducir producción.'
            . ' Sincroniza a lista vacía antes de lanzar esta suite (así se reasignan los order).'
            . ' Orden actual: ' . implode(',', $enabled)
        );
    }

    /**
     * @description
     * Mismo reparto en la capa de controladores y XMLView: `EditEmployeeContract` vuelve a ser
     * el de HumanResources (antes lo tapaba OpenServBus y su página no se podía abrir) y
     * `EditOsbEmployeeContract` es el nuestro.
     */
    public function testCadaPluginConservaSuControladorYSuVista(): void
    {
        $rrhh = '\\FacturaScripts\\Dinamic\\Controller\\EditEmployeeContract';
        $osb = '\\FacturaScripts\\Dinamic\\Controller\\EditOsbEmployeeContract';

        $this->assertTrue(class_exists($rrhh), 'HumanResources ya no publica EditEmployeeContract');
        $this->assertTrue(class_exists($osb), 'OpenServBus ya no publica EditOsbEmployeeContract');

        $this->assertSame(
            'FacturaScripts\\Plugins\\HumanResources\\Controller\\EditEmployeeContract',
            get_parent_class($rrhh),
            'OpenServBus vuelve a tapar el controlador de contratos de HumanResources'
        );
        $this->assertSame(
            'FacturaScripts\\Plugins\\OpenServBus\\Controller\\EditOsbEmployeeContract',
            get_parent_class($osb)
        );

        // cada controlador edita SU modelo
        $this->assertSame('EmployeeContract', (new $rrhh('EditEmployeeContract'))->getModelClassName());
        $this->assertSame('OsbEmployeeContract', (new $osb('EditOsbEmployeeContract'))->getModelClassName());

        // Y la XMLView desplegada es la del plugin dueño de cada página. No se comparan bytes
        // con el original: PluginsDeploy::linkXMLFile no copia el fichero, lo reserializa con
        // SimpleXML (para fusionar las extensiones), así que el resultado nunca es idéntico.
        // Se comparan los campos, que además son los del fallo: startdate en HumanResources y
        // fecha_inicio en OpenServBus.
        $this->assertSame(
            ['enddate', 'startdate'],
            $this->fieldsOfView('EditEmployeeContract', ['startdate', 'enddate', 'fecha_inicio', 'fecha_fin']),
            'La XMLView EditEmployeeContract desplegada no es la de HumanResources'
        );
        $this->assertSame(
            ['fecha_fin', 'fecha_inicio'],
            $this->fieldsOfView('EditOsbEmployeeContract', ['startdate', 'enddate', 'fecha_inicio', 'fecha_fin']),
            'La XMLView EditOsbEmployeeContract desplegada no es la de OpenServBus'
        );
    }

    /**
     * @description
     * Con los dos plugins activos hay DOS modelos, cada uno sobre su tabla: `EmployeeContract`
     * es el de HumanResources (`rrhh_employeescontracts`) y `OsbEmployeeContract` el de
     * OpenServBus (`employee_contracts`). Antes solo sobrevivía uno de los dos nombres.
     */
    public function testCadaPluginConservaSuModelo(): void
    {
        $rrhh = '\\FacturaScripts\\Dinamic\\Model\\EmployeeContract';
        $osb = '\\FacturaScripts\\Dinamic\\Model\\OsbEmployeeContract';

        $this->assertTrue(class_exists($rrhh), 'HumanResources ya no publica su modelo EmployeeContract');
        $this->assertTrue(class_exists($osb), 'OpenServBus ya no publica su modelo OsbEmployeeContract');

        $this->assertSame('rrhh_employeescontracts', $rrhh::tableName());
        $this->assertSame('employee_contracts', $osb::tableName());

        // el fallo original consistía justamente en que estos dos nombres daban la MISMA tabla
        $this->assertNotSame($rrhh::tableName(), $osb::tableName());

        // y cada clase Dinamic hereda del plugin que le corresponde
        $this->assertSame(
            'FacturaScripts\\Plugins\\HumanResources\\Model\\EmployeeContract',
            get_parent_class($rrhh)
        );
        $this->assertSame(
            'FacturaScripts\\Plugins\\OpenServBus\\Model\\OsbEmployeeContract',
            get_parent_class($osb)
        );
    }

    /**
     * @description
     * **La regresión concreta.** Reproduce lo que hacía el panel que reventaba: EditEmployee de
     * HumanResources carga su vista de contratos filtrando por `idemployee` y ordenando por
     * `startdate` (lo fija `EmployeeControllerTrait::orderByForView`). Con la colisión, esa consulta
     * salía contra `employee_contracts` y MySQL respondía *Unknown column 'startdate' in 'ORDER BY'*.
     *
     * FacturaScripts atrapa los errores de SQL y los registra en vez de lanzarlos, así que la
     * comprobación es que el MiniLog quede limpio: si vuelve la colisión, aquí aparece el error.
     */
    public function testCargarLosContratosDeUnEmpleadoNoDaErrorSql(): void
    {
        $controller = $this->createController('EditEmployee');

        $this->assertArrayHasKey(
            'EditEmployeeContract',
            $controller->views,
            'EditEmployee ya no registra la vista de contratos: el test dejaría de vigilar la regresión'
        );

        $view = $controller->views['EditEmployeeContract'];

        // la vista debe montar el modelo de HumanResources, no el nuestro
        $this->assertSame(
            'FacturaScripts\\Plugins\\HumanResources\\Model\\EmployeeContract',
            get_parent_class(get_class($view->model)),
            'La vista de contratos de EditEmployee monta el modelo de OpenServBus'
        );

        MiniLog::clear();

        // misma llamada que EditEmployee::loadData para esta vista
        $view->loadData(false, [Where::eq('idemployee', 1)], ['startdate' => 'DESC']);

        $errores = MiniLog::read('', ['critical', 'error']);
        $this->assertEmpty(
            $errores,
            'Cargar los contratos del empleado da error: ' . ($errores[0]['message'] ?? '')
        );
    }

    /**
     * @description
     * El reverso: el listado de contratos de OpenServBus sigue ordenando por SUS columnas
     * (`fecha_inicio`/`fecha_fin`) contra SU tabla, también con HumanResources delante.
     */
    public function testElListadoDeContratosDeOpenServBusSigueFuncionando(): void
    {
        $controller = $this->createController('ListEmployeeOpen');

        $this->assertArrayHasKey('ListOsbEmployeeContract', $controller->views);

        MiniLog::clear();

        $controller->views['ListOsbEmployeeContract']
            ->loadData(false, [], ['fecha_inicio' => 'DESC']);

        $errores = MiniLog::read('', ['critical', 'error']);
        $this->assertEmpty(
            $errores,
            'El listado de contratos de OpenServBus da error: ' . ($errores[0]['message'] ?? '')
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

    /**
     * Devuelve, de los campos de $lookFor, cuáles usa la XMLView desplegada (ordenados, para
     * poder compararlos sin depender del orden en que estén en el XML).
     */
    private function fieldsOfView(string $viewName, array $lookFor): array
    {
        $path = FS_FOLDER . '/Dinamic/XMLView/' . $viewName . '.xml';
        $this->assertFileExists($path, 'No se ha desplegado la XMLView ' . $viewName);

        $xml = simplexml_load_file($path);
        $this->assertNotFalse($xml, 'XMLView ilegible: ' . $path);

        $found = [];
        foreach ($xml->xpath('//*[@fieldname]') ?: [] as $node) {
            $fieldName = (string)$node['fieldname'];
            if (in_array($fieldName, $lookFor, true)) {
                $found[$fieldName] = $fieldName;
            }
        }

        sort($found);

        return $found;
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
