
# 📡 LYNK25 – Dashboard Web para Reflector P25

## 🧠 Descripción
**LYNK25** es un dashboard web de monitoreo y personalización para **reflectores P25**. Muestra tráfico en tiempo real, estaciones conectadas, historial de transmisiones, **ranking** con podio, **mapa** por ciudad asociada a la licencia, y **notificaciones por Telegram**. Incluye página **About**, página de **personalización del header**, y **verificación de actualizaciones**.

Inspirado por el trabajo abierto de **Jonathan Naylor (G4KLX)**, **DVReflector de NØSTAR**, y por la comunidad que hace posibles estos sistemas e inspira dashboards más intuitivos; adicionalmente como es de costubre, nombre, tipologia y colores, se basa en mi entorno familia, esta vez en **mi esposa Jocelyn**, gracias por siempre apoyar mis proyectos


## 🚀 Funcionalidades
- **Tiempo real:** tráfico y estado del reflector (`estado_reflector.json`).
- **Estaciones conectadas:** lista dinámica, resalta la última 🆕.
- **Historial:** transmisiones recientes con filtros.
- **Ranking y podio:** actividad radial automática (Top 3).
- **Mapa:** usuarios geolocalizados por ciudad/licencia (RadioID).
- **Telegram:** alertas a operadores/admins; registro de envíos.
- **Personalización:** edición de título/logo/encabezado sin tocar código.
- **About:** créditos, enlaces, versión.
- **Actualizaciones:** chequeo de versión con `version.json`.

---

## 🧰 Estructura del Proyecto (real)

```bash
├── index.php
├── about.php
├── personalizar_header.php
├── version.json
├── css/
│ ├── index.php
│ └── style.css
├── img/
│ ├── index.php
│ ├── lynk25about.png
│ ├── lynk25logo.png
│ ├── lynk25_favicon.png
│ └── zdmrlogoindex.png
├── js/
│ ├── index.php
│ ├── main.js
│ ├── trafico.js
│ └── update.js
├── includes/
│ ├── index.php
│ ├── config.php
│ ├── cache_estaciones.php
│ ├── check_update.php
│ ├── generar_estado_reflector.php
│ ├── heard.php
│ ├── logs.php
│ ├── mapa.php
│ ├── metrics.php
│ ├── radioid.php
│ ├── telegram.php
│ ├── telegram_config.json
│ ├── telegram_notif.php
│ └── timezone.php
└── data/
├── admin_auth.json
├── dvref_config.json
├── dvref_status.json
├── estaciones_cache.json
├── estaciones_current.json
├── estado_reflector.json
├── header_config.json
├── index.php
├── radioid_cache.json
├── telegram_notif.log
├── telegram_state.json
└── user.csv

```
## 🌍 Integraciones
- **DVReflector API:** para validadcion de reflector online
- **RadioID API:** para nombre/ciudad por ID.
- **Telegram:** para envio de notificaciones, para el admin o para el grupo

## 👉 [Ver instalacion](install.md) 

## 🔗 Enlaces
- 🌐 Sitio: https://zonadmr.cl  
- 🧩 Repo: https://github.com/telecov/LYNK25

## 🤝 Créditos
- **Jonathan Naylor (G4KLX)** – base de software para reflectores/MMDVM.  
- **DVReflector de NØSTAR** – pilar para gestión moderna P25.  
- Comunidad internacional de radioaficionados digitales.  

> 🛰️ *LYNK25 – 2025*
