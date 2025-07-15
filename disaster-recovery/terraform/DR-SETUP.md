# Disaster Recovery Setup Guide

## Overview
This Terraform configuration manages the DR infrastructure in eu-central-1, importing existing resources created via AWS CLI and managing the RDS read replica.

## Prerequisites
1. Primary infrastructure running in eu-west-1
2. DR resources created via AWS CLI (VPC, subnets, security groups, read replica)
3. Terraform >= 1.0
4. AWS CLI configured with appropriate permissions

## Setup Steps

### 1. Update Configuration
Update `terraform.tfvars` with your actual AWS account ID:
```hcl
source_db_identifier = "arn:aws:rds:eu-west-1:YOUR_ACCOUNT_ID:db:ecs-lamp-mysql-primary"
```

### 2. Create DR Resources via AWS CLI
Run the AWS CLI commands from the implementation guide to create:
- DR VPC (10.1.0.0/16)
- Private subnets in eu-central-1a and eu-central-1b
- DB subnet group
- RDS security group
- Cross-region read replica

### 3. Import Existing Resources
1. Update `import-dr-resources.sh` with actual resource IDs from AWS CLI output
2. Run the import script:
```bash
./import-dr-resources.sh
```

### 4. Initialize and Plan
```bash
terraform init
terraform plan
```

### 5. Apply Configuration
```bash
terraform apply
```

## Resource Mapping

| Terraform Resource | AWS Resource Name | Purpose |
|-------------------|-------------------|---------|
| `module.vpc.aws_vpc.main` | `ecs-lamp-dr-vpc` | DR VPC |
| `module.vpc.aws_subnet.private[0]` | DR private subnet 1 | Database subnet |
| `module.vpc.aws_subnet.private[1]` | DR private subnet 2 | Database subnet |
| `module.rds.aws_db_subnet_group.main` | `ecs-lamp-dr-db-subnet-group` | DB subnet group |
| `module.security.aws_security_group.rds` | `ecs-lamp-dr-rds-sg` | RDS security group |
| `module.rds.aws_db_instance.mysql` | `ecs-lamp-mysql-replica` | Read replica |

## Key Configuration Changes

### VPC CIDR
- Primary region (eu-west-1): 10.0.0.0/16
- DR region (eu-central-1): 10.1.0.0/16

### RDS Configuration
- Creates read replica from primary DB in eu-west-1
- Uses existing DR subnet group and security group
- Maintains same instance class (db.t3.micro)

### Naming Convention
All DR resources use `-dr-` prefix to distinguish from primary resources.

## Disaster Recovery Process

### Failover Steps
1. **Promote Read Replica**: Convert read replica to standalone DB
2. **Update DNS**: Point application to DR region
3. **Scale ECS**: Deploy application containers in DR region
4. **Verify**: Test application functionality

### Promote Read Replica
```bash
aws rds promote-read-replica \
    --db-instance-identifier ecs-lamp-mysql-replica \
    --region eu-central-1
```

## Monitoring
- CloudWatch alarms for replication lag
- RDS performance insights
- Application health checks

## Cleanup
To destroy DR resources:
```bash
terraform destroy
```

**Note**: This will only destroy Terraform-managed resources. Manually created resources may need separate cleanup.