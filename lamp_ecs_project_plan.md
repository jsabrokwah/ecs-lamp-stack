# LAMP Application ECS Deployment Project Plan

## Phase 1: Environment Setup & Prerequisites

### 1.1 AWS Account Configuration
- Verify AWS account access and billing setup
- Configure AWS CLI with appropriate credentials
- Install ECS CLI and configure cluster profile
- Set up AWS regions

### 1.2 IAM Role & Policy Setup
- Create ECS Task Execution Role
- Create ECS Service Role
- Attach policies: `AmazonECSTaskExecutionRolePolicy`, `AmazonECSServiceRolePolicy`
- Create custom policies for ECR, CloudWatch, and S3 access

### 1.3 Development Environment
- Install Docker Desktop locally
- Set up local development environment
- Install required tools: git, text editor, AWS CLI v2

## Phase 2: Application Development & Containerization

### 2.1 LAMP Application Structure
```
lamp-app/
├── web/
│   ├── Dockerfile
│   ├── apache2.conf
│   ├── php.ini
│   └── src/
│       ├── index.php
│       ├── config.php
│       └── database.php
├── mysql/
│   ├── Dockerfile
│   ├── init.sql
│   └── mysql.conf
└── docker-compose.yml (for local testing)
```

### 2.2 Container Development Tasks
- Create PHP application with database connectivity
- Build single web container with Apache + PHP
- Configure MySQL container with initialization scripts
- Create optimized Dockerfiles for both containers
- Test containers locally using Docker Compose

### 2.3 Application Features to Implement
- Simple web interface (PHP frontend)
- Database connection testing page
- CRUD operations (Create, Read, Update, Delete)
- Health check endpoints
- Error handling and logging

## Phase 3: AWS Infrastructure Setup

### 3.1 Amazon ECR (Elastic Container Registry)
- Create ECR repositories for each container:
  - `lamp-web` (Apache + PHP)
  - `lamp-mysql`
- Configure repository policies
- Set up image lifecycle policies

### 3.2 VPC and Networking
- Create VPC with public and private subnets
- Set up Internet Gateway and NAT Gateway
- Configure Route Tables
- Configure Security Groups:
  - ALB Security Group (HTTP/HTTPS from internet)
  - ECS Security Group (container ports: 80, 3306, 443)
  - EFS Security Group (NFS port 2049 from ECS)

### 3.3 Persistent Storage for MySQL Container
- Create EFS file system for MySQL data persistence
- Configure EFS mount targets in private subnets
- Set up EFS security groups (NFS port 2049)
- Create EFS access points for MySQL data directory

### 3.4 Load Balancer Configuration
- Create Application Load Balancer (ALB)
- Set up target groups for ECS services
- Configure health checks

## Phase 4: ECS Cluster & Service Deployment

### 4.1 ECS Cluster Setup
- Create ECS Cluster using Fargate launch type
- Configure cluster settings and networking
- Set up CloudWatch logging

### 4.2 Task Definitions
- Create task definition for web application:
  - Apache + PHP container
  - Resource allocation (CPU, memory)
  - Environment variables for database connection
  - Logging configuration
  - Volume mounts for shared files
- Create task definition for MySQL database:
  - MySQL container configuration
  - EFS volume mount for data persistence
  - Environment variables for database setup
  - Resource allocation and health checks

### 4.3 ECS Services
- Deploy MySQL database service first
- Deploy web application service
- Configure service-to-service networking
- Set up service discovery for database connection
- Configure service auto-scaling policies

### 4.4 Container Image Management
- Build and tag Docker images locally
- Push images to ECR using AWS CLI
- Automate image building with scripts

## Phase 5: Testing & Optimization

### 5.1 Application Testing
- Test application functionality through ALB
- Verify database connectivity
- Test CRUD operations
- Perform load testing (basic)

### 5.2 Monitoring & Logging
- Set up CloudWatch dashboards
- Configure log groups for containers
- Set up basic alarms for service health

### 5.3 Security Hardening
- Review security group rules
- Implement least privilege access
- Enable encryption at rest and in transit

## Phase 6: Documentation & Submission Preparation

### 6.1 Architectural Diagram Creation
- Design comprehensive architecture diagram showing:
  - VPC structure with subnets
  - ECS cluster and services
  - Load balancer and target groups
  - Database architecture
  - Security groups and networking
  - Data flow between components

### 6.2 Screenshot Documentation
- ECS cluster creation commands
- Task definition creation
- Service deployment commands
- Container status verification
- Application Load Balancer configuration
- CloudWatch logs and monitoring

### 6.3 Comprehensive Documentation
- **README.md** with project overview
- **DEPLOYMENT.md** with step-by-step deployment guide
- **ARCHITECTURE.md** explaining design decisions
- **TROUBLESHOOTING.md** with common issues and solutions
- Configuration files and scripts
- Environment variable documentation

### 6.4 Live Application Preparation
- Ensure application is publicly accessible
- Verify all features work correctly
- Test from multiple browsers/devices
- Prepare demo data if needed

## Submission Deliverables Checklist

### Required Submissions:
1. **Architectural Diagram**
   - High-level AWS architecture
   - Network topology
   - Container orchestration flow
   - Security boundaries

2. **CLI Screenshots**
   - ECS cluster creation
   - Task definition registration
   - Service creation and updates
   - Container status and logs
   - ECR image push operations

3. **Live Application Link**
   - Publicly accessible URL
   - Functional LAMP application
   - Database connectivity demonstrated

4. **Documentation & Project Files**
   - Complete source code
   - Docker files and configurations
   - AWS CLI scripts
   - Step-by-step deployment guide
   - Architecture explanation

## Key ECS Commands to Document

```bash
# Cluster Management
aws ecs create-cluster --cluster-name lamp-cluster
aws ecs list-clusters
aws ecs describe-clusters --clusters lamp-cluster

# Task Definitions
aws ecs register-task-definition --cli-input-json file://task-definition.json
aws ecs list-task-definitions
aws ecs describe-task-definition --task-definition lamp-web-app

# Service Management
aws ecs create-service --cluster lamp-cluster --service-name lamp-web-service
aws ecs list-services --cluster lamp-cluster
aws ecs describe-services --cluster lamp-cluster --services lamp-web-service

# ECR Operations
aws ecr get-login-password --region us-east-1 | docker login --username AWS
docker build -t lamp-web .
docker tag lamp-web:latest [account-id].dkr.ecr.us-east-1.amazonaws.com/lamp-web:latest
docker push [account-id].dkr.ecr.us-east-1.amazonaws.com/lamp-web:latest
```

## Success Criteria

- Application accessible via public URL
- All LAMP components working correctly
- ECS services running without errors
- Proper documentation with screenshots
- Architecture diagram accurately represents deployment
- Code repository organized and complete

## Risk Mitigation

- **Container Issues**: Test locally with Docker Compose first
- **Networking Problems**: Use VPC flow logs for debugging
- **Permission Errors**: Document IAM policies clearly
- **Cost Management**: Set up billing alerts and use t3.micro instances
- **Timeline Delays**: Focus on MVP first, then add enhancements
