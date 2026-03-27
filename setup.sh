#!/bin/bash

set -e

# Handle Branch Argument
BRANCH_NAME=$1
REPO_DIR="webshark"

echo "Starting setup..."

# Prerequisites & Updates
sudo apt-get update -y && sudo apt-get upgrade -y
sudo apt-get install git ca-certificates curl -y

# Repository Handling (Check if already exists)
if [ -d "$REPO_DIR" ]; then
    echo "Directory '$REPO_DIR' already exists. Entering directory..."
    cd "$REPO_DIR"
elif [ -d ".git" ]; then
    echo "Already inside a git repository."
else
    echo "Cloning repository..."
    git clone https://git.mif.vu.lt/luse0397/webshark.git
    cd "$REPO_DIR"
fi

# Immediate Branch Validation
if [ -n "$BRANCH_NAME" ]; then
    echo "Checking for branch: $BRANCH_NAME..."
    # Update remote tracking info
    git fetch origin
    
    # Check if branch exists (local or remote)
    if git rev-parse --verify "origin/$BRANCH_NAME" >/dev/null 2>&1 || git rev-parse --verify "$BRANCH_NAME" >/dev/null 2>&1; then
        echo "Switching to branch '$BRANCH_NAME'..."
        git checkout "$BRANCH_NAME"
    else
        echo -e "\e[31mError: Branch '$BRANCH_NAME' does not exist.\e[0m"
        exit 1
    fi
else
    echo "No branch specified, staying on default branch."
fi

# Install Docker
echo "Installing Docker..."
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc

sudo tee /etc/apt/sources.list.d/docker.sources <<EOF
Types: deb
URIs: https://download.docker.com/linux/debian
Suites: $(. /etc/os-release && echo "$VERSION_CODENAME")
Components: stable
Signed-By: /etc/apt/keyrings/docker.asc
EOF

sudo apt update -y
sudo apt install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin -y

# Application Configuration and Docker Launch
echo "Configuring application..."
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

echo
echo -e "\e[1mWebShark is now running. You can access it at http://localhost:8000\e[0m"
echo -e "\e[1mYou must be inside the project directory to run these commands:\e[0m"
echo -e "\e[1mTo stop the application, run:\e[0m"
echo "make down"
echo -e "\e[1mTo run the application again, run:\e[0m"
echo "make up"
echo -e "\e[1mFor more commands, please refer to the README.md file\e[0m"
echo -e "\e[1mIf you are running this on a remote VM, close the connection and use the following command to create an SSH tunnel to access the application locally:\e[0m"
echo "ssh -L 8000:localhost:8000 -L 5173:localhost:5173 <OpenNebula CONNECT_INFO1 (without the ssh)>"
