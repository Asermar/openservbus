<?php
namespace FacturaScripts\Plugins\OpenServBus\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

class ListTarjeta_type extends ListController {
    
    // Para presentar la pantalla del controlador
    // Estará en el el menú principal bajo \\OpenServBus\Archivos\Empleados
    public function getPageData(): array {
        $pageData = parent::getPageData();
        
        $pageData['menu'] = 'OpenServBus';
        $pageData['submenu'] = 'Tarjetas';
        $pageData['title'] = 'Tarjetas - tipos';
        
        $pageData['icon'] = 'far fa-credit-card';


        return $pageData;
    }
    
    protected function createViews() {
        $this->createViewTarjeta_type();
    }
    
    protected function createViewTarjeta_type($viewName = 'ListTarjeta_type')
    {
        $this->addView($viewName, 'Tarjeta_type');
        
        // Opciones de búsqueda rápida
        $this->addSearchFields($viewName, ['nombre']); // Las búsqueda la hará por el campo nombre
        
        // Tipos de Ordenación
            // Primer parámetro es la pestaña
            // Segundo parámetro es los campos por los que ordena (array)
            // Tercer parámetro es la etiqueta a poner
            // Cuarto parámetro, si se rellena, le está diciendo cual es el order by por defecto, y además las opciones son
               // 1 Orden ascendente
               // 2 Orden descendente
        $this->addOrderBy($viewName, ['nombre'], 'Nombre', 1);
        $this->addOrderBy($viewName, ['fechaalta', 'fechamodificacion'], 'F.Alta+F.MOdif.');
        
        // Filtros
        // Filtro checkBox por campo Activo ... addFilterCheckbox($viewName, $key, $label, $field);
            // $viewName ... nombre del controlador
            // $key ... es el nombre que le ponemos al filtro
            // $label ... la etiqueta a mostrar al usuario
            // $field ... el campo del modelo sobre el que vamos a comprobar
        // $this->addFilterCheckbox($viewName, 'activo', 'Ver sólo los activos', 'activo');
     
        // Filtro de TIPO SELECT para filtrar por registros activos (SI, NO, o TODOS)
        // Sustituimos el filtro activo (checkBox) por el filtro activo (select)
        $activo = [
            ['code' => '1', 'description' => 'Activos = SI'],
            ['code' => '0', 'description' => 'Activos = NO'],
        ];
        $this->addFilterSelect($viewName, 'soloActivos', 'Activos = TODOS', 'activo', $activo);        

        // Filtro periodo de fechas
        // addFilterPeriod($viewName, $key, $label, $field)
            // $key ... es el nombre que le ponemos al filtro
            // $label ... es la etiqueta a mostrar al cliente
            // $field ... es el campo sobre el que filtraremos
        // $this->addFilterPeriod($viewName, 'porFechaAlta', 'Fecha de alta', 'fechaalta');
        
        // Filtro de fecha sin periodo
        // addFilterDatePicker($viewName, $key, $label, $field)

        // Filtro de TIPO SELECT para filtrar por SI ES O NO de pago, O TODOS
        $esDePago = [
            ['code' => '1', 'description' => 'De pago = SI'],
            ['code' => '0', 'description' => 'De pago = NO'],
        ];
        $this->addFilterSelect($viewName, 'esDepago', 'De pago = TODO', 'de_pago', $esDePago);        
        
    }
}
