# ECS LAMP Stack Terraform Deployment

This Terraform configuration deploys a modern LAMP stack on AWS using ECS Fargate and RDS MySQL, based on the original implementation guide.

## Architecture

- **VPC**: Custom VPC with public and private subnets across 2 AZs
- **Security**: Security groups with least privilege access
- **Database**: RDS MySQL 8.0 in private subnets
- **Compute**: ECS Fargate containers for web application
- **Load Balancing**: Application Load Balancer with health checks
- **Container Registry**: ECR for container images
- **Monitoring**: CloudWatch logs and metrics

## Prerequisites

1. AWS CLI configured with appropriate permissions
2. Terraform >= 1.0 installed
3. Docker installed (for building and pushing images)

## Quick Start

1. **Clone and navigate to terraform directory**:
   ```bash
   cd terraform/
   ```

2. **Configure variables**:
   ```bash
   cp terraform.tfvars.example terraform.tfvars
   # Edit terraform.tfvars with your values
   ```

3. **Initialize Terraform**:
   ```bash
   terraform init
   ```

4. **Plan deployment**:
   ```bash
   terraform plan
   ```

5. **Deploy infrastructure**:
   ```bash
   terraform apply
   ```

6. **Build and push Docker image**:
   ```bash
   # Get ECR repository URL from terraform output
   ECR_URL=$(terraform output -raw ecr_repository_url)
   
   # Login to ECR
   aws ecr get-login-password --region $(terraform output -raw aws_region) | docker login --username AWS --password-stdin $ECR_URL
   
   # Build and push image (from lamp-app directory)
   cd ../lamp-app/web
   docker build -t ecs-lamp-web .
   docker tag ecs-lamp-web:latest $ECR_URL:latest
   docker push $ECR_URL:latest
   ```

7. **Database initialization**:
   ```bash
   # Database is automatically initialized by Terraform using a one-time ECS task
   # Check CloudWatch logs at /ecs/ecs-lamp-db-init for initialization status
   ```

8. **Access application**:
   ```bash
   echo "Application URL: $(terraform output -raw application_url)"
   ```

## Module Structure

```
terraform/
├── main.tf                 # Main configuration
├── variables.tf            # Input variables
├── outputs.tf             # Output values
├── terraform.tfvars.example # Example variables
└── modules/
    ├── vpc/               # VPC and networking
    ├── security/          # Security groups
    ├── rds/              # RDS MySQL database
    ├── ecr/              # Container registry
    └── ecs/              # ECS cluster and services
```

## Customization

### Variables

Key variables you can customize in `terraform.tfvars`:

- `aws_region`: AWS region for deployment
- `project_name`: Prefix for all resources
- `vpc_cidr`: VPC CIDR block
- `db_instance_class`: RDS instance size
- `db_password`: Database password (use a secure value)

### Scaling

To scale the ECS service, modify the `desired_count` in `modules/ecs/main.tf`.

### Multi-AZ RDS

For production, enable Multi-AZ by setting `multi_az = true` in `modules/rds/main.tf`.

## Outputs

After deployment, Terraform provides:

- `application_url`: URL to access the TaskFlow application
- `alb_dns_name`: Load balancer DNS name
- `ecr_repository_url`: ECR repository URL for pushing images
- `rds_endpoint`: Database endpoint (sensitive)

## Cleanup

To destroy all resources:

```bash
terraform destroy
```

## Security Notes

1. Database password is marked as sensitive
2. RDS is deployed in private subnets only
3. Security groups follow least privilege principle
4. ECR images are scanned on push

## Monitoring

The deployment includes:

- CloudWatch log groups for ECS containers
- Container Insights enabled on ECS cluster
- ALB access logs (can be enabled by modifying the ALB configuration)

## Troubleshooting

1. **ECS tasks not starting**: Check CloudWatch logs in `/ecs/ecs-lamp-web`
2. **Database connection issues**: Verify security groups and RDS endpoint
3. **Image pull errors**: Ensure ECR repository has the latest image pushed

## Migration from Manual Deployment

This Terraform configuration replicates the manual deployment from the implementation guide:

- Same VPC and subnet structure
- Identical security group rules
- Same RDS configuration
- Equivalent ECS task definition
- Same ALB and target group setup

The main benefits of using Terraform:

- **Infrastructure as Code**: Version controlled, repeatable deployments
- **State Management**: Terraform tracks resource state
- **Modular Design**: Reusable modules for different environments
- **Dependency Management**: Automatic resource dependency resolution