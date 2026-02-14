FROM node:20-slim

# Install Docker CLI from official Docker repo and build dependencies for node-pty
RUN apt-get update && apt-get install -y \
    ca-certificates \
    curl \
    gnupg \
    python3 \
    make \
    g++ \
    && install -m 0755 -d /etc/apt/keyrings \
    && curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg \
    && chmod a+r /etc/apt/keyrings/docker.gpg \
    && echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/debian bookworm stable" > /etc/apt/sources.list.d/docker.list \
    && apt-get update \
    && apt-get install -y docker-ce-cli \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Copy package files
COPY package.json .

# Install dependencies
RUN npm install

# Copy server
COPY server.js .

EXPOSE 3002

CMD ["node", "server.js"]
