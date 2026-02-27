# WebShark 🦈

### 1. Setup
First, make sure you have your `.env` file ready in the Laravel folder and make sure to set it up correctly:
```bash
cp src/WebShark/.env.example src/WebShark/.env
```

### 2. Spin docker up
Run this from the root folder. We use the `--env-file` flag so Docker can find your config:
```bash
docker compose --env-file src/WebShark/.env up -d --build
```

### 3. Install dependencies (First time only)
```bash
docker exec -it webshark-app composer install
docker exec -it webshark-node npm install
```

### 4. Laravel
```bash
docker exec -it webshark-app php artisan key:generate
docker exec -it webshark-app php artisan storage:link
docker exec -it webshark-app php artisan migrate
```

### 4. Test it
http://localhost:8000/

### 5. Stopping
```bash
docker compose stop # (keeps your data)
# or
docker compose down # (cleans up containers)
```

### To start processing the PCAP analysis jobs:
docker exec -it webshark-app php artisan queue:work