# Web Directory

This directory contains files related to the web server service for the LAMP stack with disaster recovery capabilities.
The web service is containerized using Docker and deployed across multiple AWS regions for high availability. The Apache web server and PHP runtime are configured using the provided configuration files. The `src` directory contains the PHP application source code.

## Contents

- `apache2.conf`: Apache web server configuration file.
- `Dockerfile`: Dockerfile to build the web server container image (deployed to ECR in both regions).
- `php.ini`: PHP configuration file.
- `src/`: Directory containing PHP source code files with DR-aware database connectivity.

## Disaster Recovery Integration

### Multi-Region Deployment
The web container is built and deployed to ECR repositories in both:
- **Primary Region**: eu-west-1
- **DR Region**: eu-central-1

### Container Configuration
The Dockerfile and configuration files support:
- Dynamic database endpoint configuration via environment variables
- Health check endpoints for ALB-based failover monitoring
- Cross-region deployment compatibility

### Build and Deployment
```bash
# Build for primary region
docker build -t ecs-lamp-web .
docker tag ecs-lamp-web:latest ${ACCOUNT_ID}.dkr.ecr.eu-west-1.amazonaws.com/ecs-lamp-web:latest
docker push ${ACCOUNT_ID}.dkr.ecr.eu-west-1.amazonaws.com/ecs-lamp-web:latest

# Build for DR region
docker tag ecs-lamp-web:latest ${ACCOUNT_ID}.dkr.ecr.eu-central-1.amazonaws.com/ecs-lamp-web:latest
docker push ${ACCOUNT_ID}.dkr.ecr.eu-central-1.amazonaws.com/ecs-lamp-web:latest
```

