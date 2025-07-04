# Lamp App Directory

This directory contains the source code and Docker configurations for the LAMP application.
It is structured to separate the MySQL database and the web server components into their own subdirectories. Dockerfiles and configuration files are provided to build and run the containers for each service.

## Contents

- `docker-compose.yml`: Docker Compose file to orchestrate the MySQL and web containers for local testing.
- `README.md`: This file, documenting the lamp-app directory.
- `mysql/`: Directory containing MySQL Dockerfile, configuration, and initialization scripts.
- `web/`: Directory containing the web server Dockerfile, configuration files, and PHP source code.
