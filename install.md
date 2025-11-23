**Dashboard Web para Reflector P25**  

---

🖥️ Requisitos
*** Tener instalado y corriendo DVREFLECTOR de NOSTAR ***
https://github.com/nostar/DVReflectors

si ya lo tienes instalado y funcionando, puedes saltar directamente a la instalacion del DASHBOARD, ahora si estas iniciando puedes seguir el paso a paso apoyando de este video

PRECAUCION, si ya tienes un dashboard web funcionando te recomiendo realizar backup, o instalar este dashboard paralelo para que lo pruebes antes, por ejemplo guardarlo en html/p25/ para asi no perder lo que tienes en html, si es de tu gusto puede eliminar todo y seguir el procedimieto 

###

* Hardware recomendado:

* Requisitos mínimos:
CPU: Dual Core 1.2 GHz o superior (Intel Atom / Celeron)
RAM: 1 GB mínimo (2 GB recomendado)
Almacenamiento: 8 GB (SD o HDD)
Red: Ethernet 100 Mbps o Wi-Fi b/g/n
SO: Debian, Ubuntu Server, Raspbian, Bannanian 

* Raspberry PI 3 

LYNK25 ha sido probado y funciona de forma óptima en:

Distribución recomendada: Debian 12+ / Raspbian 12
Entornos compatibles: Raspberry Pi OS, Ubuntu Server, Armbian (bookwoorm)
Equipo recomendado: Computador o mini-servidor con Linux

Software necesario:

Apache2
PHP 8.2 o superior
Git
cURL

** Software necesario para configurar **
IPSCANNER - para identificar ip de equipo
PUTTY - para administrar Linux por SSH

Para instalar en Raspberry OS se recomieda Raspberry pi Imager


📡 Instalación del Reflector P25 (DVReflector)

Creacion de usuario

```bash
sudo adduser p25reflector
sudo usermod -aG sudo p25reflector
```
Descargar DVReflector P25

```bash
cd /opt
sudo git clone https://github.com/nostar/DVReflectors.git
sudo chmod -R 755 DVReflectors
cd DVReflectors/P25Reflector
```
Compilar e instalar

```bash
sudo make
sudo install -m 755 P25Reflector /usr/local/bin/
```
Copiar archivo INI a /etc/

```bash
sudo cp /opt/DVReflectors/P25Reflector/P25Reflector.ini /etc/P25Reflector.ini
```
Crear carpeta de logs

```bash
sudo mkdir -p /var/log/p25reflector
sudo chmod 777 /var/log/p25reflector
```
Actualizar archivo DMRIds.dat

```bash
cd /opt/DVReflectors/P25Reflector
sudo rm -f DMRIds.dat
wget https://raw.githubusercontent.com/telecov/LYNK25/main/DMRIds.dat -O DMRIds.dat
sudo chmod 644 DMRIds.dat
```

Configurar el archivo /etc/P25Reflector.ini
```bash
sudo nano /etc/P25Reflector.ini
```
```bash
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

Crear servicio Systemd para autoinicio

```bash
sudo nano /etc/systemd/system/p25reflector.service
```

```bash
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

```bash
sudo systemctl daemon-reload
sudo systemctl enable p25reflector
sudo systemctl start p25reflector
sudo systemctl status p25reflector
```

Limpieza de procesos (si hiciste pruebas)

```bash
sudo pkill -f P25Reflector
sudo systemctl reset-failed p25reflector
```


## 📦 Instalación del Dashboard

🧰 Paso a paso

```bash
sudo apt update
sudo apt install apache2 -y
sudo apt install php libapache2-mod-php -y
sudo apt install php-curl unzip -y
sudo apt install network-manager -y
sudo apt install git -y
```

1. Copia la carpeta completa **LYNK25** a tu servidor web:  
```bash
cd /var/www/
sudo rm -rf /var/www/html
sudo git clone https://github.com/telecov/LYNK25.git html
```

2. Permisos
```bash
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 755 /var/www/html
sudo chmod 664 /var/www/html/data/*.json
```

2.1 Crear servicio Telegram Tiempo Real
```bash
sudo nano /etc/systemd/system/lynk25-realtime.service
```
escribe, guarda este servicio
```bash
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
Activa el servicio

```bash
sudo systemctl enable --now lynk25-realtime.service
```

Ejecucion de Cron para informes del servidor via telegram y Alerta de Reflector

```bash
sudo crontab -u www-data -e
```

```bash
* * * * * /usr/bin/php /var/www/html/includes/telegram_notif.php >> /var/www/html/data/cron_telegram.log 2>&1
* * * * * /usr/bin/php /var/www/html/includes/generar_estado_reflector.php >> /var/www/html/data/cron_estado.log 2>&1
*/1 * * * * php /var/www/html/includes/telegram_notificaciones.php

```

Verifica el Crontab

```bash
sudo crontab -l
```

3. Acceso WEB
Accede desde tu navegador:

http://tu-servidor/


## 🧠 Configuración Inicial

Toda la configuración de LYNK25 se realiza desde la interfaz web, sin editar archivos manualmente.
Página de Personalización

Accede a:
http://tu-servidor/personalizar_header.php

Contraseña por defecto
```bash
  admin
  lynk252025
```

Desde esta página podrás configurar:

## 🛰️ DVReflector

Nombre del sistema o reflector
Dirección IP o dominio del reflector P25
Puerto y descripción
Estado de enlace y estadísticas

## 💬 Telegram

* Activar o desactivar notificaciones

Configura Telegram (opcional)
Crea un bot en @BotFather
Obten el token http api
crea un canal o agraga tu bot como admin al grupo Telegram
buscar el ID del canal o grupo a utilizar https://api.telegram.org/bot/getUpdates
Asociar grupo o canal

* Controla mensajes automáticos de actividad o errores

## 🎨 Apariencia y encabezado

* Cambiar logos, íconos y textos principales
* Editar colores o imagen de fondo

## Personalizar el título y lema del proyecto

* Los cambios se guardan automáticamente en:

```bash
/data/header_config.json
/data/dvref_config.json
/data/telegram_state.json
```
