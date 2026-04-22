.PHONY: up down

up:
	sudo docker compose --env-file src/WebShark/.env up -d --build

down:
	sudo docker compose --env-file src/WebShark/.env down