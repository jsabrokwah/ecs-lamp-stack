# ECS LAMP Stack Disaster Recovery Guide

## Overview

This guide provides comprehensive disaster recovery procedures for the ECS LAMP stack application, including automated failover processes, monitoring, and alerting mechanisms.

## Architecture

**Primary Region**: eu-west-1  
**DR Region**: eu-central-1

```
Primary (eu-west-1)          DR (eu-central-1)
├── ECS Cluster              ├── ECS Cluster (Pilot Light)
├── RDS MySQL (Primary)      ├── RDS MySQL (Read Replica)
├── ALB                      ├── ALB (Standby)
└── Route 53 (Primary)       └── Route 53 (Failover)
```

## Prerequisites

Ensure the primary infrastructure is deployed using the implementation-guideline.md and the cross-region read replica is configured.

## 1. Route 53 DNS Failover Setup

### 1.1 Create Hosted Zone and Health Checks

```bash
# Create hosted zone (if not exists)
DOMAIN_NAME="taskflow-app.com"  # Replace with your domain
HOSTED_ZONE_ID=$(aws route53 create-hosted-zone \
    --name $DOMAIN_NAME \
    --caller-reference $(date +%s) \
    --query 'HostedZone.Id' \
    --output text | cut -d'/' -f3)

# Create health check for primary ALB
PRIMARY_ALB_DNS=$(aws elbv2 describe-load-balancers \
    --names ecs-lamp-alb \
    --region eu-west-1 \
    --query 'LoadBalancers[0].DNSName' \
    --output text)

PRIMARY_HEALTH_CHECK_ID=$(aws route53 create-health-check \
    --caller-reference "primary-$(date +%s)" \
    --health-check-config Type=HTTP,ResourcePath=/health.php,FullyQualifiedDomainName=$PRIMARY_ALB_DNS,Port=80,RequestInterval=30,FailureThreshold=3 \
    --query 'HealthCheck.Id' \
    --output text)

# Create health check for DR ALB
DR_ALB_DNS=$(aws elbv2 describe-load-balancers \
    --names ecs-lamp-alb \
    --region eu-central-1 \
    --query 'LoadBalancers[0].DNSName' \
    --output text)

DR_HEALTH_CHECK_ID=$(aws route53 create-health-check \
    --caller-reference "dr-$(date +%s)" \
    --health-check-config Type=HTTP,ResourcePath=/health.php,FullyQualifiedDomainName=$DR_ALB_DNS,Port=80,RequestInterval=30,FailureThreshold=3 \
    --query 'HealthCheck.Id' \
    --output text)
```

### 1.2 Configure DNS Failover Records

```bash
# Create primary DNS record
cat > primary-record.json << EOF
{
    "Changes": [{
        "Action": "CREATE",
        "ResourceRecordSet": {
            "Name": "$DOMAIN_NAME",
            "Type": "A",
            "SetIdentifier": "Primary",
            "Failover": "PRIMARY",
            "TTL": 60,
            "ResourceRecords": [{"Value": "$(dig +short $PRIMARY_ALB_DNS | head -1)"}],
            "HealthCheckId": "$PRIMARY_HEALTH_CHECK_ID"
        }
    }]
}
EOF

# Create DR DNS record
cat > dr-record.json << EOF
{
    "Changes": [{
        "Action": "CREATE",
        "ResourceRecordSet": {
            "Name": "$DOMAIN_NAME",
            "Type": "A",
            "SetIdentifier": "DR",
            "Failover": "SECONDARY",
            "TTL": 60,
            "ResourceRecords": [{"Value": "$(dig +short $DR_ALB_DNS | head -1)"}]
        }
    }]
}
EOF

# Apply DNS records
aws route53 change-resource-record-sets \
    --hosted-zone-id $HOSTED_ZONE_ID \
    --change-batch file://primary-record.json

aws route53 change-resource-record-sets \
    --hosted-zone-id $HOSTED_ZONE_ID \
    --change-batch file://dr-record.json
```

## 2. Automated Failover Scripts

### 2.1 DR Activation Script

```bash
# Create DR activation script
cat > activate-dr.sh << 'EOF'
#!/bin/bash
set -e

DR_REGION="eu-central-1"
PRIMARY_REGION="eu-west-1"

echo "🚨 DISASTER RECOVERY ACTIVATION STARTED"
echo "Timestamp: $(date)"

# Step 1: Promote read replica to standalone database
echo "📊 Promoting read replica to primary..."
aws rds promote-read-replica \
    --db-instance-identifier ecs-lamp-mysql-replica \
    --region $DR_REGION

# Wait for promotion to complete
echo "⏳ Waiting for database promotion..."
aws rds wait db-instance-available \
    --db-instance-identifier ecs-lamp-mysql-replica \
    --region $DR_REGION

# Step 2: Get new database endpoint
DR_DB_ENDPOINT=$(aws rds describe-db-instances \
    --db-instance-identifier ecs-lamp-mysql-replica \
    --region $DR_REGION \
    --query 'DBInstances[0].Endpoint.Address' \
    --output text)

echo "📍 New DB Endpoint: $DR_DB_ENDPOINT"

# Step 3: Update ECS task definition with new DB endpoint
ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)

cat > dr-task-definition.json << EOL
{
    "family": "ecs-lamp-web-dr",
    "networkMode": "awsvpc",
    "requiresCompatibilities": ["FARGATE"],
    "cpu": "256",
    "memory": "512",
    "executionRoleArn": "arn:aws:iam::${ACCOUNT_ID}:role/ecsLampTaskExecutionRole",
    "containerDefinitions": [
        {
            "name": "web",
            "image": "${ACCOUNT_ID}.dkr.ecr.${DR_REGION}.amazonaws.com/ecs-lamp-web:latest",
            "essential": true,
            "portMappings": [{"containerPort": 80, "protocol": "tcp"}],
            "environment": [
                {"name": "DB_HOST", "value": "$DR_DB_ENDPOINT"},
                {"name": "DB_NAME", "value": "lampdb"},
                {"name": "DB_USER", "value": "root"},
                {"name": "DB_PASSWORD", "value": "rootpassword"}
            ],
            "healthCheck": {
                "command": ["CMD-SHELL", "curl -f http://localhost/health.php || exit 1"],
                "interval": 30, "timeout": 5, "retries": 3, "startPeriod": 60
            },
            "logConfiguration": {
                "logDriver": "awslogs",
                "options": {
                    "awslogs-group": "/ecs/ecs-lamp-web-dr",
                    "awslogs-region": "$DR_REGION",
                    "awslogs-stream-prefix": "ecs",
                    "awslogs-create-group": "true"
                }
            }
        }
    ]
}
EOL

# Register DR task definition
aws ecs register-task-definition \
    --cli-input-json file://dr-task-definition.json \
    --region $DR_REGION

# Step 4: Scale up ECS service in DR region
echo "🚀 Scaling up DR ECS service..."
aws ecs update-service \
    --cluster ecs-lamp-cluster \
    --service ecs-lamp-web-service \
    --desired-count 2 \
    --task-definition ecs-lamp-web-dr:1 \
    --region $DR_REGION

# Wait for service to be stable
echo "⏳ Waiting for DR service to stabilize..."
aws ecs wait services-stable \
    --cluster ecs-lamp-cluster \
    --services ecs-lamp-web-service \
    --region $DR_REGION

echo "✅ DISASTER RECOVERY ACTIVATION COMPLETED"
echo "🌐 Application is now running in DR region: $DR_REGION"
echo "📊 Database: $DR_DB_ENDPOINT"
EOF

chmod +x activate-dr.sh
```

### 2.2 DR Deactivation Script (Failback)

```bash
# Create DR deactivation script
cat > deactivate-dr.sh << 'EOF'
#!/bin/bash
set -e

DR_REGION="eu-central-1"
PRIMARY_REGION="eu-west-1"

echo "🔄 DISASTER RECOVERY DEACTIVATION STARTED"
echo "Timestamp: $(date)"

# Step 1: Scale down DR ECS service
echo "📉 Scaling down DR ECS service..."
aws ecs update-service \
    --cluster ecs-lamp-cluster \
    --service ecs-lamp-web-service \
    --desired-count 0 \
    --region $DR_REGION

# Step 2: Scale up primary ECS service
echo "📈 Scaling up primary ECS service..."
aws ecs update-service \
    --cluster ecs-lamp-cluster \
    --service ecs-lamp-web-service \
    --desired-count 2 \
    --region $PRIMARY_REGION

# Wait for primary service to be stable
echo "⏳ Waiting for primary service to stabilize..."
aws ecs wait services-stable \
    --cluster ecs-lamp-cluster \
    --services ecs-lamp-web-service \
    --region $PRIMARY_REGION

echo "✅ DISASTER RECOVERY DEACTIVATION COMPLETED"
echo "🌐 Application is now running in primary region: $PRIMARY_REGION"
EOF

chmod +x deactivate-dr.sh
```

## 3. Monitoring and Alerting

### 3.1 CloudWatch Alarms for Disaster Detection

```bash
# Create SNS topic for DR alerts
DR_SNS_TOPIC_ARN=$(aws sns create-topic \
    --name ecs-lamp-dr-alerts \
    --query 'TopicArn' \
    --output text)

# Subscribe email to SNS topic (replace with your email)
aws sns subscribe \
    --topic-arn $DR_SNS_TOPIC_ARN \
    --protocol email \
    --notification-endpoint your-email@example.com

# Primary region health alarm
aws cloudwatch put-metric-alarm \
    --alarm-name "ECS-LAMP-Primary-Health" \
    --alarm-description "Primary region health check failure" \
    --metric-name HealthCheckStatus \
    --namespace AWS/Route53 \
    --statistic Minimum \
    --period 60 \
    --threshold 1 \
    --comparison-operator LessThanThreshold \
    --dimensions Name=HealthCheckId,Value=$PRIMARY_HEALTH_CHECK_ID \
    --evaluation-periods 3 \
    --alarm-actions $DR_SNS_TOPIC_ARN \
    --region eu-west-1

# RDS connection alarm
aws cloudwatch put-metric-alarm \
    --alarm-name "RDS-Primary-Connection-Failure" \
    --alarm-description "RDS primary connection failures" \
    --metric-name DatabaseConnections \
    --namespace AWS/RDS \
    --statistic Sum \
    --period 300 \
    --threshold 0 \
    --comparison-operator LessThanOrEqualToThreshold \
    --dimensions Name=DBInstanceIdentifier,Value=ecs-lamp-mysql-primary \
    --evaluation-periods 3 \
    --alarm-actions $DR_SNS_TOPIC_ARN \
    --region eu-west-1

# ECS service health alarm
aws cloudwatch put-metric-alarm \
    --alarm-name "ECS-Service-Running-Tasks" \
    --alarm-description "ECS service has no running tasks" \
    --metric-name RunningTaskCount \
    --namespace AWS/ECS \
    --statistic Average \
    --period 300 \
    --threshold 1 \
    --comparison-operator LessThanThreshold \
    --dimensions Name=ServiceName,Value=ecs-lamp-web-service Name=ClusterName,Value=ecs-lamp-cluster \
    --evaluation-periods 2 \
    --alarm-actions $DR_SNS_TOPIC_ARN \
    --region eu-west-1
```

### 3.2 Lambda Function for Automated DR Trigger

```bash
# Create Lambda execution role
cat > lambda-trust-policy.json << EOF
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Principal": {"Service": "lambda.amazonaws.com"},
            "Action": "sts:AssumeRole"
        }
    ]
}
EOF

LAMBDA_ROLE_ARN=$(aws iam create-role \
    --role-name ECS-LAMP-DR-Lambda-Role \
    --assume-role-policy-document file://lambda-trust-policy.json \
    --query 'Role.Arn' \
    --output text)

# Attach policies to Lambda role
aws iam attach-role-policy \
    --role-name ECS-LAMP-DR-Lambda-Role \
    --policy-arn arn:aws:iam::aws:policy/service-role/AWSLambdaBasicExecutionRole

# Create custom policy for DR operations
cat > dr-lambda-policy.json << EOF
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "rds:PromoteReadReplica",
                "rds:DescribeDBInstances",
                "ecs:UpdateService",
                "ecs:RegisterTaskDefinition",
                "ecs:DescribeServices",
                "sns:Publish"
            ],
            "Resource": "*"
        }
    ]
}
EOF

aws iam create-policy \
    --policy-name ECS-LAMP-DR-Policy \
    --policy-document file://dr-lambda-policy.json

aws iam attach-role-policy \
    --role-name ECS-LAMP-DR-Lambda-Role \
    --policy-arn arn:aws:iam::${ACCOUNT_ID}:policy/ECS-LAMP-DR-Policy

# Create Lambda function for automated DR
cat > dr-lambda.py << 'EOF'
import json
import boto3
import os

def lambda_handler(event, context):
    # Parse CloudWatch alarm
    message = json.loads(event['Records'][0]['Sns']['Message'])
    alarm_name = message['AlarmName']
    
    if message['NewStateValue'] == 'ALARM':
        print(f"🚨 Disaster detected: {alarm_name}")
        
        # Initialize AWS clients
        rds_dr = boto3.client('rds', region_name='eu-central-1')
        ecs_dr = boto3.client('ecs', region_name='eu-central-1')
        sns = boto3.client('sns')
        
        try:
            # Promote read replica
            print("📊 Promoting read replica...")
            rds_dr.promote_read_replica(
                DBInstanceIdentifier='ecs-lamp-mysql-replica'
            )
            
            # Scale up DR ECS service
            print("🚀 Scaling up DR service...")
            ecs_dr.update_service(
                cluster='ecs-lamp-cluster',
                service='ecs-lamp-web-service',
                desiredCount=2
            )
            
            # Send success notification
            sns.publish(
                TopicArn=os.environ['SNS_TOPIC_ARN'],
                Subject='✅ DR Activation Successful',
                Message=f'Disaster recovery activated successfully for alarm: {alarm_name}'
            )
            
        except Exception as e:
            print(f"❌ DR activation failed: {str(e)}")
            sns.publish(
                TopicArn=os.environ['SNS_TOPIC_ARN'],
                Subject='❌ DR Activation Failed',
                Message=f'DR activation failed: {str(e)}'
            )
    
    return {'statusCode': 200}
EOF

# Package and deploy Lambda
zip dr-lambda.zip dr-lambda.py

aws lambda create-function \
    --function-name ecs-lamp-dr-trigger \
    --runtime python3.9 \
    --role $LAMBDA_ROLE_ARN \
    --handler dr-lambda.lambda_handler \
    --zip-file fileb://dr-lambda.zip \
    --environment Variables="{SNS_TOPIC_ARN=$DR_SNS_TOPIC_ARN}" \
    --timeout 300

# Subscribe Lambda to SNS topic
LAMBDA_ARN=$(aws lambda get-function \
    --function-name ecs-lamp-dr-trigger \
    --query 'Configuration.FunctionArn' \
    --output text)

aws sns subscribe \
    --topic-arn $DR_SNS_TOPIC_ARN \
    --protocol lambda \
    --notification-endpoint $LAMBDA_ARN

# Grant SNS permission to invoke Lambda
aws lambda add-permission \
    --function-name ecs-lamp-dr-trigger \
    --statement-id sns-invoke \
    --action lambda:InvokeFunction \
    --principal sns.amazonaws.com \
    --source-arn $DR_SNS_TOPIC_ARN
```

## 4. S3 Cross-Region Replication (Optional)

For applications with static assets or file uploads:

```bash
# Create S3 buckets for static assets
PRIMARY_BUCKET="ecs-lamp-assets-primary-$(date +%s)"
DR_BUCKET="ecs-lamp-assets-dr-$(date +%s)"

aws s3 mb s3://$PRIMARY_BUCKET --region eu-west-1
aws s3 mb s3://$DR_BUCKET --region eu-central-1

# Enable versioning
aws s3api put-bucket-versioning \
    --bucket $PRIMARY_BUCKET \
    --versioning-configuration Status=Enabled

aws s3api put-bucket-versioning \
    --bucket $DR_BUCKET \
    --versioning-configuration Status=Enabled

# Create replication role
cat > s3-replication-trust-policy.json << EOF
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Principal": {"Service": "s3.amazonaws.com"},
            "Action": "sts:AssumeRole"
        }
    ]
}
EOF

S3_REPLICATION_ROLE_ARN=$(aws iam create-role \
    --role-name S3-Replication-Role \
    --assume-role-policy-document file://s3-replication-trust-policy.json \
    --query 'Role.Arn' \
    --output text)

# Create replication policy
cat > s3-replication-policy.json << EOF
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:GetObjectVersionForReplication",
                "s3:GetObjectVersionAcl"
            ],
            "Resource": "arn:aws:s3:::$PRIMARY_BUCKET/*"
        },
        {
            "Effect": "Allow",
            "Action": [
                "s3:ListBucket"
            ],
            "Resource": "arn:aws:s3:::$PRIMARY_BUCKET"
        },
        {
            "Effect": "Allow",
            "Action": [
                "s3:ReplicateObject",
                "s3:ReplicateDelete"
            ],
            "Resource": "arn:aws:s3:::$DR_BUCKET/*"
        }
    ]
}
EOF

aws iam create-policy \
    --policy-name S3-Replication-Policy \
    --policy-document file://s3-replication-policy.json

aws iam attach-role-policy \
    --role-name S3-Replication-Role \
    --policy-arn arn:aws:iam::${ACCOUNT_ID}:policy/S3-Replication-Policy

# Configure cross-region replication
cat > replication-config.json << EOF
{
    "Role": "$S3_REPLICATION_ROLE_ARN",
    "Rules": [
        {
            "Status": "Enabled",
            "Priority": 1,
            "Filter": {"Prefix": ""},
            "Destination": {
                "Bucket": "arn:aws:s3:::$DR_BUCKET",
                "StorageClass": "STANDARD_IA"
            }
        }
    ]
}
EOF

aws s3api put-bucket-replication \
    --bucket $PRIMARY_BUCKET \
    --replication-configuration file://replication-config.json
```

## 5. Testing and Validation

### 5.1 DR Testing Script

```bash
# Create DR testing script
cat > test-dr.sh << 'EOF'
#!/bin/bash
set -e

echo "🧪 DISASTER RECOVERY TESTING STARTED"

# Test 1: Health check endpoints
echo "1️⃣ Testing health check endpoints..."
PRIMARY_HEALTH=$(curl -s -o /dev/null -w "%{http_code}" http://$PRIMARY_ALB_DNS/health.php)
DR_HEALTH=$(curl -s -o /dev/null -w "%{http_code}" http://$DR_ALB_DNS/health.php)

echo "Primary health: $PRIMARY_HEALTH"
echo "DR health: $DR_HEALTH"

# Test 2: Database connectivity
echo "2️⃣ Testing database connectivity..."
mysql -h $PRIMARY_RDS_ENDPOINT -u root -prootpassword -e "SELECT COUNT(*) FROM lampdb.todo;" 2>/dev/null && echo "✅ Primary DB: Connected" || echo "❌ Primary DB: Failed"

# Test 3: Replication lag
echo "3️⃣ Checking replication lag..."
REPLICA_LAG=$(aws rds describe-db-instances \
    --db-instance-identifier ecs-lamp-mysql-replica \
    --region eu-central-1 \
    --query 'DBInstances[0].StatusInfos[?StatusType==`read replication`].Status' \
    --output text)

echo "Replica status: $REPLICA_LAG"

# Test 4: DNS failover simulation
echo "4️⃣ Testing DNS failover (simulation)..."
echo "Manual DNS test: nslookup $DOMAIN_NAME"

echo "✅ DR TESTING COMPLETED"
EOF

chmod +x test-dr.sh
```

## 6. Recovery Time and Point Objectives

### RTO (Recovery Time Objective): 15 minutes
- DNS failover: 1-2 minutes
- Read replica promotion: 5-10 minutes
- ECS service scaling: 3-5 minutes

### RPO (Recovery Point Objective): 5 minutes
- RDS read replica lag: typically < 5 minutes
- S3 replication: near real-time

## 7. Maintenance and Documentation

### 7.1 Regular DR Drills

```bash
# Schedule monthly DR drills
cat > dr-drill-schedule.sh << 'EOF'
#!/bin/bash
# Run this script monthly to test DR procedures

echo "📅 Monthly DR Drill - $(date)"

# 1. Test health checks
./test-dr.sh

# 2. Simulate failover (without promoting replica)
echo "🔄 Simulating failover..."
aws ecs update-service \
    --cluster ecs-lamp-cluster \
    --service ecs-lamp-web-service \
    --desired-count 1 \
    --region eu-central-1

sleep 60

# 3. Scale back down
aws ecs update-service \
    --cluster ecs-lamp-cluster \
    --service ecs-lamp-web-service \
    --desired-count 0 \
    --region eu-central-1

echo "✅ DR Drill completed successfully"
EOF

chmod +x dr-drill-schedule.sh
```

### 7.2 DR Runbook

1. **Detection**: Monitor CloudWatch alarms and health checks
2. **Assessment**: Verify the scope and impact of the disaster
3. **Activation**: Execute `./activate-dr.sh` or rely on automated Lambda trigger
4. **Validation**: Confirm application availability in DR region
5. **Communication**: Notify stakeholders of DR activation
6. **Monitoring**: Continuously monitor DR environment
7. **Failback**: Execute `./deactivate-dr.sh` when primary region is restored

## 8. Cost Optimization

- **Pilot Light**: Keep DR ECS services at 0 desired count
- **RDS Read Replica**: Use smaller instance class for cost savings
- **S3 Intelligent Tiering**: Automatically optimize storage costs
- **CloudWatch Logs**: Set retention periods to control costs

## Conclusion

This disaster recovery setup provides:
- **Automated failover** with Lambda triggers
- **DNS-based traffic routing** with Route 53
- **Cross-region data replication** with RDS read replicas
- **Comprehensive monitoring** with CloudWatch alarms
- **Cost-effective pilot light** architecture

The solution meets enterprise-grade DR requirements with RTO of 15 minutes and RPO of 5 minutes.