# ECS LAMP Stack Project - RDS Migration

## Project Overview

This project demonstrates a modern, scalable LAMP (Linux, Apache, MySQL, PHP) stack application deployed on AWS ECS (Elastic Container Service) using **AWS RDS MySQL** for the database layer. The project showcases a **strategic migration from containerized MySQL with EFS storage to managed RDS MySQL**, significantly improving disaster recovery capabilities, operational efficiency, and scalability.

## Live Primary Application

The TaskFlow application is accessible at: [http://ecs-lamp-alb-1493132989.eu-west-1.elb.amazonaws.com](http://ecs-lamp-alb-1493132989.eu-west-1.elb.amazonaws.com/)

## Live Disaster Recovery URL

The Disaster Recovery Test URL is accessible at: [http://ecs-lamp-dr-alb-1462511445.eu-central-1.elb.amazonaws.com/](http://ecs-lamp-dr-alb-1462511445.eu-central-1.elb.amazonaws.com/)

## Architecture Evolution: Why We Migrated to RDS

### Original Architecture (Containerized MySQL + EFS)
- **MySQL Container**: Running MySQL 8.0 in ECS Fargate
- **EFS Storage**: Persistent storage for MySQL data
- **Challenges**:
  - Complex disaster recovery setup
  - Manual database administration
  - EFS performance limitations
  - Higher operational overhead
  - Limited cross-region replication options

### Current Architecture (RDS MySQL)
- **AWS RDS MySQL**: Fully managed database service
- **Benefits Achieved**:
  - **Disaster Recovery Ready**: Cross-region read replicas
  - **Managed Service**: Automated backups, patching, monitoring
  - **High Availability**: Multi-AZ deployment support
  - **Scalability**: Easy compute and storage scaling
  - **Cost Optimization**: No EFS costs, better resource utilization
  - **Operational Excellence**: Reduced database administration

## Current Technology Stack

- **Compute**: AWS ECS Fargate (Serverless containers)
- **Database**: AWS RDS MySQL 8.0 (Managed service)
- **Load Balancing**: Application Load Balancer (ALB)
- **Container Registry**: Amazon ECR
- **Monitoring**: CloudWatch with RDS-specific metrics
- **Networking**: VPC with public/private subnets
- **Security**: Security groups with least privilege access

## Application Features

### TaskFlow - Modern Todo Application
- **Modern UI**: Responsive design with CSS animations and gradients
- **Full CRUD Operations**: Create, Read, Update, Delete tasks
- **Status Management**: Pending, In Progress, Completed states
- **Real-time Statistics**: Task counters and progress tracking
- **Health Monitoring**: Dedicated health check endpoint for ALB
- **Database Integration**: PDO-based MySQL connectivity with RDS

## Project Structure

```
ecs_lamp-stack/
├── README.md                    # This documentation
├── implementation-guideline.md  # Complete deployment guide
├── sprint2.md                  # Disaster recovery requirements
├── disaster-recovery/          # DR implementation (NEW)
│   ├── disaster-recovery-guide.md  # Complete DR procedures
│   └── terraform/              # Infrastructure as Code
│       ├── README.md          # Terraform deployment guide
│       ├── main.tf            # Main infrastructure configuration
│       ├── modules/           # Reusable Terraform modules
│       │   ├── vpc/           # VPC and networking
│       │   ├── security/      # Security groups
│       │   ├── rds/           # RDS with cross-region replica
│       │   ├── ecr/           # Container registry
│       │   └── ecs/           # ECS cluster and services
│       └── terraform.tfvars   # Configuration variables
├── lamp-app/
│   ├── README.md              # Application structure documentation
│   ├── docker-compose.yml     # Local development setup
│   ├── web/                   # Web container configuration
│   │   ├── Dockerfile         # Apache + PHP container
│   │   ├── apache2.conf       # Apache configuration
│   │   ├── php.ini           # PHP configuration
│   │   └── src/              # PHP application source
│   │       ├── index.php     # Main TaskFlow application
│   │       ├── config.php    # Database configuration
│   │       ├── database.php  # Database connection handler
│   │       └── health.php    # Health check endpoint
│   └── mysql/                # Legacy MySQL container files (reference only)
├── screenshots/              # Implementation screenshots
├── architectural-diagram.png # Primary architecture visualization
└── DR-architecture.png      # Disaster recovery architecture (NEW)
```

## Migration Impact and Benefits

### Performance Improvements
- **Database Performance**: RDS optimized storage and compute
- **Network Latency**: Direct RDS connection vs container networking
- **Resource Utilization**: No MySQL container resource overhead

### Operational Benefits
- **Automated Backups**: Point-in-time recovery capability
- **Automated Patching**: Security updates without downtime
- **Monitoring**: Built-in CloudWatch metrics and alarms
- **Scaling**: Independent database scaling from application

### Cost Optimization
- **No EFS Costs**: Eliminated file system storage costs
- **Better Resource Allocation**: Optimized compute resources
- **Managed Service**: Reduced operational overhead

### Disaster Recovery Readiness
- **Cross-Region Replicas**: Easy setup for DR scenarios
- **Automated Failover**: RDS Multi-AZ capabilities
- **Backup Strategy**: Automated, consistent backups
- **Recovery Time**: Faster recovery with managed service

## Security Architecture

### Network Security
- **Private Subnets**: RDS deployed in private subnets only
- **Security Groups**: Restrictive access (MySQL port 3306 from ECS only)
- **VPC Isolation**: Complete network isolation from internet

### Access Control
- **IAM Roles**: Least privilege access for ECS tasks
- **Database Authentication**: Secure credential management
- **Encryption**: RDS storage encryption enabled

## Monitoring and Observability

### CloudWatch Metrics
- **ECS Metrics**: CPU, memory utilization of web containers
- **RDS Metrics**: Database performance, connections, latency
- **ALB Metrics**: Request count, response times, error rates
- **Custom Alarms**: Proactive monitoring and alerting

### Logging
- **Application Logs**: ECS container logs in CloudWatch
- **Database Logs**: RDS slow query and error logs
- **Load Balancer Logs**: Access and error logging

## Disaster Recovery Implementation (Phase 2 Complete)

**Status**: ✅ **IMPLEMENTED** - Full disaster recovery solution deployed

**Primary Region**: eu-west-1 | **DR Region**: eu-central-1

### Implemented DR Components

1. **Cross-Region RDS Read Replica**: 
   - Automated data replication from eu-west-1 to eu-central-1
   - Continuous synchronization with < 5 minutes RPO
   - Automated promotion capability for failover

2. **Pilot Light ECS Infrastructure**:
   - Complete ECS cluster deployed in DR region
   - Services scaled to 0 for cost optimization
   - Instant scaling capability during disaster

3. **ALB-Based Failover Architecture**:
   - Direct ALB endpoint access (no domain required)
   - Primary: `http://ecs-lamp-alb-1493132989.eu-west-1.elb.amazonaws.com`
   - DR: Available on-demand via ALB endpoint in eu-central-1

4. **Automated Monitoring & Alerting**:
   - CloudWatch alarms for disaster detection
   - SNS notifications for DR events
   - Lambda-based automated failover triggers

5. **Infrastructure as Code**:
   - Complete Terraform modules for reproducible deployments
   - Automated resource provisioning in both regions
   - Version-controlled DR procedures

### DR Capabilities Achieved

- **RTO (Recovery Time Objective)**: 15 minutes
- **RPO (Recovery Point Objective)**: 5 minutes
- **Automated Failover**: Lambda-triggered disaster response
- **Cost Optimization**: Pilot light architecture with minimal standby costs
- **Endpoint Management**: Simple ALB-based switching without DNS complexity

## Getting Started

### Primary Infrastructure Deployment
For complete deployment instructions, see [implementation-guideline.md](implementation-guideline.md)

### Disaster Recovery Setup
For DR implementation, see [disaster-recovery/disaster-recovery-guide.md](disaster-recovery/disaster-recovery-guide.md)

### Terraform Deployment (Recommended)
For Infrastructure as Code deployment:
```bash
cd disaster-recovery/terraform/
terraform init
terraform plan
terraform apply
```

### Quick Start
1. **Prerequisites**: AWS CLI, Terraform, Docker, appropriate IAM permissions
2. **Primary Infrastructure**: Deploy VPC, RDS, ECS cluster in eu-west-1
3. **DR Infrastructure**: Deploy cross-region replica and pilot light in eu-central-1
4. **Application**: Build and deploy web container to both regions
5. **Verification**: Test application functionality and DR procedures
6. **Monitoring**: Configure CloudWatch alarms and SNS notifications

## Migration Lessons Learned

### Technical Insights
- **Service Discovery Simplification**: Direct RDS endpoint vs container discovery
- **Configuration Management**: Environment variables for database connectivity
- **Health Checks**: Application-level health monitoring
- **Database Initialization**: ECS task-based database setup

### Best Practices Applied
- **Infrastructure as Code**: All resources defined programmatically
- **Security First**: Private subnet deployment with proper access controls
- **Monitoring**: Comprehensive observability from day one
- **Documentation**: Detailed implementation and migration guides

## Implemented Enhancements

- ✅ **Cross-Region DR**: Complete disaster recovery with RDS read replica
- ✅ **Infrastructure as Code**: Terraform modules for reproducible deployments
- ✅ **Automated Monitoring**: CloudWatch alarms and Lambda-based failover
- ✅ **Pilot Light Architecture**: Cost-optimized standby infrastructure
- ✅ **ALB-Based Failover**: Direct endpoint switching without DNS complexity

## Future Enhancements

- **Multi-AZ RDS**: High availability configuration within regions
- **Auto Scaling**: Dynamic ECS service scaling based on metrics
- **CI/CD Pipeline**: Automated deployment pipeline with DR integration
- **SSL/TLS**: HTTPS termination at ALB with certificate management
- **Enhanced Monitoring**: Custom dashboards and advanced alerting

## Conclusion

The migration from containerized MySQL+EFS to RDS MySQL represents a significant architectural improvement, delivering enhanced reliability, simplified operations, and disaster recovery readiness. This project demonstrates modern cloud-native principles while maintaining application functionality and improving overall system resilience.
