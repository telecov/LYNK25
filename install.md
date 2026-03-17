# 🛠️ Instalación – Dashboard Web para Reflector P25

🌐 🇪🇸 Español | [🇺🇸 English](install_en.md)

---

## 🖥️ Requisitos

**Es necesario tener instalado y funcionando DVReflector de NØSTAR.**  
Repositorio oficial:  
https://github.com/nostar/DVReflectors

Si ya tienes tu reflector P25 funcionando, puedes saltar directamente a la instalación del **dashboard web**.

Si estás comenzando desde cero, puedes apoyarte también en el video de presentación e instalación de LYNK25.

⚠️ **Precaución:**  
Si ya tienes un dashboard web funcionando, se recomienda realizar un respaldo antes de continuar, o bien instalar este dashboard de forma paralela para hacer pruebas.  
Por ejemplo, puedes instalarlo en una ruta como:

```bash
/var/www/html/p25/
```

Así evitas perder tu instalación actual. Si luego te gusta el resultado, puedes reemplazar tu dashboard anterior.

---

## 💻 Hardware recomendado

### Requisitos mínimos
- **CPU:** Dual Core 1.2 GHz o superior (Intel Atom / Celeron)
- **RAM:** 1 GB mínimo (2 GB recomendado)
- **Almacenamiento:** 8 GB mínimo (SD o HDD)
- **Red:** Ethernet 100 Mbps o Wi-Fi b/g/n
- **Sistema Operativo:** Debian, Ubuntu Server, Raspberry Pi OS, Bananian

### Equipos recomendados
- Raspberry Pi 3 o superior
- Mini PC o computador con Linux
- Servidor liviano con Debian o Ubuntu

---

## ✅ Compatibilidad probada

**LYNK25** ha sido probado y funciona correctamente en:

- **Debian 12**
- **Raspberry Pi OS / Raspbian 12**
- **Ubuntu Server**
- **Armbian Bookworm**

### Distribución recomendada
- **Debian 12**

---

## 📦 Software necesario

### Para el funcionamiento del dashboard
- Apache2
- PHP 8.2 o superior
- Git
- cURL
- unzip
- network-manager

### Software recomendado para configuración remota
- **IP Scanner** → para identificar la IP del equipo
- **PuTTY** → para administrar Linux vía SSH
- **Raspberry Pi Imager** → recomendado para instalar Raspberry Pi OS

---

# 📡 Instalación del Reflector P25 (DVReflector)

## 1) Crear usuario para el reflector

```bash
sudo adduser p25reflector
sudo usermod -aG sudo p25reflector
```

---

## 2) Descargar DVReflector P25

```bash
cd /opt
sudo git clone https://github.com/nostar/DVReflectors.git
sudo chmod -R 755 DVReflectors
cd DVReflectors/P25Reflector
```

---

## 3) Compilar e instalar

```bash
sudo make
sudo install -m 755 P25Reflector /usr/local/bin/
```

---

## 4) Copiar archivo INI a `/etc/`

```bash
sudo cp /opt/DVReflectors/P25Reflector/P25Reflector.ini /etc/P25Reflector.ini
```

---

## 5) Crear carpeta de logs

```bash
sudo mkdir -p /var/log/p25reflector
sudo chmod 777 /var/log/p25reflector
```

---

## 6) Actualizar archivo `DMRIds.dat`

```bash
cd /opt/DVReflectors/P25Reflector
sudo rm -f DMRIds.dat
wget https://raw.githubusercontent.com/telecov/LYNK25/main/DMRIds.dat -O DMRIds.dat
sudo chmod 644 DMRIds.dat
```

---

## 7) Configurar el archivo `/etc/P25Reflector.ini`

```bash
sudo nano /etc/P25Reflector.ini
```

Usa esta configuración base:

```ini
[General]
Daemon=0

[Id Lookup]
Name=/opt/DVReflectors/P25Reflector/DMRIds.dat
Time=24

[Log]
DisplayLevel=1
FileLevel=1
FilePath=/var/log/p25reflector
FileRoot=P25Reflector
FileRotate=1

[Network]
Port=41000
Debug=0
```

---

## 8) Crear servicio systemd para inicio automático

```bash
sudo nano /etc/systemd/system/p25reflector.service
```

Contenido del servicio:

```ini
[Unit]
Description=P25 Reflector
After=network.target

[Service]
User=p25reflector
ExecStart=/usr/local/bin/P25Reflector /etc/P25Reflector.ini
Restart=always

[Install]
WantedBy=multi-user.target
```

---

## 9) Permisos sudo para gestión desde la web

Esto permite que el dashboard pueda reiniciar servicios, consultar estado del reflector o ejecutar cambios básicos del servidor.

```bash
sudo visudo -f /etc/sudoers.d/lynk25
```

Agrega lo siguiente:

```bash
www-data ALL=(ALL) NOPASSWD:/usr/bin/systemctl start p25reflector.service
www-data ALL=(ALL) NOPASSWD:/usr/bin/systemctl stop p25reflector.service
www-data ALL=(ALL) NOPASSWD:/usr/bin/systemctl restart p25reflector.service
www-data ALL=(ALL) NOPASSWD:/usr/bin/systemctl status p25reflector.service
www-data ALL=(ALL) NOPASSWD:/usr/sbin/reboot
www-data ALL=(ALL) NOPASSWD:/usr/bin/hostnamectl
www-data ALL=(ALL) NOPASSWD:/usr/bin/nmcli
```

---

## 10) Activar e iniciar el reflector

```bash
sudo systemctl daemon-reload
sudo systemctl enable p25reflector
sudo systemctl start p25reflector
sudo systemctl status p25reflector
```

---

## 11) Limpieza de procesos (solo si hiciste pruebas previas)

```bash
sudo pkill -f P25Reflector
sudo systemctl reset-failed p25reflector
```

---

# 📦 Instalación del Dashboard Web LYNK25

## 1) Instalar dependencias

```bash
sudo apt update
sudo apt install apache2 -y
sudo apt install php libapache2-mod-php -y
sudo apt install php-curl unzip -y
sudo apt install network-manager -y
sudo apt install git -y
sudo systemctl restart apache2
```

---

## 2) Clonar LYNK25 en el servidor web

```bash
cd /var/www/
sudo rm -rf /var/www/html
sudo git clone https://github.com/telecov/LYNK25.git html
```

---

## 3) Asignar permisos

```bash
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 755 /var/www/html
sudo chmod 664 /var/www/html/data/*.json
```

---

## 4) Crear servicio de Telegram en tiempo real

```bash
sudo nano /etc/systemd/system/lynk25-realtime.service
```

Contenido del servicio:

```ini
[Unit]
Description=LYNK25 Telegram Realtime Notifier
After=network.target

[Service]
ExecStart=/usr/bin/php /var/www/html/includes/telegram_realtime.php
Restart=always
User=teleco

[Install]
WantedBy=multi-user.target
```

Activar servicio:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now lynk25-realtime.service
```

> ⚠️ Cambia `User=teleco` por el usuario real de tu sistema si es distinto.

---

## 5) Crear tareas Cron para reportes y estado del reflector

Editar el crontab del usuario `www-data`:

```bash
sudo crontab -u www-data -e
```

Agregar estas líneas:

```bash
* * * * * /usr/bin/php /var/www/html/includes/telegram_notif.php >> /var/www/html/data/cron_telegram.log 2>&1
* * * * * /usr/bin/php /var/www/html/includes/generar_estado_reflector.php >> /var/www/html/data/cron_estado.log 2>&1
```

Verificar crontab:

```bash
sudo crontab -u www-data -l
```

---

## 6) Acceso web

Abre tu navegador y visita:

```bash
http://tu-servidor/
```

Ejemplo:

```bash
http://192.168.1.50/
```

---

# 🧠 Configuración inicial

Toda la configuración principal de **LYNK25** se realiza desde la interfaz web, sin necesidad de editar archivos manualmente.

## Página de personalización

Accede a:

```bash
http://tu-servidor/personalizar_header.php
```

### Credenciales por defecto

```bash
Usuario: admin
Clave: lynk252025
```

---

## Desde esta página podrás configurar

### 🛰️ DVReflector
- Nombre del sistema o reflector
- Dirección IP o dominio del reflector P25
- Puerto
- Descripción
- Estado del enlace
- Estadísticas generales

### 💬 Telegram
- Activar o desactivar notificaciones
- Configurar bot y canal/grupo
- Asociar Telegram al sistema
- Controlar mensajes automáticos de actividad o errores

### 🎨 Apariencia y encabezado
- Cambiar logos
- Cambiar íconos
- Editar textos principales
- Modificar colores
- Configurar imagen de fondo
- Personalizar título y lema del proyecto

---

## 🤖 Configurar Telegram (opcional)

1. Crear un bot con **@BotFather**
2. Obtener el **token HTTP API**
3. Crear un canal o grupo de Telegram
4. Agregar el bot como administrador
5. Obtener el ID del canal o grupo
6. Asociarlo desde la interfaz web

Ejemplo para consultar actualizaciones del bot:

```bash
https://api.telegram.org/botTOKEN/getUpdates
```

---

## 📁 Archivos de configuración generados

Los cambios realizados desde la interfaz se guardan automáticamente en:

```bash
/data/header_config.json
/data/dvref_config.json
/data/telegram_state.json
```

---

## ✅ Recomendación final

Cuando termines la instalación:

- Verifica que el reflector esté activo
- Confirma que Apache cargue correctamente el dashboard
- Revisa permisos en `/data`
- Comprueba que las tareas cron estén funcionando
- Prueba la personalización web
- Configura Telegram si deseas alertas automáticas

---

## ❤️ Consejo importante

Antes de actualizar o modificar una instalación en producción, realiza siempre un respaldo.

LYNK25 está pensado para ayudarte a monitorear y gestionar tu reflector P25 de forma más cómoda, visual y moderna, pero cada entorno puede tener diferencias. Probar primero en paralelo siempre es una buena idea.
