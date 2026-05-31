<?php

namespace FacturaScripts\Plugins\OSBFuelImport;

use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Plugins\CSVimport\Model\CSVfile;
use FacturaScripts\Plugins\OSBFuelImport\Lib\ManualTemplates\KmsManual;

/**
 * Los plugins pueden contener un archivo Init.php en el que se definen procesos a ejecutar
 * cada vez que carga FacturaScripts o cuando se instala o actualiza el plugin.
 *
 * https://facturascripts.com/publicaciones/el-archivo-init-php-307
 */
class Init extends InitClass
{
    public function init(): void
    {
        // Controlador de los repostajes. Agrega el botón de importación
        $this->loadExtension(new Extension\Controller\ListFuelKm());

        // Plantilla manual para importar repostajes
        CSVfile::addManualTemplate('FuelKm', new KmsManual());

    }

    public function uninstall(): void
    {
        // se ejecuta cada vez que se desinstale el plugin. Primero desinstala y luego ejecuta el uninstall.
    }

    public function update(): void
    {
        // se ejecuta cada vez que se instala o actualiza el plugin
    }
}
