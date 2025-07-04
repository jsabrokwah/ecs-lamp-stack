# ECS LAMP Stack Project

## Project Overview

This project sets up a scalable LAMP (Linux, Apache, MySQL, PHP) stack application deployed on AWS ECS (Elastic Container Service) using Docker containers. The architecture leverages containerization to separate concerns between the web server and the database, enabling easier deployment, scaling, and management.

The application consists of a MySQL database service and a PHP-based web server running on Apache. The project includes Dockerfiles, configuration files, and orchestration scripts to build and deploy the containers.

## Live URL

The application can be accessed online at: [http://ecs-lamp-alb-1493132989.eu-west-1.elb.amazonaws.com](http://ecs-lamp-alb-1493132989.eu-west-1.elb.amazonaws.com/)  

## Features

- Containerized MySQL database with custom initialization scripts.
- Apache web server running PHP application.
- Health check endpoint for monitoring.
- Docker Compose setup for local development.
- AWS ECS deployment-ready configuration.
- Modular directory structure separating database and web components.

## Development Stack

- Linux
- Apache HTTP Server
- MySQL
- PHP
- Docker & Docker Compose
- AWS ECS (Elastic Container Service)

## Directory Structure

- `.gitignore`: Git ignore file specifying untracked files.
- `architectural-diagram.png`: Diagram illustrating the architecture of the project.
- `implementation-guideline.md`: Guidelines for implementing the project.
- `README.md`: This file, documenting the root directory.
- `lamp-app/`: Directory containing the LAMP application source code and Docker configurations.
- `screenshots/`: Directory containing screenshots documenting various steps of the project.

