# OpenServBus
Plugin para empresas del gremio de transporte discrecional y regular.
- https://facturascripts.com/plugins/openservbus

## Nombre de carpeta
Como con todos los plugins, la carpeta se debe llamar igual que el plugin. En este caso **OpenServBus**.

## Compatibilidad con BuscadorAcumulado
La integración con el plugin **BuscadorAcumulado** (búsqueda acumulada en los listados) requiere
**BuscadorAcumulado 2.61 Beta** como versión mínima. A partir de esa versión, el selector de campo
del buscador incluye también los `searchFields` de OpenServBus que **no** tienen columna visible en
el listado (que BuscadorAcumulado, por sí solo, deja fuera al armar el selector desde las columnas):

- campos calculados de modelos `JoinModel` (p. ej. conductor / vehículo / surtidor en `ListFuelKm`);
- campos reales de modelos normales que no se muestran como columna (p. ej. provincia / código postal
  en `ListStop`, dirección en `ListEmployeeOpen` / `ListGarage`, etc.).

Con versiones anteriores de BuscadorAcumulado esa adaptación no está soportada.

## Enlaces de interés
- [Cómo instalar plugins en FacturaScripts](https://facturascripts.com/publicaciones/como-instalar-un-plugin-en-facturascripts)
- [Curso de FacturaScripts](https://youtube.com/playlist?list=PLNxcJ5CWZ8V6nfeVu6vieKI_d8a_ObLfY)
- [Programa para hacer facturas gratis](https://facturascripts.com/programa-para-hacer-facturas)
- [Cómo instalar FacturaScripts en Windows](https://facturascripts.com/instalar-windows)
- [Programa para imprimir tickets gratis](https://facturascripts.com/remote-printer)