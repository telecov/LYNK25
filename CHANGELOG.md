# Changelog
Todos los cambios importantes de este proyecto serán documentados en este archivo.

---

## [1.4.0] - 2026-03-10
### Añadido
- se agrega idioma ingles con cambio entre español o ingles
- cambio de ip desde configuracion
- cambio de configuracion de archivo ini desde configuracion

  
## [1.3.0] - 2025-12-22
### Añadido
- Se agrega boton para borrado del cache de estaciones, permite eliminar errores de lectura por ejemplo cuando llega una estacion y queda con otro ID, al borrar se actualizan
- en pie de pagina puedes ahora puedes ver la version actual que estas trabajando
- 
### Cambiado
- Se borra boton de actualizacion en linea ya que las actualizaciones no cargaban bien, se corregira en versiones posteriores, ahora puedes actualizar borrando el repo t cargarlo nuevamente

### Reparado
- se repara el error de lectura que impedia ver el reflector ONLINE, cuado se cargan los datos de dvref en personalizacion. 
- ajustes menores.
 
## [1.2.0] - 2025-12-08
### Añadido
- Gestión dinámica de configuración mediante archivos `.ini`
- Panel web para modificar parámetros críticos del reflector
- Control de servicios del sistema (start / stop / restart) desde la interfaz web
- Opción para cambiar la IP del servidor sin edición manual por consola
- Opción para modificar el nombre del reflector / servidor desde el panel
- Validación del estado real de los servicios mediante systemd

### Cambiado
- Reestructuración interna del sistema separando:
  - Configuración
  - Lógica del sistema
  - Interfaz de usuario
- Mejora en el flujo operativo del reflector, reduciendo la dependencia de SSH
- Actualización del panel de configuración y secciones administrativas
- Mejor claridad visual en el estado del sistema y servicios

### Reparado
- Ajustes menores en la lectura de parámetros de configuración
- Correcciones en la detección del estado del reflector

### Notas
- Esta versión marca la transición de LYNK25 desde un dashboard informativo
  a un **panel de administración funcional del reflector P25**

---

## [1.1.0] - 2025-09-01
### Añadido
- Dashboard web para visualización del reflector P25
- Lectura y procesamiento de logs diarios del reflector
- Visualización de:
  - Estaciones conectadas
  - Tráfico y transmisiones
  - Estado general del sistema
- Identificación de inicios y finales de transmisión

### Cambiado
- Mejora en el diseño general del dashboard
- Optimización en la lectura de logs grandes

### Reparado
- Correcciones menores en el parseo de logs
- Ajustes visuales en tablas y paneles

---

## [1.0.0] - 2025-08-20
### Añadido
- Versión inicial del proyecto LYNK25
- Base del dashboard web para reflector P25
- Estructura inicial de archivos y logs
- Implementación básica de visualización de estado

---

