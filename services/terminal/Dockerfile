FROM node:18-alpine

# Install build dependencies for node-pty (C++ bindings) and Docker CLI
RUN apk add --no-cache python3 make g++ docker-cli

WORKDIR /app

COPY package.json .

RUN npm install

COPY server.js .

EXPOSE 3002

CMD ["npm", "start"]
