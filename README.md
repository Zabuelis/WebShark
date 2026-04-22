# WebShark

WebShark is a web based network packet analyzer.

Upload a `.pcap` or `.pcapng` files and inspect its packets, protocols and IP flows in your browser.

Built with Laravel, Vue.js, Python, Scapy, tshark, PostgreSQL and Valkey.

[![Live Demo](https://img.shields.io/badge/Live_Demo-WebShark_CD-007ec6?style=flat-square)](http://193.219.91.103:8000/)

> [!NOTE]
> Example PCAP files can be found in `src/pcap_samples`

---

# Quick Start (Recommended)

For a debian-based machine, run the following command.

This script will clone the repository if needed, install Docker and perform all setup steps:

```bash
curl -fsSL https://git.mif.vu.lt/luse0397/webshark/-/raw/main/setup.sh | bash
```

## Managing the Application

Once the script finishes, you must be inside the project directory (`⁠webshark/`) to run these commands:

### Stop the application
```bash
make down
```

### Start the application
```bash
make up
```

### Access the UI (local machine only)
```bash
http://localhost:8000/
```

## Running on a remote VM

If you are hosting on a VM, close your current SSH session and reconnect with port forwarding

```bash
ssh -L 8000:localhost:8000 -L 5173:localhost:5173 <OpenNebula CONNECT_INFO1 (without the ssh)>
```

---

# Manual Installation

Use these steps if you need to set up the environment manually.

## 0. Prerequisites and Cloning (First time only)

Ensure you have ⁠git and ⁠docker installed.

```bash
git clone https://git.mif.vu.lt/luse0397/webshark.git
cd webshark
```

## 1. Setup (First time only)

First, copy the example environment file:

```bash
cp src/WebShark/.env.example src/WebShark/.env
```
For macOS: update WWWUSER and WWWGROUP in .env (run 'id -u' and 'id -g')

## 2. Start the application

Run this from the root folder. We use the `--env-file` flag so Docker can find your config:

```bash
sudo docker compose --env-file src/WebShark/.env up -d --build
```

## 3. Install dependencies (First time only)
```bash
sudo docker exec -it webshark-app composer install
sudo docker compose --env-file src/WebShark/.env run --rm node npm install
sudo docker compose --env-file src/WebShark/.env up -d --build
sudo docker exec -it webshark-node npm install
```

## 4. Laravel setup (First time only)
```bash
sudo docker exec -it webshark-app php artisan key:generate
sudo docker exec -it webshark-app php artisan storage:link
sudo docker exec -it webshark-app php artisan migrate
```

## 5. Running on a remote VM (Optional)

If you are hosting on a VM, close your current SSH session and reconnect with port forwarding

```bash
ssh -L 8000:localhost:8000 -L 5173:localhost:5173 <OpenNebula CONNECT_INFO1 (without the ssh)>
```

## 6. Restart it (First time only)
```bash
sudo docker compose --env-file src/WebShark/.env down
sudo docker compose --env-file src/WebShark/.env up -d --build
```

## 7. Test it
```bash
http://localhost:8000/
```

## 8. Stop the application
```bash
sudo docker compose --env-file src/WebShark/.env down
```

---

# Debugging & Maintenance

## To see PostgreSQL
```bash
sudo docker compose --env-file src/WebShark/.env exec db psql -U webshark -d websharkdb -c "SELECT * FROM analysis_job;"
```

## To see Valkey
```bash
sudo docker compose --env-file src/WebShark/.env exec valkey valkey-cli KEYS "*"
```

## To see Cron
```bash
sudo docker compose --env-file src/WebShark/.env logs -f cron
```

## To run tests
```bash
sudo docker exec -it webshark-app php artisan test
```

## Scalability for queue-worker
```bash
sudo docker compose --env-file src/WebShark/.env up -d --scale queue-worker=3
```
