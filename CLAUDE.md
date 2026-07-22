# OpenServBus — guía para Claude Code

Plugin **base** de **FacturaScripts** para el sector del **transporte de viajeros**: gestiona
servicios **discrecionales**, **regulares** y **regulares especiales**, más su flota (vehículos,
conductores, repostajes) y el RRHH asociado. `version 4.02`, `min_version 2026`, `min_php 8`.
`compatible = CSVimport, BuscadorAcumulado` (integraciones opcionales; no son `require`). **No tiene
`require`**: es el plugin del que dependen o al que extienden **BusCanarias** (tour-operadores),
**OSBCae**, **BusImportacion** y los **AppConnect\***. Licencia **LGPL v3** (open source; procede de
Carlos García / socger, FacturaScripts). Cabeceras nuevas → co-copyright Okodex/Alexis.

> Para desarrollar aquí, apoyarse en los agentes/skills **`fs-dev:*`** (docs-expert para el patrón
> del framework, backend-developer para modelos/workers, etc.) y que revisen la implementación.

## Modelo mental

- Modelos propios en `namespace FacturaScripts\Plugins\OpenServBus\Model`, todos `ModelClass` +
  `ModelTrait` + **`OpenServBusModelTrait`**. En runtime se usan vía `Dinamic\Model\*` (rebuild
  regenera `Dinamic`; tras tocar `Init.php`/workers/modelos hay que **rebuild/deploy** o no se
  registran).
- **Patrón de auditoría/borrado lógico común** a casi todos los modelos: columnas `activo`,
  `fechaalta`/`useralta`, `fechamodificacion`/`usermodificacion`, `fechabaja`/`userbaja`/`motivobaja`.
  `OpenServBusModelTrait::comprobarSiActivo()` (llamado en `test()`) implementa el **soft-delete**: si
  `activo` sigue, limpia los campos de baja; si se desactiva, exige `motivobaja` (error si falta) y
  sella fecha/usuario de baja. `saveUpdate()` de cada modelo fija `usermodificacion`/`fechamodificacion`.
- **Mucho SQL crudo** concatenado (`static::db()->select()/exec()`) para joins y actualizaciones en
  cascada. El texto se sanea con `Tools::noHtml()` (métodos `evitarInyeccionSQL()`), pero los **ids
  numéricos se concatenan directos**: son propios/internos, no entrada libre — mantener esa premisa al
  tocar estas queries.

## Dominios (Model/)

- **Servicios discrecionales** → `Service` (`services`). Servicio puntual con fechas/horas desde-hasta,
  cliente, empresa, vehículo/tipo, hasta 3 conductores (`iddriver_1..3` + alojamiento/observaciones),
  monitor (`idhelper`), hoja de ruta (origen/destino/expediciones/contratante/CIF), importes nacional +
  extranjero con sus impuestos, y `aceptado` (requisito para poder montarlo).
- **Servicios regulares** → `ServiceRegular` (`service_regulars`) + satélites:
  `ServiceRegularPeriod` (periodos con fecha/hora de vigencia; el servicio hereda el **último** periodo
  vía `completarDatosUltimoPeriodo()`), `ServiceRegularItinerary` (paradas ordenadas, FK a `Stop`),
  `ServiceRegularValuation` (valoraciones/importes por concepto). Marca de días de la semana
  (`lunes..domingo`), `cod_servicio` (alfanumérico, autogenerado con `newCode()` si falta),
  `facturar_SN`/`facturar_agrupando`, y `combinadoSN` (derivado).
- **Combinaciones de regulares** → `ServiceRegularCombination` (`service_regular_combinations`) y su
  N:M `ServiceRegularCombinationServ` (`service_regular_combination_servs`, enlaza combinación↔servicio
  regular). Validan **coherencia de días de la semana** entre combinación y servicios; el enlace
  autocompleta conductor/vehículo (de la combinación, si no del servicio) y actualiza `combinadoSN` del
  servicio regular en cada `save()`/`delete()`.
- **Montaje** → `ServiceAssembly` (`service_assemblies`). **Snapshot** de un `Service` **o** un
  `ServiceRegular` (XOR: uno de `idservice`/`idservice_regular`) asignado a un vehículo/conductor
  concretos. `completarServicio()` copia los campos del servicio origen: en discrecionales **sobrescribe**
  todo; en regulares **respeta** lo editado a mano. Es la tabla que `Service::actualizarServicioEnMontaje()`
  refresca por UPDATE masivo tras editar el servicio.
- **Valoraciones** → `ServiceValuation` (`service_valuations`) + `ServiceValuationType` (catálogo).
  Cada valoración suma al `importe`/`importe_enextranjero` del `Service` padre (`actualizar_Importes()`
  en insert/update/delete recalcula la suma y vuelve a llamar a `rellenarTotal()`); `orden` en pasos de 5.
- **Itinerarios y paradas** → `ServiceItinerary`, `ServiceRegularItinerary`, `Stop` (`stops`).
- **Vehículos** → `Vehicle` (`vehicles`; propia XOR colaborador: `idempresa`/`idcollaborator`),
  `VehicleType`, `VehicleDocumentation`, `VehicleEquipament` + `VehicleEquipamentType`, `Garage`.
- **Conductores y RRHH** → `Driver` (`drivers`; apunta a un `EmployeeOpen` **o** un `Collaborator`; su
  `nombre` se deriva de uno u otro y sincroniza `driver_yn` en el empleado al guardar/borrar),
  `EmployeeOpen` (`employees_open`, ficha de personal), `Helper` (monitores/auxiliares),
  `Collaborator` (colaboradores externos, ligados a proveedor), `EmployeeContract` +
  `EmployeeContractType`, `EmployeeDocumentation` + `DocumentationType`, `AbsenceReason`,
  `EmployeeAttendanceManagement` + `EmployeeAttendanceManagementYn`, `IdentificationMean`,
  `TourDepartment` (tabla `departments`).
- **Combustible / repostajes** → `FuelKm` (`fuel_kms`), `FuelPump` (surtidores), `FuelType`, más medios
  de pago `Tarjeta` + `TarjetaType` e `IdentificationMean`. Ver lógica destacable abajo.
- **Avisos** → `AdvertismentUser` (`advertisment_users`): avisos por usuario/rol, inyectados en las
  fichas de User y Role del core (ver Extensiones).
- **`Model/Join/FuelKm`** (`JoinModel`, solo lectura): sustituye al modelo plano en `ListFuelKm` para
  permitir **buscar por conductor/vehículo/surtidor** (join a `drivers`/`vehicles`/`fuel_pumps`). Requiere
  `FacturaScripts\Core\Template\JoinModel` (core v2026) — de ahí el `min_version`.

### Cálculo de importes e impuestos (Service / ServiceRegular / ServiceAssembly)

`rellenarTotal()` + `calcularImpuesto()` (idénticos en los tres) calculan `total = importe +
importe_enextranjero` aplicando IVA/recargo/retención. Leen por SQL crudo el `regimeniva`/`codretencion`
del cliente y el `tipo/iva/recargo` del impuesto: `tipo=1` aplica porcentaje, resto suma fija; respetan
régimen `exento`/`recargo` y restan la retención (IRPF). Redondean con `FS_NF0`.

### Estadísticas de repostajes (FuelKm)

- Cada repostaje calcula `km_recorridos` y `consumo` (L/100km) frente al **repostaje anterior del mismo
  vehículo** (cadena por fecha; enlace `idfuel_km_anterior`). Se recalcula en `test()`
  (`calcularEstadisticas()`).
- `onInsert/onUpdate/onDelete` → `recalcularVecinos()` refresca el siguiente y el que apuntaba a este,
  con **guarda estática `$recalculando`** contra la recursión.
- `recalcularCadenaVehiculo()`/`recalcularTodas()` (estáticos) reescriben solo columnas calculadas por
  lotes (sin hooks); los usa el worker de mantenimiento.
- Reglas de `test()`: surtidor XOR proveedor, empleado XOR conductor, tarjeta XOR medio de identificación;
  el medio de pago es obligatorio solo si `Tools::settings('openservbus','obligar_medio_pago_repostaje')`.
  Avisa (no bloquea) si la empresa no coincide con la del conductor/vehículo.

## Init.php — qué registra

- **Siempre**: extensiones de core `Extension\Controller\EditRole` y `EditUser`.
- **Solo si `CSVimport` activo**: registra la plantilla manual `Lib\ManualTemplates\KmsManual` para el
  perfil `FuelKm` (importación de repostajes por CSV).
- **Mantenimiento**: `WorkQueue::addWorker('RecalculateFuelKmStats', 'OpenServBus.recalculate-fuelkm-stats')`
  + `Maintenance::addJob([...])` (recálculo de estadísticas de repostajes).
- `update()`: instancia `Service`/`ServiceRegular` (sincroniza tablas), corre las migraciones one-shot,
  `createRoleForPlugin()` y `Cache::clear()`.
- `createRoleForPlugin()`: crea el rol **`OpenServbus`** y sus `RoleAccess` para la lista blanca de
  controladores del plugin (idempotente, en transacción).

## Área de mantenimiento (patrón worker + job)

Framework propio, **extensible por los plugins dependientes** (BusCanarias registra sus jobs aquí):

1. `Lib\Maintenance` = registro estático de jobs (`addJob`/`all`/`has`), indexado por evento.
2. `ConfigOpenServBus` (PanelController) pinta un botón por job en la pestaña **Mantenimiento**
   (`View/Maintenance.html.twig` + `getMaintenanceJobs()`); la acción `enqueue-job`
   (`execPreviousAction`) valida contra `Maintenance::has()` y hace `WorkQueue::send($event, ...)`.
3. Un `Worker` en `Worker/` escucha el evento (`RecalculateFuelKmStats::run()` → `FuelKm::recalcularTodas()`
   + `Tools::log()->notice(...)` + `return $this->done();`).

Añadir un job = `Maintenance::addJob([...])` en el `Init` + un Worker que escuche su `event` + traducciones.

## Integraciones opcionales

- **BuscadorAcumulado** (mín. **2.64**): a partir de esa versión el enriquecido de títulos
  (`||count||total||campo:Etiqueta...` para los contadores "X de Y" y el selector por campo) lo hace el
  propio plugin **de forma nativa** para TODA vista con `searchFields` (JoinModel incluido), y su
  `BAFields::build` ya ofrece los `searchFields` **sin columna visible** etiquetándolos con
  `Tools::trans(<campo>)`. OpenServBus **ya no aporta extensión ni helper propios** (se eliminaron la
  antigua `Extension\Controller\ListController` y `Lib\AccumulatedSearchTitle`, que compensaban el hueco
  de versiones ≤2.51). Solo quedan dos ajustes propios que sacan partido del enriquecido nativo:
  - `ListFuelKm` (con CSVimport) va sobre `Model/Join/FuelKm` y declara searchFields prefijados
    `d.nombre_conductor`/`v.nombre_vehiculo`/`fp.nombre_surtidor` → aparecen en el selector; sus etiquetas
    se traducen con las claves `nombre_conductor`/`nombre_vehiculo`/`nombre_surtidor` en `Translation/`.
  - `ListEmployeeAttendanceManagement` y `...Yn` declaran `addSearchFields(['observaciones'])` para que el
    bloque nativo (gated en `!empty(searchFields)`) siga pintando el contador en esas vistas.
- **CSVimport** (`Lib\ManualTemplates\KmsManual`): importa repostajes a `FuelKm`. Resuelve `idvehicle`
  desde `cod_vehicle` (pad a 3) e `iddriver` desde `cod_employee` (pad a 4 → `EmployeeOpen` → `Driver`);
  calcula `pvp_litro = precio/litros`; deduplica por `fecha+hora+idvehicle` según modo INSERT/UPDATE.

## Extensiones de core (Extension/Controller)

- `EditUser` y `EditRole`: añaden la lista **`ListAdvertismentUser`** (avisos) a la ficha, filtrada por
  `nick`/`codrole`. (Métodos `createViews`/`loadData` que devuelven `Closure`.)

## Controladores (Controller/)

`List*`/`Edit*` de las entidades de cada dominio. Patrones a seguir:
- **`ConfigOpenServBus`** (`PanelController`, plantilla `EditSettings`): pestaña de ajustes
  (`Settings`, namespace **`openservbus`**), pestaña **Mantenimiento** (HTML) y varios `List*` de
  **catálogos-tipo** (DocumentationType, EmployeeContractType, FuelType, ServiceType, TarjetaType,
  VehicleEquipamentType, VehicleType). Settings conocidos: `obligar_medio_pago_repostaje`,
  `dias_barco_reemplazo` (este último lo consume BusCanarias).
- **`EditServiceRegular`** (`EditController`) es el ejemplo del patrón multi-pestaña: añade contactos,
  periodos, itinerarios, combinaciones y valoraciones como sub-listas filtradas por `idservice_regular`,
  y rellena los `select` de conductores/monitores en `loadData()` con `setValuesFromArray()`.
- **`ListFuelKm`** usa el `Join\FuelKm` para buscar en tablas relacionadas.

## Table/ y datos

- **Nombres de tabla con desajustes a vigilar**: `employees_open` (renombrada desde `employees` para no
  colisionar con otro plugin) y `departments` (modelo `TourDepartment`). El resto siguen la convención
  plural del modelo.

## Migraciones (Migration/, one-shot, registradas en MyFiles/migrations.json)

- `DropOrphanColumnNombre` (v3.0): elimina la columna huérfana `nombre` de `employee_contracts`,
  `employees_attendance_management_yn`, `helpers`, `collaborators`. **`drivers` se excluye a propósito**:
  `drivers.nombre` es vigente (la puebla `Driver::test()` y la usa el Join de repostajes).
- `RenameEmployeesTable` (v3.1): `employees` → `employees_open` en instalaciones legadas.

Antes vivían en `Init` y se reintentaban en cada `update()`; migradas a clases one-shot.

## Tests (Test/)

Suites por combinación de plugins, cada una con su `install-plugins.txt`: `main/`, `with-csvimport/`,
`with-buscadoracumulado/`, `with-csvimport-buscadoracumulado/`. Comprueban presencia/ausencia de las
integraciones (p. ej. que con BuscadorAcumulado 2.64 el título se enriquece de forma nativa en toda vista
con `searchFields`, JoinModel de `ListFuelKm` incluido, y que las traducciones del selector existen).

## Convenciones y gotchas

- **Cabeceras de autoría**: archivos nuevos → autor Alexis (Okodex) sobre el copyright original de Carlos
  García / socger; modificados → añadir a Alexis. Licencia LGPL v3 (distinta de BusCanarias, que es EULA).
- **Soft-delete**: al desactivar cualquier registro hay que dar `motivobaja` o `test()` falla.
- **Extensiones = solo Closures** (Reflection registra todos los métodos como pipes). Helpers, a `Lib/`.
- **Rebuild/deploy** tras tocar `Init.php`, modelos o workers (regenera `Dinamic`; si no, el worker/rol
  no se registra). Es submódulo dentro de Mesa_FS: commitear aquí y actualizar el puntero en el padre.
- **Cascadas por SQL crudo**: editar `Service` propaga a `service_assemblies`; borrar/crear `FuelKm`
  recalcula vecinos; `ServiceValuation` recalcula importes del `Service`. Al tocar un modelo, revisar sus
  UPDATE en cascada para no dejar datos desincronizados.
- **`Driver::__get('nombre')`**: convive con la propiedad pública `$nombre` (que `test()` sí persiste);
  el `__get` es un fallback y solo se dispara si la propiedad no está inicializada — cuidado al refactorizar.
- **Traducciones**: `Translation/` cubre muchos idiomas; los mantenidos al día son **es_ES** y **en_EN**
  (el resto arrastra las cadenas antiguas de FacturaScripts).
