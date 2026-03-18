# WebShark 🦈

### 1. Setup (First time only)
First, copy the example environment file:
```bash
cp src/WebShark/.env.example src/WebShark/.env
# Update WWWUSER and WWWGROUP in .env (run 'id -u' and 'id -g'). Might need to change only on macOS.
```

### 2. Spin docker up
Run this from the root folder. We use the `--env-file` flag so Docker can find your config:
```bash
sudo docker compose --env-file src/WebShark/.env up -d --build
```

### 3. Install dependencies (First time only)
```bash
sudo docker exec -it webshark-app composer install
sudo docker exec -it webshark-node npm install
```

### 4. Laravel setup (First time only)
```bash
sudo docker exec -it webshark-app php artisan key:generate
sudo docker exec -it webshark-app php artisan storage:link
sudo docker exec -it webshark-app php artisan migrate
```

### 5. Only if you are hosting on VM - close your current SSH session, then open a new one
```bash
ssh -L 8000:localhost:8000 -L 5173:localhost:5173 <OpenNebula CONNECT_INFO1 (without the ssh)>
```

### 6. Test it
```bash
http://localhost:8000/
```

### 7. Stopping
```bash
sudo docker compose down
```

# To see PostgreSQL
```bash
sudo docker compose --env-file src/WebShark/.env exec db psql -U webshark -d websharkdb -c "SELECT * FROM redis_job;"
```

# To see Redis
```bash
sudo docker compose --env-file src/WebShark/.env exec valkey valkey-cli KEYS "*"
```

# Scalability for queue-worker
```bash
sudo docker compose --env-file src/WebShark/.env up -d --scale queue-worker=3
```
