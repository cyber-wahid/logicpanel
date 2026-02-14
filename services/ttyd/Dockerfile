FROM tsl0922/ttyd:alpine

# Install Docker CLI so we can exec into other containers
RUN apk add --no-cache docker-cli

# Set session timeout (heartbeat) to avoid disconnecting? 
# ttyd keeps alive as long as the process is alive. 
EXPOSE 7681

# Command is provided at runtime
ENTRYPOINT ["/sbin/tini", "--", "ttyd"]
