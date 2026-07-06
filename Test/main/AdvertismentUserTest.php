<?php
/**
 * This file is part of OpenServBus plugin for FacturaScripts
 * Copyright (C) 2025-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use FacturaScripts\Core\Base\MiniLog;
use FacturaScripts\Core\Model\User;
use FacturaScripts\Plugins\OpenServBus\Model\AdvertismentUser;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;

/**
 * @description
 * ## Avisos a usuarios
 *
 * Valida el modelo `AdvertismentUser` (avisos mostrados a usuarios): al **dar de baja**
 * un aviso (`activo = false`) es obligatorio indicar el **motivo de la baja**.
 */
final class AdvertismentUserTest extends TestCase
{
    use LogErrorsTrait;

    /**
     * @description
     * Al dar de baja un aviso sin motivo, `save()` falla y registra el error
     * `record-is-not-active-specify-reason`. Con motivo, el guardado es válido.
     */
    public function testRequiereMotivoBaja(): void
    {
        $advertismentUser = new AdvertismentUser();
        $advertismentUser->nombre = 'test';
        $this->assertTrue($advertismentUser->save());

        // borramos los mensajes anteriores
        MiniLog::clear();

        // damos de baja
        $advertismentUser->activo = false;

        // comprobamos
        $this->assertFalse($advertismentUser->save());
        $this->assertEquals(
            'record-is-not-active-specify-reason',
            MiniLog::read('', ['critical', 'error', 'warning'])[0]['original']
        );

        // ahora pasamos el motivo de la baja

        // borramos los mensajes anteriores
        MiniLog::clear();

        $advertismentUser->activo = false;
        $advertismentUser->motivobaja = 'test-motivo-baja';

        // comprobamos: guardado válido, sin errores/avisos (ignoramos las trazas SQL
        // del canal database que FS registra en modo debug).
        $this->assertTrue($advertismentUser->save());
        $this->assertEmpty(MiniLog::read('', ['critical', 'error', 'warning']));
    }

    protected function setUp(): void
    {
        // instanciamos al usuario para que se cree la tabla y no de error de foreign key
        new User();
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
