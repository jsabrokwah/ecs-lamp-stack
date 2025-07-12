#!/bin/bash
set -e

# RDS Setup Script for ECS LAMP Stack
# Replace containerized MySQL with RDS MySQL

# Set variables (update these with your actual values)
export VPC_ID="vpc-0c24660f703a81af6"
export PRIVATE_SUBNET_1="subnet-0ae7b9b5c1ff7c6e7"
export PRIVATE_SUBNET_2="subnet-078cb3d391c5acc25"
export ECS_SG="sg-0f7e614c724911594"

# Create DB Subnet Group
echo "Creating DB Subnet Group..."
aws rds create-db-subnet-group \
    --db-subnet-group-name ecs-lamp-db-subnet-group \
    --db-subnet-group-description "Subnet group for ECS LAMP RDS" \
    --subnet-ids $PRIVATE_SUBNET_1 $PRIVATE_SUBNET_2

# Create RDS Security Group
echo "Creating RDS Security Group..."
RDS_SG=$(aws ec2 create-security-group \
    --group-name ecs-lamp-rds-sg \
    --description "Security group for RDS MySQL" \
    --vpc-id $VPC_ID \
    --query 'GroupId' \
    --output text)

# Allow MySQL access from ECS security group
echo "Allowing MySQL access from ECS security group..."
aws ec2 authorize-security-group-ingress \
    --group-id $RDS_SG \
    --protocol tcp \
    --port 3306 \
    --source-group $ECS_SG

# Create RDS MySQL Instance
echo "Creating RDS MySQL Instance..."
aws rds create-db-instance \
    --db-instance-identifier ecs-lamp-mysql \
    --db-instance-class db.t3.micro \
    --engine mysql \
    --engine-version 8.0.39 \
    --master-username root \
    --master-user-password rootpassword \
    --allocated-storage 20 \
    --storage-type gp2 \
    --vpc-security-group-ids $RDS_SG \
    --db-subnet-group-name ecs-lamp-db-subnet-group \
    --db-name lampdb \
    --backup-retention-period 7 \
    --no-multi-az \
    --storage-encrypted \
    --no-publicly-accessible

echo "RDS instance creation initiated. This will take 5-10 minutes."
echo "RDS Security Group ID: $RDS_SG"