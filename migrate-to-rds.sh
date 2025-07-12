#!/bin/bash

# Migration Script: Containerized MySQL to RDS MySQL
# This script handles the complete migration process

set -eou pipefail

echo "🔄 Starting migration from containerized MySQL to RDS MySQL..."

# Variables (update these)
export CLUSTER_NAME="ecs-lamp-cluster"
export ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
export VPC_ID="vpc-0c24660f703a81af6"
export PRIVATE_SUBNET_1="subnet-0ae7b9b5c1ff7c6e7"
export PRIVATE_SUBNET_2="subnet-078cb3d391c5acc25"
export ECS_SG="sg-0f7e614c724911594"

# Step 1: Scale down MySQL service
echo "📉 Scaling down MySQL service..."
aws ecs update-service \
    --cluster $CLUSTER_NAME \
    --service ecs-lamp-mysql-service \
    --desired-count 0
    
echo "Waiting for MySQL service to scale down..."
aws ecs wait services-stable --cluster $CLUSTER_NAME --services ecs-lamp-mysql-service

# Step 2: Get RDS endpoint
echo "🔍 Getting RDS endpoint..."
RDS_ENDPOINT=$(aws rds describe-db-instances \
    --db-instance-identifier ecs-lamp-mysql \
    --query 'DBInstances[0].Endpoint.Address' \
    --output text)

echo "RDS Endpoint: $RDS_ENDPOINT"

# Step 3: Update task definition with RDS endpoint
echo "📝 Updating web task definition..."
sed "s/ACCOUNT_ID/$ACCOUNT_ID/g; s/RDS_ENDPOINT_HERE/$RDS_ENDPOINT/g" \
    web-task-definition-rds.json > web-task-definition-updated.json

# Step 4: Register new task definition
echo "📋 Registering updated task definition..."
aws ecs register-task-definition --cli-input-json file://web-task-definition-updated.json

# Step 5: Update web service
echo "🔄 Updating web service..."
aws ecs update-service \
    --cluster $CLUSTER_NAME \
    --service ecs-lamp-web-service \
    --task-definition ecs-lamp-web:$(aws ecs describe-task-definition --task-definition ecs-lamp-web --query 'taskDefinition.revision' --output text)

aws ecs wait services-stable --cluster $CLUSTER_NAME --services ecs-lamp-web-service

# Step 6: Initialize RDS database using ECS task
echo "🗄️ Initializing RDS database using ECS task..."

# Register init task definition
aws ecs register-task-definition --cli-input-json file://init-rds-task.json

echo "Database initialization task registered. Wait for 1 minute before Starting task..."
sleep 60
# Run one-time task to initialize database
echo "🚀 Starting database initialization task..."
TASK_ARN=$(aws ecs run-task \
    --cluster $CLUSTER_NAME \
    --task-definition rds-init-task:1 \
    --launch-type FARGATE \
    --network-configuration "awsvpcConfiguration={subnets=[$PRIVATE_SUBNET_1],securityGroups=[$ECS_SG]}" \
    --query 'tasks[0].taskArn' \
    --output text)

echo "Task started: $TASK_ARN"

echo "Database initialization task started. Check CloudWatch logs for completion."

# Step 7: Delete MySQL service and resources
echo "🗑️ Cleaning up MySQL container resources..."
#aws ecs delete-service --cluster $CLUSTER_NAME --service ecs-lamp-mysql-service --force

# Wait a bit for service deletion
sleep 30

# Delete EFS (optional - uncomment if you want to remove EFS)
# EFS_ID=$(aws efs describe-file-systems --query 'FileSystems[?Name==`ecs-lamp-mysql-data`].FileSystemId' --output text)
# aws efs delete-mount-target --mount-target-id $(aws efs describe-mount-targets --file-system-id $EFS_ID --query 'MountTargets[0].MountTargetId' --output text)
# aws efs delete-file-system --file-system-id $EFS_ID

echo "✅ Migration completed successfully!"
echo "🌐 Your application now uses RDS MySQL at: $RDS_ENDPOINT"
echo "🔗 Test your application at the ALB endpoint"