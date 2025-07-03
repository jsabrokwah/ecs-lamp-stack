# LAMP Application ECS Deployment Implementation Guide

## Prerequisites Setup

### 1. Install Required Tools

```bash
# Install AWS CLI v2
curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip"
unzip awscliv2.zip
sudo ./aws/install

# Install Docker
sudo apt update
sudo apt install docker.io -y
sudo systemctl start docker
sudo systemctl enable docker
sudo usermod -aG docker $USER
```

### 2. Configure AWS CLI

```bash
# Configure AWS credentials
aws configure
# Enter your Access Key ID, Secret Access Key, Region (us-east-1), and output format (json)

# Verify configuration
aws sts get-caller-identity

# Set environment variables for cluster configuration
export CLUSTER_NAME=ecs-lamp-cluster
export AWS_DEFAULT_REGION=us-east-1
```

## Phase 1: IAM Roles and Policies Setup

### 1.1 Create ECS Task Execution Role

```bash
# Create trust policy for ECS tasks
cat > ecs-task-trust-policy.json << EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Service": "ecs-tasks.amazonaws.com"
      },
      "Action": "sts:AssumeRole"
    }
  ]
}
EOF

# Create the role
aws iam create-role \
    --role-name ecsTaskExecutionRole \
    --assume-role-policy-document file://ecs-task-trust-policy.json

# Attach AWS managed policy
aws iam attach-role-policy \
    --role-name ecsTaskExecutionRole \
    --policy-arn arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy

# Create custom policy for ECR and CloudWatch
cat > ecs-custom-policy.json << EOF
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "ecr:GetAuthorizationToken",
                "ecr:BatchCheckLayerAvailability",
                "ecr:GetDownloadUrlForLayer",
                "ecr:BatchGetImage",
                "logs:CreateLogStream",
                "logs:PutLogEvents",
                "logs:CreateLogGroup"
            ],
            "Resource": "*"
        }
    ]
}
EOF

aws iam create-policy \
    --policy-name ECSCustomPolicy \
    --policy-document file://ecs-custom-policy.json

# Get account ID for policy ARN
ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)

aws iam attach-role-policy \
    --role-name ecsTaskExecutionRole \
    --policy-arn arn:aws:iam::${ACCOUNT_ID}:policy/ECSCustomPolicy
```

## Phase 2: VPC and Networking Setup

### 2.1 Create VPC Infrastructure

```bash
# Create VPC
VPC_ID=$(aws ec2 create-vpc \
    --cidr-block 10.0.0.0/16 \
    --query 'Vpc.VpcId' \
    --output text)

aws ec2 create-tags \
    --resources $VPC_ID \
    --tags Key=Name,Value=ecs-lamp-vpc

# Create Internet Gateway
IGW_ID=$(aws ec2 create-internet-gateway \
    --query 'InternetGateway.InternetGatewayId' \
    --output text)

aws ec2 attach-internet-gateway \
    --vpc-id $VPC_ID \
    --internet-gateway-id $IGW_ID

# Create public subnets
PUBLIC_SUBNET_1=$(aws ec2 create-subnet \
    --vpc-id $VPC_ID \
    --cidr-block 10.0.1.0/24 \
    --availability-zone us-east-1a \
    --query 'Subnet.SubnetId' \
    --output text)

PUBLIC_SUBNET_2=$(aws ec2 create-subnet \
    --vpc-id $VPC_ID \
    --cidr-block 10.0.2.0/24 \
    --availability-zone us-east-1b \
    --query 'Subnet.SubnetId' \
    --output text)

# Create private subnets
PRIVATE_SUBNET_1=$(aws ec2 create-subnet \
    --vpc-id $VPC_ID \
    --cidr-block 10.0.3.0/24 \
    --availability-zone us-east-1a \
    --query 'Subnet.SubnetId' \
    --output text)

PRIVATE_SUBNET_2=$(aws ec2 create-subnet \
    --vpc-id $VPC_ID \
    --cidr-block 10.0.4.0/24 \
    --availability-zone us-east-1b \
    --query 'Subnet.SubnetId' \
    --output text)

# Create NAT Gateway
NAT_ALLOCATION_ID=$(aws ec2 allocate-address \
    --domain vpc \
    --query 'AllocationId' \
    --output text)

NAT_GATEWAY_ID=$(aws ec2 create-nat-gateway \
    --subnet-id $PUBLIC_SUBNET_1 \
    --allocation-id $NAT_ALLOCATION_ID \
    --query 'NatGateway.NatGatewayId' \
    --output text)

# Create route tables
PUBLIC_RT=$(aws ec2 create-route-table \
    --vpc-id $VPC_ID \
    --query 'RouteTable.RouteTableId' \
    --output text)

PRIVATE_RT=$(aws ec2 create-route-table \
    --vpc-id $VPC_ID \
    --query 'RouteTable.RouteTableId' \
    --output text)

# Create routes
aws ec2 create-route \
    --route-table-id $PUBLIC_RT \
    --destination-cidr-block 0.0.0.0/0 \
    --gateway-id $IGW_ID

aws ec2 create-route \
    --route-table-id $PRIVATE_RT \
    --destination-cidr-block 0.0.0.0/0 \
    --nat-gateway-id $NAT_GATEWAY_ID

# Associate subnets with route tables
aws ec2 associate-route-table \
    --subnet-id $PUBLIC_SUBNET_1 \
    --route-table-id $PUBLIC_RT

aws ec2 associate-route-table \
    --subnet-id $PUBLIC_SUBNET_2 \
    --route-table-id $PUBLIC_RT

aws ec2 associate-route-table \
    --subnet-id $PRIVATE_SUBNET_1 \
    --route-table-id $PRIVATE_RT

aws ec2 associate-route-table \
    --subnet-id $PRIVATE_SUBNET_2 \
    --route-table-id $PRIVATE_RT
```

### 2.2 Create Security Groups

```bash
# ALB Security Group
ALB_SG=$(aws ec2 create-security-group \
    --group-name ecs-lamp-alb-sg \
    --description "Security group for ALB" \
    --vpc-id $VPC_ID \
    --query 'GroupId' \
    --output text)

aws ec2 authorize-security-group-ingress \
    --group-id $ALB_SG \
    --protocol tcp \
    --port 80 \
    --cidr 0.0.0.0/0

aws ec2 authorize-security-group-ingress \
    --group-id $ALB_SG \
    --protocol tcp \
    --port 443 \
    --cidr 0.0.0.0/0

# ECS Security Group
ECS_SG=$(aws ec2 create-security-group \
    --group-name ecs-lamp-ecs-sg \
    --description "Security group for ECS tasks" \
    --vpc-id $VPC_ID \
    --query 'GroupId' \
    --output text)

aws ec2 authorize-security-group-ingress \
    --group-id $ECS_SG \
    --protocol tcp \
    --port 80 \
    --source-group $ALB_SG

aws ec2 authorize-security-group-ingress \
    --group-id $ECS_SG \
    --protocol tcp \
    --port 3306 \
    --source-group $ECS_SG

# EFS Security Group
EFS_SG=$(aws ec2 create-security-group \
    --group-name ecs-lamp-efs-sg \
    --description "Security group for EFS" \
    --vpc-id $VPC_ID \
    --query 'GroupId' \
    --output text)

aws ec2 authorize-security-group-ingress \
    --group-id $EFS_SG \
    --protocol tcp \
    --port 2049 \
    --source-group $ECS_SG
```

## Phase 3: EFS Setup for MySQL Persistence

```bash
# Create EFS file system
EFS_ID=$(aws efs create-file-system \
    --creation-token ecs-lamp-mysql-data \
    --performance-mode generalPurpose \
    --throughput-mode provisioned \
    --provisioned-throughput-in-mibps 100 \
    --query 'FileSystemId' \
    --output text)

# Create mount targets
aws efs create-mount-target \
    --file-system-id $EFS_ID \
    --subnet-id $PRIVATE_SUBNET_1 \
    --security-groups $EFS_SG

aws efs create-mount-target \
    --file-system-id $EFS_ID \
    --subnet-id $PRIVATE_SUBNET_2 \
    --security-groups $EFS_SG

# Create access point for MySQL
EFS_ACCESS_POINT=$(aws efs create-access-point \
    --file-system-id $EFS_ID \
    --posix-user Uid=999,Gid=999 \
    --root-directory Path="/mysql-data",CreationInfo='{OwnerUid=999,OwnerGid=999,Permissions=755}' \
    --query 'AccessPointId' \
    --output text)
```

## Phase 4: ECR Repository Setup

```bash
# Create ECR repositories
aws ecr create-repository --repository-name ecs-lamp-web
aws ecr create-repository --repository-name ecs-lamp-mysql

# Get login token
aws ecr get-login-password --region us-east-1 | docker login --username AWS --password-stdin ${ACCOUNT_ID}.dkr.ecr.us-east-1.amazonaws.com
```

## Phase 5: Application Development

### 5.1 Create Application Structure

```bash
mkdir -p ecs-lamp-app/{web/src,mysql}
cd ecs-lamp-app
```

### 5.2 Web Application Files

```bash
# Create web Dockerfile
cat > web/Dockerfile << 'EOF'
FROM php:8.1-apache

# Install mysqli extension
RUN docker-php-ext-install mysqli

# Copy application files
COPY src/ /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# Enable Apache modules
RUN a2enmod rewrite

EXPOSE 80
EOF

# Create PHP application
cat > web/src/index.php << 'EOF'
<?php
require_once 'config.php';

echo "<h1>LAMP Stack on ECS</h1>";
echo "<p>Server Time: " . date('Y-m-d H:i:s') . "</p>";

// Test database connection
try {
    $pdo = new PDO($dsn, $username, $password);
    echo "<p style='color: green;'>Database connection: SUCCESS</p>";
    
    // Create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Insert sample data
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (id, name, email) VALUES (1, 'John Doe', 'john@example.com')");
    $stmt->execute();
    
    // Display users
    $stmt = $pdo->query("SELECT * FROM users");
    echo "<h2>Users:</h2><ul>";
    while ($row = $stmt->fetch()) {
        echo "<li>ID: {$row['id']}, Name: {$row['name']}, Email: {$row['email']}</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database connection failed: " . $e->getMessage() . "</p>";
}
?>
EOF

cat > web/src/config.php << 'EOF'
<?php
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'lampdb';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: 'rootpassword';

$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
?>
EOF

cat > web/src/health.php << 'EOF'
<?php
http_response_code(200);
echo json_encode(['status' => 'healthy', 'timestamp' => time()]);
?>
EOF
```

### 5.3 MySQL Container Files

```bash
cat > mysql/Dockerfile << 'EOF'
FROM mysql:8.0

# Copy initialization script
COPY init.sql /docker-entrypoint-initdb.d/

# Set permissions
RUN chmod 644 /docker-entrypoint-initdb.d/init.sql

EXPOSE 3306
EOF

cat > mysql/init.sql << 'EOF'
CREATE DATABASE IF NOT EXISTS lampdb;
USE lampdb;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (name, email) VALUES 
('John Doe', 'john@example.com'),
('Jane Smith', 'jane@example.com');
EOF
```

### 5.4 Build and Push Images

```bash
# Build web image
cd web
docker build -t ecs-lamp-web .
docker tag ecs-lamp-web:latest ${ACCOUNT_ID}.dkr.ecr.us-east-1.amazonaws.com/ecs-lamp-web:latest
docker push ${ACCOUNT_ID}.dkr.ecr.us-east-1.amazonaws.com/ecs-lamp-web:latest

# Build MySQL image
cd ../mysql
docker build -t ecs-lamp-mysql .
docker tag ecs-lamp-mysql:latest ${ACCOUNT_ID}.dkr.ecr.us-east-1.amazonaws.com/ecs-lamp-mysql:latest
docker push ${ACCOUNT_ID}.dkr.ecr.us-east-1.amazonaws.com/ecs-lamp-mysql:latest

cd ..
```

## Phase 6: ECS Cluster Setup

```bash
# Create ECS cluster using AWS CLI
aws ecs create-cluster --cluster-name $CLUSTER_NAME

# Verify cluster creation
aws ecs describe-clusters --clusters $CLUSTER_NAME
```

## Phase 7: Load Balancer Setup

```bash
# Create Application Load Balancer
ALB_ARN=$(aws elbv2 create-load-balancer \
    --name ecs-lamp-alb \
    --subnets $PUBLIC_SUBNET_1 $PUBLIC_SUBNET_2 \
    --security-groups $ALB_SG \
    --query 'LoadBalancers[0].LoadBalancerArn' \
    --output text)

# Create target group
TARGET_GROUP_ARN=$(aws elbv2 create-target-group \
    --name ecs-lamp-web-tg \
    --protocol HTTP \
    --port 80 \
    --vpc-id $VPC_ID \
    --target-type ip \
    --health-check-path /health.php \
    --health-check-interval-seconds 30 \
    --health-check-timeout-seconds 5 \
    --healthy-threshold-count 2 \
    --unhealthy-threshold-count 3 \
    --query 'TargetGroups[0].TargetGroupArn' \
    --output text)

# Create listener
aws elbv2 create-listener \
    --load-balancer-arn $ALB_ARN \
    --protocol HTTP \
    --port 80 \
    --default-actions Type=forward,TargetGroupArn=$TARGET_GROUP_ARN
```

## Phase 8: Task Definitions

### 8.1 MySQL Task Definition

```bash
cat > mysql-task-definition.json << EOF
{
    "family": "ecs-lamp-mysql",
    "networkMode": "awsvpc",
    "requiresCompatibilities": ["FARGATE"],
    "cpu": "512",
    "memory": "1024",
    "executionRoleArn": "arn:aws:iam::${ACCOUNT_ID}:role/ecsTaskExecutionRole",
    "containerDefinitions": [
        {
            "name": "mysql",
            "image": "${ACCOUNT_ID}.dkr.ecr.us-east-1.amazonaws.com/ecs-lamp-mysql:latest",
            "essential": true,
            "portMappings": [
                {
                    "containerPort": 3306,
                    "protocol": "tcp"
                }
            ],
            "environment": [
                {
                    "name": "MYSQL_ROOT_PASSWORD",
                    "value": "rootpassword"
                },
                {
                    "name": "MYSQL_DATABASE",
                    "value": "lampdb"
                }
            ],
            "mountPoints": [
                {
                    "sourceVolume": "mysql-data",
                    "containerPath": "/var/lib/mysql"
                }
            ],
            "logConfiguration": {
                "logDriver": "awslogs",
                "options": {
                    "awslogs-group": "/ecs/ecs-lamp-mysql",
                    "awslogs-region": "us-east-1",
                    "awslogs-stream-prefix": "ecs",
                    "awslogs-create-group": "true"
                }
            }
        }
    ],
    "volumes": [
        {
            "name": "mysql-data",
            "efsVolumeConfiguration": {
                "fileSystemId": "${EFS_ID}",
                "accessPointId": "${EFS_ACCESS_POINT}",
                "transitEncryption": "ENABLED"
            }
        }
    ]
}
EOF

# Register MySQL task definition
aws ecs register-task-definition --cli-input-json file://mysql-task-definition.json
```

### 8.2 Web Application Task Definition

```bash
cat > web-task-definition.json << EOF
{
    "family": "ecs-lamp-web",
    "networkMode": "awsvpc",
    "requiresCompatibilities": ["FARGATE"],
    "cpu": "256",
    "memory": "512",
    "executionRoleArn": "arn:aws:iam::${ACCOUNT_ID}:role/ecsTaskExecutionRole",
    "containerDefinitions": [
        {
            "name": "web",
            "image": "${ACCOUNT_ID}.dkr.ecr.us-east-1.amazonaws.com/ecs-lamp-web:latest",
            "essential": true,
            "portMappings": [
                {
                    "containerPort": 80,
                    "protocol": "tcp"
                }
            ],
            "environment": [
                {
                    "name": "DB_HOST",
                    "value": "ecs-lamp-mysql.ecs-lamp-cluster.local"
                },
                {
                    "name": "DB_NAME",
                    "value": "lampdb"
                },
                {
                    "name": "DB_USER",
                    "value": "root"
                },
                {
                    "name": "DB_PASSWORD",
                    "value": "rootpassword"
                }
            ],
            "healthCheck": {
                "command": ["CMD-SHELL", "curl -f http://localhost/health.php || exit 1"],
                "interval": 30,
                "timeout": 5,
                "retries": 3,
                "startPeriod": 60
            },
            "logConfiguration": {
                "logDriver": "awslogs",
                "options": {
                    "awslogs-group": "/ecs/ecs-lamp-web",
                    "awslogs-region": "us-east-1",
                    "awslogs-stream-prefix": "ecs",
                    "awslogs-create-group": "true"
                }
            }
        }
    ]
}
EOF

# Register web task definition
aws ecs register-task-definition --cli-input-json file://web-task-definition.json
```

## Phase 9: Service Discovery

```bash
# Create service discovery namespace
NAMESPACE_ID=$(aws servicediscovery create-private-dns-namespace \
    --name ecs-lamp-cluster.local \
    --vpc $VPC_ID \
    --query 'OperationId' \
    --output text)

# Wait for namespace creation
sleep 30

# Get namespace ID
NAMESPACE_ID=$(aws servicediscovery list-namespaces \
    --filters Name=TYPE,Values=DNS_PRIVATE \
    --query 'Namespaces[?Name==`ecs-lamp-cluster.local`].Id' \
    --output text)

# Create service discovery service for MySQL
MYSQL_SERVICE_ID=$(aws servicediscovery create-service \
    --name ecs-lamp-mysql \
    --dns-config NamespaceId=${NAMESPACE_ID},DnsRecords=[{Type=A,TTL=300}] \
    --health-check-custom-config FailureThreshold=1 \
    --query 'Service.Id' \
    --output text)
```

## Phase 10: Deploy ECS Services Using AWS CLI

### 10.1 Deploy MySQL Service

```bash
# Create MySQL service
aws ecs create-service \
    --cluster $CLUSTER_NAME \
    --service-name ecs-lamp-mysql-service \
    --task-definition ecs-lamp-mysql:1 \
    --desired-count 1 \
    --launch-type FARGATE \
    --network-configuration "awsvpcConfiguration={subnets=[$PRIVATE_SUBNET_1,$PRIVATE_SUBNET_2],securityGroups=[$ECS_SG],assignPublicIp=DISABLED}" \
    --service-registries "registryArn=arn:aws:servicediscovery:us-east-1:${ACCOUNT_ID}:service/${MYSQL_SERVICE_ID}"

# Wait for MySQL service to be stable
aws ecs wait services-stable --cluster $CLUSTER_NAME --services ecs-lamp-mysql-service
```

### 10.2 Deploy Web Service

```bash
# Create web service with load balancer integration
aws ecs create-service \
    --cluster $CLUSTER_NAME \
    --service-name ecs-lamp-web-service \
    --task-definition ecs-lamp-web:1 \
    --desired-count 2 \
    --launch-type FARGATE \
    --network-configuration "awsvpcConfiguration={subnets=[$PRIVATE_SUBNET_1,$PRIVATE_SUBNET_2],securityGroups=[$ECS_SG],assignPublicIp=DISABLED}" \
    --load-balancers "targetGroupArn=$TARGET_GROUP_ARN,containerName=web,containerPort=80"

# Wait for web service to be stable
aws ecs wait services-stable --cluster $CLUSTER_NAME --services ecs-lamp-web-service
```

### 10.3 Scale Services (if needed)

```bash
# Scale web service to 3 instances
aws ecs update-service \
    --cluster $CLUSTER_NAME \
    --service ecs-lamp-web-service \
    --desired-count 3

# Wait for scaling to complete
aws ecs wait services-stable --cluster $CLUSTER_NAME --services ecs-lamp-web-service
```

## Phase 11: Verification and Testing

### 11.1 Check Service Status

```bash
# List all tasks in the cluster
aws ecs list-tasks --cluster $CLUSTER_NAME

# Get detailed service information
aws ecs describe-services --cluster $CLUSTER_NAME --services ecs-lamp-mysql-service ecs-lamp-web-service

# Check task status
aws ecs describe-tasks --cluster $CLUSTER_NAME --tasks $(aws ecs list-tasks --cluster $CLUSTER_NAME --query 'taskArns' --output text)

# Get service status summary
aws ecs describe-services --cluster $CLUSTER_NAME --services ecs-lamp-web-service --query 'services[0].{ServiceName:serviceName,Status:status,RunningCount:runningCount,DesiredCount:desiredCount}'

# Get ALB DNS name
ALB_DNS=$(aws elbv2 describe-load-balancers \
    --load-balancer-arns $ALB_ARN \
    --query 'LoadBalancers[0].DNSName' \
    --output text)

echo "Application URL: http://$ALB_DNS"
```

### 11.2 Test Application

```bash
# Test health endpoint
curl http://$ALB_DNS/health.php

# Test main application
curl http://$ALB_DNS/

# Check target group health
aws elbv2 describe-target-health --target-group-arn $TARGET_GROUP_ARN
```

## Phase 12: Monitoring Setup

```bash
# Create CloudWatch dashboard
cat > dashboard.json << EOF
{
    "widgets": [
        {
            "type": "metric",
            "properties": {
                "metrics": [
                    ["AWS/ECS", "CPUUtilization", "ServiceName", "ecs-lamp-web-service", "ClusterName", "ecs-lamp-cluster"],
                    [".", "MemoryUtilization", ".", ".", ".", "."]
                ],
                "period": 300,
                "stat": "Average",
                "region": "us-east-1",
                "title": "ECS Service Metrics"
            }
        }
    ]
}
EOF

aws cloudwatch put-dashboard \
    --dashboard-name "ecs-LAMP-ECS-Dashboard" \
    --dashboard-body file://dashboard.json
```

## Phase 13: Cleanup Commands (Optional)

```bash
# Scale down services to 0
aws ecs update-service --cluster $CLUSTER_NAME --service ecs-lamp-web-service --desired-count 0
aws ecs update-service --cluster $CLUSTER_NAME --service ecs-lamp-mysql-service --desired-count 0

# Wait for services to scale down
aws ecs wait services-stable --cluster $CLUSTER_NAME --services ecs-lamp-web-service ecs-lamp-mysql-service

# Delete services
aws ecs delete-service --cluster $CLUSTER_NAME --service ecs-lamp-web-service --force
aws ecs delete-service --cluster $CLUSTER_NAME --service ecs-lamp-mysql-service --force

# Delete cluster
aws ecs delete-cluster --cluster $CLUSTER_NAME

# Delete load balancer
aws elbv2 delete-load-balancer --load-balancer-arn $ALB_ARN

# Delete target group
aws elbv2 delete-target-group --target-group-arn $TARGET_GROUP_ARN

# Delete EFS
aws efs delete-mount-target --mount-target-id $(aws efs describe-mount-targets --file-system-id $EFS_ID --query 'MountTargets[0].MountTargetId' --output text)
aws efs delete-file-system --file-system-id $EFS_ID

# Delete VPC resources (in reverse order)
aws ec2 delete-nat-gateway --nat-gateway-id $NAT_GATEWAY_ID
aws ec2 release-address --allocation-id $NAT_ALLOCATION_ID
aws ec2 delete-subnet --subnet-id $PRIVATE_SUBNET_1
aws ec2 delete-subnet --subnet-id $PRIVATE_SUBNET_2
aws ec2 delete-subnet --subnet-id $PUBLIC_SUBNET_1
aws ec2 delete-subnet --subnet-id $PUBLIC_SUBNET_2
aws ec2 detach-internet-gateway --internet-gateway-id $IGW_ID --vpc-id $VPC_ID
aws ec2 delete-internet-gateway --internet-gateway-id $IGW_ID
aws ec2 delete-vpc --vpc-id $VPC_ID
```

## Troubleshooting Commands

```bash
# Check service status
aws ecs describe-services --cluster $CLUSTER_NAME --services ecs-lamp-web-service ecs-lamp-mysql-service

# Get task details
TASK_ARN=$(aws ecs list-tasks --cluster $CLUSTER_NAME --service-name ecs-lamp-web-service --query 'taskArns[0]' --output text)
aws ecs describe-tasks --cluster $CLUSTER_NAME --tasks $TASK_ARN

# Check task logs using AWS CLI
aws logs describe-log-groups --log-group-name-prefix "/ecs/ecs-lamp"

# Get task definition details
aws ecs describe-task-definition --task-definition ecs-lamp-web:1
aws ecs describe-task-definition --task-definition ecs-lamp-mysql:1

# Check service events
aws ecs describe-services --cluster $CLUSTER_NAME --services ecs-lamp-web-service --query 'services[0].events'
aws ecs describe-services --cluster $CLUSTER_NAME --services ecs-lamp-mysql-service --query 'services[0].events'

# View container logs
TASK_ID=$(echo $TASK_ARN | cut -d'/' -f3)
aws logs get-log-events \
    --log-group-name "/ecs/ecs-lamp-web" \
    --log-stream-name "ecs/web/$TASK_ID" \
    --start-from-head

# View MySQL logs
MYSQL_TASK_ARN=$(aws ecs list-tasks --cluster $CLUSTER_NAME --service-name ecs-lamp-mysql-service --query 'taskArns[0]' --output text)
MYSQL_TASK_ID=$(echo $MYSQL_TASK_ARN | cut -d'/' -f3)
aws logs get-log-events \
    --log-group-name "/ecs/ecs-lamp-mysql" \
    --log-stream-name "ecs/mysql/$MYSQL_TASK_ID" \
    --start-from-head
```

## Success Verification Checklist

- [ ] ECS cluster created successfully
- [ ] Task definitions registered
- [ ] Services running with desired count
- [ ] Load balancer health checks passing
- [ ] Application accessible via ALB DNS name
- [ ] Database connectivity working
- [ ] CloudWatch logs being generated
- [ ] All screenshots captured for submission

## Important Notes

1. Replace `${ACCOUNT_ID}` with your actual AWS account ID throughout the commands
2. Ensure all environment variables are set correctly before running commands
3. Wait for services to stabilize before proceeding to next steps
4. Monitor CloudWatch logs for any errors during deployment
5. Test the application thoroughly before submission
6. Keep track of all resource ARNs and IDs for cleanup if needed

This implementation guide provides all the necessary commands and configurations to deploy a LAMP application on ECS using only CLI commands.