FROM node:24-alpine

WORKDIR /var/www

# Copy package files into current working directory of the container
COPY package*.json ./
# Install npm packages during container startup
RUN npm install
# Change the ownership of the node_modules file to container user (packages were installed using root user)
RUN chown -R node:node /var/www/node_modules
