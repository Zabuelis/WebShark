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
sudo docker compose --env-file src/WebShark/.env run --rm node npm install
sudo docker compose --env-file src/WebShark/.env up -d --build
```


```bash
docker compose --env-file src/WebShark/.env run --rm node npm install --legacy-peer-deps

docker exec -it webshark-node npm install --legacy-peer-deps
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

### 5. (only if you are hosting on VM) Close your current SSH session, then open a new one
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
