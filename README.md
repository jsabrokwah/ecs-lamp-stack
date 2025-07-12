# ECS LAMP Stack Project - RDS Migration

## Project Overview

This project demonstrates a modern, scalable LAMP (Linux, Apache, MySQL, PHP) stack application deployed on AWS ECS (Elastic Container Service) using **AWS RDS MySQL** for the database layer. The project showcases a **strategic migration from containerized MySQL with EFS storage to managed RDS MySQL**, significantly improving disaster recovery capabilities, operational efficiency, and scalability.

## Live Application

The TaskFlow application is accessible at: [http://ecs-lamp-alb-1493132989.eu-west-1.elb.amazonaws.com](http://ecs-lamp-alb-1493132989.eu-west-1.elb.amazonaws.com/)

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
├── phase2.md                   # Disaster recovery strategy
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
└── architectural-diagram.png # Architecture visualization
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

## Disaster Recovery Strategy (Phase 2 Ready)

The migration to RDS enables comprehensive disaster recovery:

1. **Cross-Region Read Replica**: Automated data replication
2. **ECS Service Replication**: Portable task definitions
3. **Infrastructure as Code**: Reproducible deployments
4. **Automated Failover**: DNS-based traffic routing
5. **Recovery Testing**: Regular DR drills and validation

## Getting Started

For complete deployment instructions, see [implementation-guideline.md](implementation-guideline.md)

### Quick Start
1. **Prerequisites**: AWS CLI, Docker, appropriate IAM permissions
2. **Infrastructure**: Deploy VPC, RDS, ECS cluster
3. **Application**: Build and deploy web container
4. **Verification**: Test application functionality

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

## Future Enhancements

- **Multi-AZ RDS**: High availability configuration
- **Read Replicas**: Performance optimization for read-heavy workloads
- **Auto Scaling**: Dynamic ECS service scaling
- **CI/CD Pipeline**: Automated deployment pipeline
- **SSL/TLS**: HTTPS termination at ALB

## Conclusion

The migration from containerized MySQL+EFS to RDS MySQL represents a significant architectural improvement, delivering enhanced reliability, simplified operations, and disaster recovery readiness. This project demonstrates modern cloud-native principles while maintaining application functionality and improving overall system resilience.
