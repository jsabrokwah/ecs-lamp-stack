#!/bin/bash

# Import script for DR resources created via AWS CLI
# Run this script after creating resources via AWS CLI commands

set -eou pipefail

echo "Starting import of DR resources..."

# Set variables (update these with actual resource IDs from AWS CLI output)
DR_REGION="eu-central-1"
PROJECT_NAME="ecs-lamp"

# Get resource IDs (these should be captured from AWS CLI output)
echo "Please update the following variables with actual resource IDs from your AWS CLI output:"
echo "DR_VPC_ID=vpc-xxxxxxxxx"
echo "DR_PRIVATE_SUBNET_1=subnet-xxxxxxxxx" 
echo "DR_PRIVATE_SUBNET_2=subnet-xxxxxxxxx"
echo "DR_RDS_SG=sg-xxxxxxxxx"

# Uncomment and update these lines with actual resource IDs:
DR_VPC_ID="vpc-0a545c74ef9f4315d"
DR_PRIVATE_SUBNET_1="subnet-03ff55c17503ea02b"
DR_PRIVATE_SUBNET_2="subnet-0997ff1c048b17364" 
DR_RDS_SG="sg-0a9552eb9382a31c1"

# Import only the resources created by AWS CLI
echo "Importing VPC..."
terraform import module.vpc.aws_vpc.main $DR_VPC_ID

echo "Importing private subnets..."
terraform import 'module.vpc.aws_subnet.private[0]' $DR_PRIVATE_SUBNET_1
terraform import 'module.vpc.aws_subnet.private[1]' $DR_PRIVATE_SUBNET_2

echo "Importing DB subnet group..."
terraform import module.rds.aws_db_subnet_group.main ecs-lamp-dr-db-subnet-group

echo "Importing RDS security group..."
terraform import module.security.aws_security_group.rds $DR_RDS_SG

echo "Importing RDS read replica..."
terraform import module.rds.aws_db_instance.mysql ecs-lamp-mysql-replica

echo "Import completed. Run 'terraform plan' to see what needs to be created."

echo "Import commands prepared. Please:"
echo "1. Update the resource IDs in this script"
echo "2. Uncomment the terraform import commands"
echo "3. Run the script again"
echo "4. Run 'terraform plan' to verify the import"