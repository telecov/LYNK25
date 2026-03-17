# 🛠️ Installation – P25 Reflector Web Dashboard

🌐 [🇪🇸 Versión en Español](install.md) | 🇺🇸 English

---

## 🖥️ Requirements

**You must already have DVReflector by NØSTAR installed and running.**  
Official repository:  
https://github.com/nostar/DVReflectors

If your P25 reflector is already working, you can skip directly to the **dashboard installation** section.

If you are starting from scratch, you can also follow the LYNK25 presentation and installation video for guidance.

⚠️ **Warning:**  
If you already have a working web dashboard, it is strongly recommended to make a backup first, or install this dashboard in parallel for testing.  
For example, you can place it under:

```bash
/var/www/html/p25/
```

This helps avoid overwriting your current setup. If you like the result, you can later replace the old dashboard.

---

## 💻 Recommended hardware

### Minimum requirements
- **CPU:** Dual Core 1.2 GHz or higher (Intel Atom / Celeron)
- **RAM:** 1 GB minimum (2 GB recommended)
- **Storage:** 8 GB minimum (SD card or HDD)
- **Network:** 100 Mbps Ethernet or Wi-Fi b/g/n
- **Operating System:** Debian, Ubuntu Server, Raspberry Pi OS, Bananian

### Recommended devices
- Raspberry Pi 3 or newer
- Linux mini PC or desktop
- Lightweight Linux server

---

## ✅ Tested compatibility

**LYNK25** has been tested and works properly on:

- **Debian 12**
- **Raspberry Pi OS / Raspbian 12**
- **Ubuntu Server**
- **Armbian Bookworm**

### Recommended distribution
- **Debian 12**

---

## 📦 Required software

### For the dashboard
- Apache2
- PHP 8.2 or higher
- Git
- cURL
- unzip
- network-manager

### Recommended tools for setup
- **IP Scanner** → to identify the device IP address
- **PuTTY** → for Linux administration over SSH
- **Raspberry Pi Imager** → recommended for installing Raspberry Pi OS

---

# 📡 P25 Reflector Installation (DVReflector)

## 1) Create reflector user

```bash
sudo adduser p25reflector
sudo usermod -aG sudo p25reflector
```

---

## 2) Download DVReflector P25

```bash
cd /opt
sudo git clone https://github.com/nostar/DVReflectors.git
sudo chmod -R 755 DVReflectors
cd DVReflectors/P25Reflector
```

---

## 3) Compile and install

```bash
sudo make
sudo install -m 755 P25Reflector /usr/local/bin/
```

---

## 4) Copy INI file to `/etc/`

```bash
sudo cp /opt/DVReflectors/P25Reflector/P25Reflector.ini /etc/P25Reflector.ini
```

---

## 5) Create log folder

```bash
sudo mkdir -p /var/log/p25reflector
sudo chmod 777 /var/log/p25reflector
```

---

## 6) Update `DMRIds.dat`

```bash
cd /opt/DVReflectors/P25Reflector
sudo rm -f DMRIds.dat
wget https://raw.githubusercontent.com/telecov/LYNK25/main/DMRIds.dat -O DMRIds.dat
sudo chmod 644 DMRIds.dat
```

---

## 7) Configure `/etc/P25Reflector.ini`

```bash
sudo nano /etc/P25Reflector.ini
```

Use this base configuration:

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

## 8) Create systemd service for auto-start

```bash
sudo nano /etc/systemd/system/p25reflector.service
```

Service content:

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

## 9) Sudo permissions for web management

This allows the dashboard to restart services, check reflector status, or perform basic server-side tasks.

```bash
sudo visudo -f /etc/sudoers.d/lynk25
```

Add:

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

## 10) Enable and start reflector

```bash
sudo systemctl daemon-reload
sudo systemctl enable p25reflector
sudo systemctl start p25reflector
sudo systemctl status p25reflector
```

---

## 11) Cleanup old processes (only if you made previous tests)

```bash
sudo pkill -f P25Reflector
sudo systemctl reset-failed p25reflector
```

---

# 📦 LYNK25 Dashboard Installation

## 1) Install dependencies

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

## 2) Clone LYNK25 into your web server

```bash
cd /var/www/
sudo rm -rf /var/www/html
sudo git clone https://github.com/telecov/LYNK25.git html
```

---

## 3) Set permissions

```bash
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 755 /var/www/html
sudo chmod 664 /var/www/html/data/*.json
```

---

## 4) Create Telegram real-time service

```bash
sudo nano /etc/systemd/system/lynk25-realtime.service
```

Service content:

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

Enable service:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now lynk25-realtime.service
```

> ⚠️ Replace `User=teleco` with your actual Linux username if needed.

---

## 5) Create Cron jobs for reports and reflector status

Edit the `www-data` crontab:

```bash
sudo crontab -u www-data -e
```

Add:

```bash
* * * * * /usr/bin/php /var/www/html/includes/telegram_notif.php >> /var/www/html/data/cron_telegram.log 2>&1
* * * * * /usr/bin/php /var/www/html/includes/generar_estado_reflector.php >> /var/www/html/data/cron_estado.log 2>&1
```

Verify crontab:

```bash
sudo crontab -u www-data -l
```

---

## 6) Web access

Open your browser and go to:

```bash
http://your-server/
```

Example:

```bash
http://192.168.1.50/
```

---

# 🧠 Initial configuration

All major **LYNK25** settings can be configured from the web interface without manually editing files.

## Personalization page

Go to:

```bash
http://your-server/personalizar_header.php
```

### Default credentials

```bash
User: admin
Password: lynk252025
```

---

## From this page you can configure

### 🛰️ DVReflector
- Reflector or system name
- P25 reflector IP address or domain
- Port
- Description
- Link status
- General statistics

### 💬 Telegram
- Enable or disable notifications
- Configure bot and channel/group
- Link Telegram to the system
- Control automatic alerts and error messages

### 🎨 Appearance and header
- Change logos
- Change icons
- Edit main texts
- Modify colors
- Set background image
- Customize project title and slogan

---

## 🤖 Telegram setup (optional)

1. Create a bot with **@BotFather**
2. Get the **HTTP API token**
3. Create a Telegram channel or group
4. Add the bot as administrator
5. Get the channel or group ID
6. Link it from the web interface

Example to check bot updates:

```bash
https://api.telegram.org/botTOKEN/getUpdates
```

---

## 📁 Generated configuration files

Changes made from the web interface are automatically saved into:

```bash
/data/header_config.json
/data/dvref_config.json
/data/telegram_state.json
```

---

## ✅ Final recommendation

After installation:

- Verify the reflector is running
- Confirm Apache loads the dashboard correctly
- Check permissions inside `/data`
- Verify cron jobs are working
- Test the personalization page
- Configure Telegram if you want automated alerts

---

## ❤️ Important note

Before updating or modifying a production system, always make a backup first.

LYNK25 is designed to help you monitor and manage your P25 reflector in a more visual, modern, and practical way, but every environment is different. Testing in parallel first is always a smart choice.
