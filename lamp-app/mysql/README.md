# MySQL Directory

This directory contains files related to the MySQL database service for the LAMP stack.
The MySQL service is containerized using Docker. The Dockerfile builds the MySQL image, and the initialization script sets up the database schema and initial data. The configuration file customizes MySQL server behavior.

## Contents

- `Dockerfile`: Dockerfile to build the MySQL container image.
- `init.sql`: SQL script to initialize the MySQL database with required schemas and data.
- `mysql.conf`: MySQL configuration file to customize database settings.
