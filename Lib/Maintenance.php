<?php
/**
 * Copyright (C) 2026 Oko Digital Experts, S.L.L. (Okodex)
 */

namespace FacturaScripts\Plugins\OpenServBus\Lib;

/**
 * Registro de procesos de mantenimiento de OpenServBus.
 *
 * Cualquier plugin (el propio OpenServBus o los que dependan de él, como
 * BusCanarias) registra sus procesos con addJob() desde su Init. La pestaña
 * "Mantenimiento" de ConfigOpenServBus pinta un botón por cada job registrado;
 * al pulsarlo, el job se encola en la cola de trabajos (WorkQueue) y lo procesa
 * un Worker en segundo plano, sin bloquear la petición.
 *
 * Añadir una nueva opción de mantenimiento solo requiere:
 *   1) Maintenance::addJob([...]) en el Init del plugin
 *   2) un Worker que escuche el evento indicado en 'event'
 *
 * @author Alexis Serafín <alexis@okodex.com>
 */
class Maintenance
{
    /** Catálogo de jobs registrados, indexado por nombre de evento. */
    private static $jobs = [];

    /**
     * Registra un proceso de mantenimiento. Claves del array:
     * - event: evento de la cola de trabajos a encolar (obligatorio)
     * - label: etiqueta del botón (traducible)
     * - icon: icono FontAwesome
     * - color: color Bootstrap (warning, info, danger, success...)
     * - help: texto descriptivo opcional (traducible)
     * - confirm: true para pedir confirmación antes de encolar
     */
    public static function addJob(array $job): void
    {
        if (empty($job['event'])) {
            return;
        }

        self::$jobs[$job['event']] = array_merge([
            'label' => $job['event'],
            'icon' => 'fa-solid fa-play',
            'color' => 'secondary',
            'help' => '',
            'confirm' => true,
        ], $job);
    }

    /** Devuelve la lista de jobs registrados. */
    public static function all(): array
    {
        return array_values(self::$jobs);
    }

    /** Indica si existe un job registrado con ese evento (lista blanca). */
    public static function has(string $event): bool
    {
        return isset(self::$jobs[$event]);
    }
}
