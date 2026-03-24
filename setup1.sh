#!/bin/bash

echo "Starting setup..."

# Check if we are already in the repo
if [ ! -d ".git" ]; then
    echo "Cloning repository..."
    git clone https://git.mif.vu.lt/luse0397/webshark.git
    cd webshark
else
    echo "Already in repository directory."
fi

sudo apt-get update -y && sudo apt-get upgrade -y && sudo apt-get install git -y

# Add Docker's official GPG key:
sudo apt update -y
sudo apt install ca-certificates curl -y
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc

# Add the repository to Apt sources:
sudo tee /etc/apt/sources.list.d/docker.sources <<EOF
Types: deb
URIs: https://download.docker.com/linux/debian
Suites: $(. /etc/os-release && echo "$VERSION_CODENAME")
Components: stable
Signed-By: /etc/apt/keyrings/docker.asc
EOF

sudo apt update -y

sudo apt install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin -y

echo
echo -e "\e[1mInitial setup complete. If needed, now you can manually run 'git switch <branch_name>' to switch to the desired branch and then run './setup2.sh' to continue with the setup process.\e[0m"