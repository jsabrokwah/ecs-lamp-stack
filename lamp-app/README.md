# LAMP Application - RDS MySQL Architecture

This directory contains the source code and Docker configurations for the LAMP application after migration to AWS RDS MySQL.

## Architecture Evolution

**Previous Architecture**: Containerized MySQL + EFS storage  
**Current Architecture**: Containerized web server + AWS RDS MySQL

### Migration Benefits Realized
- **Simplified Operations**: No MySQL container management required
- **Enhanced Reliability**: RDS managed service with automated backups
- **Disaster Recovery**: Cross-region read replica capabilities
- **Performance**: Optimized database performance with RDS
- **Cost Efficiency**: Eliminated EFS storage costs

The web server component remains containerized for flexibility, while the database leverages AWS RDS MySQL for enterprise-grade reliability and disaster recovery capabilities.

## Directory Structure

### Active Components (RDS Architecture)
- **`web/`**: Web server container configuration
  - `Dockerfile`: Apache + PHP 8.1 container build
  - `apache2.conf`: Apache server configuration
  - `php.ini`: PHP runtime settings
  - `src/`: PHP application source code
    - `index.php`: TaskFlow main application
    - `config.php`: RDS database connection configuration
    - `database.php`: Database abstraction layer
    - `health.php`: Health check endpoint for ALB
- **`docker-compose.yml`**: Local development environment setup

### Legacy Components (Reference Only)
- **`mysql/`**: Former MySQL container files (replaced by RDS)
  - Kept for reference and potential local development
  - Production now uses AWS RDS MySQL managed service

## Application Configuration

### Database Connection (RDS Integration)
The application connects to AWS RDS MySQL using environment variables:
- `DB_HOST`: RDS endpoint (e.g., `ecs-lamp-mysql.region.rds.amazonaws.com`)
- `DB_NAME`: Database name (`lampdb`)
- `DB_USER`: Database username
- `DB_PASSWORD`: Database password

### Local Development
For local development, you can:
1. Use the docker-compose.yml with local MySQL container
2. Connect to a development RDS instance
3. Set environment variables to point to your preferred database
4. Test DR scenarios using the cross-region RDS read replica

### DR Testing
To test disaster recovery scenarios locally:
```bash
# Test primary region connectivity
docker run --rm -e DB_HOST=primary-rds-endpoint ecs-lamp-web

# Test DR region connectivity
docker run --rm -e DB_HOST=dr-rds-endpoint ecs-lamp-web
```

## Migration Impact

### What Changed
- **Removed**: MySQL ECS service and task definition
- **Removed**: EFS file system for MySQL data persistence
- **Removed**: Service discovery for MySQL container
- **Added**: RDS MySQL instance configuration
- **Updated**: Web application database connection to use RDS endpoint
- **Enhanced**: Monitoring with RDS-specific CloudWatch metrics
- **Added**: Cross-region disaster recovery capabilities
- **Added**: Automated failover and monitoring systems

### What Stayed the Same
- Web container architecture and configuration
- PHP application code and functionality
- Apache server configuration
- Health check endpoints
- Load balancer integration

## Disaster Recovery Integration

### Cross-Region Deployment
The web application is now deployed in both regions for disaster recovery:
- **Primary Region**: eu-west-1 (active)
- **DR Region**: eu-central-1 (pilot light)

### Database Connectivity
The application automatically connects to the appropriate RDS endpoint:
- **Primary**: RDS MySQL primary instance in eu-west-1
- **DR**: RDS MySQL read replica (promoted to primary during failover) in eu-central-1

### Environment Configuration
Environment variables are dynamically configured based on deployment region:
```bash
# Primary region configuration
DB_HOST=ecs-lamp-mysql-primary.region.rds.amazonaws.com

# DR region configuration (after failover)
DB_HOST=ecs-lamp-mysql-replica.region.rds.amazonaws.com
```

### Health Check Enhancement
The health check endpoint (`health.php`) validates both:
- Application server status
- Database connectivity
- Regional deployment status

This enables ALB-based failover monitoring and automated disaster detection.
