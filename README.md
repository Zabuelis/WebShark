# WebShark

WebShark is a web based network packet analyzer.

Upload a `.pcap` or `.pcapng` files and inspect its packets, protocols and IP flows in your browser.

Built with Laravel, Vue.js, Python, tshark, PostgreSQL and Valkey.

[![Live Demo](https://img.shields.io/badge/Live_Demo-WebShark_CD-007ec6?style=flat-square)](http://193.219.91.103:8000/)

> [!NOTE]
> Example PCAP files can be found in `src/pcap_samples`

---

# Development Quick Start (Recommended)

For a Debian-based machine, run the following command.

This script will clone the repository if needed, install Docker, perform all setup steps and start the application

> [!NOTE]
> To use a specific branch add `-s -- my-branch-name` at the end

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

### Running on a remote VM (Optional)

If you are hosting on a VM, close your current SSH session and reconnect with port forwarding command below, then you can access it with http://localhost:8000/

```bash
ssh -L 8000:localhost:8000 -L 5173:localhost:5173 <OpenNebula CONNECT_INFO1 (without the ssh)>
```

### Access the UI
```bash
http://localhost:8000/
```

---

# Manual Development Installation

Use these steps if you need to set up the environment manually.

## 0. Prerequisites and Cloning (First time only)

Ensure you have Git and Docker installed, then clone the repository and navigate into the directory.

```bash
git clone https://git.mif.vu.lt/luse0397/webshark.git
cd webshark
```

## 1. Setup (First time only)

First, copy the example environment file:

```bash
cp src/WebShark/.env.example src/WebShark/.env
```
For macOS: update `WWWUSER` and `WWWGROUP` in `.env` (run `id -u` and `id -g`)

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

If you are hosting on a VM, close your current SSH session and reconnect with port forwarding command below, then you can access it with http://localhost:8000/

```bash
ssh -L 8000:localhost:8000 -L 5173:localhost:5173 <OpenNebula CONNECT_INFO1 (without the ssh)>
```

## 6. Restart it (First time only)
```bash
sudo docker compose --env-file src/WebShark/.env down
sudo docker compose --env-file src/WebShark/.env up -d --build
```

## 7. Access the UI
```bash
http://localhost:8000/
```

## 8. Stop the application
```bash
sudo docker compose --env-file src/WebShark/.env down
```

---
# Usability Guide

## Filters

For quicker packet search this tool supports filtering. Filters are finite, however they can be combined to create more strict filtering conditions. 

1. Filtering example:
   - Filter chaining can be performed by using && (and) operator. This operator combines several different filters as *A AND B AND C* condition:
      > ip.src == 192.168.1.1 && proto == TLS && ip.dst == 18.97.36.54
2. Available filters
   - Filters must follow the presented pattern or else will return incorrect or no result.
      | Filter | Action |
      |--------|--------|
      | ip.src == | Filters results based on specified source IP address. | 
      | ip.dst == | Filters results based on specifies destination IP address. |
      | port.src == | Filters results based on specified source PORT. |
      | port.dst == | Filters results based on specified destination PORT. |
      | proto == | Filters results based on specified protocol. This filter is case sensitive. In most cases all protocols are upper case except special cases such as IPv4, IPv6. |
      | tcp.flow == | Filters results based on specified TCP flow. TCP flows are reassembled during analysis process start from 0. |

---

# Debugging & Maintenance

## To see PostgreSQL
```bash
sudo docker compose --env-file src/WebShark/.env exec db psql -U webshark -d websharkdb -c "SELECT * FROM analysis_job;"
```

## To see Valkey
```bash
sudo docker compose --env-file src/WebShark/.env exec valkey valkey-cli KEYS "*"

sudo docker compose --env-file src/WebShark/.env exec valkey valkey-cli -n 1 KEYS "*"
```

## To see logs
```bash
# everything
sudo docker compose --env-file src/WebShark/.env logs -f
# cron
sudo docker compose --env-file src/WebShark/.env logs -f cron
# audit
tail -f src/WebShark/storage/logs/audit.log
```

## To run tests
```bash
sudo docker exec -it webshark-app php artisan test
```

## Scalability for queue-worker
```bash
sudo docker compose --env-file src/WebShark/.env up -d --scale queue-worker=3
```

## Reset all rate limits
```bash
sudo docker exec -it webshark-app php artisan cache:clear
```
