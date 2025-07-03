FROM mysql:8.0

# Copy initialization script
COPY init.sql /docker-entrypoint-initdb.d/

# Copy MySQL configuration
COPY mysql.conf /etc/mysql/conf.d/

# Set permissions
RUN chmod 644 /docker-entrypoint-initdb.d/init.sql

EXPOSE 3306
