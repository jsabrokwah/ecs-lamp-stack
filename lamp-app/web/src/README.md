# Web Source Directory

This directory contains the PHP source code files for the web application with disaster recovery capabilities.
These PHP scripts implement the core functionality of the web application running on the Apache server. They handle configuration, database interactions, health checks, and serve the main web pages across multiple AWS regions.

## Contents

- `config.php`: Configuration file for the PHP application with RDS endpoint management.
- `database.php`: Database connection and query handling code with cross-region RDS support.
- `health.php`: Health check endpoint script for ALB-based disaster recovery monitoring.
- `index.php`: Main entry point of the PHP web application (TaskFlow).

## Disaster Recovery Features

### Database Connectivity (`config.php` & `database.php`)
- **Dynamic RDS Endpoint**: Connects to appropriate RDS instance based on region
- **Failover Support**: Automatically adapts to promoted read replica during DR
- **Connection Resilience**: Handles database failover scenarios gracefully

### Health Monitoring (`health.php`)
Enhanced health check endpoint that validates:
- Application server status
- Database connectivity and response time
- Regional deployment status
- Cross-region replication health (when applicable)

### Application Features (`index.php`)
- **TaskFlow Application**: Full-featured todo application
- **Cross-Region Data**: Seamless access to replicated data
- **Regional Awareness**: Displays current deployment region information
- **Failover Transparency**: Users experience no data loss during DR events

### Environment Variables
The application responds to these DR-related environment variables:
```bash
DB_HOST=<rds-endpoint>          # Primary or DR RDS endpoint
DB_NAME=lampdb                  # Database name
DB_USER=root                    # Database username
DB_PASSWORD=<secure-password>   # Database password
AWS_REGION=<region>            # Current deployment region
```

### Cross-Region Compatibility
All PHP scripts are designed to work identically in both:
- **Primary Region**: eu-west-1 (normal operations)
- **DR Region**: eu-central-1 (disaster recovery)
