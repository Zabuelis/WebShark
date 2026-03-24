cp src/WebShark/.env.example src/WebShark/.env

sudo docker compose --env-file src/WebShark/.env up -d --build

sudo docker exec -it webshark-app composer install
sudo docker compose --env-file src/WebShark/.env run --rm node npm install
sudo docker compose --env-file src/WebShark/.env up -d --build
sudo docker exec -it webshark-node npm install

sudo docker exec -it webshark-app php artisan key:generate
sudo docker exec -it webshark-app php artisan storage:link
sudo docker exec -it webshark-app php artisan migrate

sudo docker compose --env-file src/WebShark/.env down
sudo docker compose --env-file src/WebShark/.env up -d --build

echo "WebShark is now running. You can access it at http://localhost:8000"
echo "To stop the application, run:"
echo "sudo docker compose --env-file src/WebShark/.env down"
echo "For more commands, please refer to the README.md file"
echo "If you are running this on a remote server, use the following command to create an SSH tunnel to access the application locally:"
echo "ssh -L 8000:localhost:8000 -L 5173:localhost:5173 <OpenNebula CONNECT_INFO1 (without the ssh)>"