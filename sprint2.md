# 🧱 Core Components

## 1. ECS (Elastic Container Service)

Since your LAMP app is containerized and running in ECS:

- **Backup ECS Task Definitions and Services**:
  - Store ECS task definitions and service configurations in version-controlled IaC.
  - Regularly export and update the latest task definitions in the DR region.

- **Pilot Light in DR Region**:
  - Set up ECS Cluster with minimal resources.
  - Keep tasks stopped or with 0 desired count to avoid costs.

- **Failover Plan**:
  - Use scripts or CloudFormation to scale up services (desired count > 0) during DR.
  - Ensure ECS Task Roles and IAM permissions are also mirrored in the DR region.

---

## 2. RDS (Relational Database Service)

- **Cross-Region Read Replica**:
  - Set up a MySQL or MariaDB read replica of your LAMP DB in the DR region.
  - Ensure replication lag is monitored.

- **Failover Procedure**:
  - On disaster, promote read replica to a standalone primary in DR region.
  - Update ECS tasks/env vars to point to the new RDS endpoint.

---

## 3. S3 (Static Assets, Backups, Media (If Applicable))

- **Cross-Region Replication (CRR)**:
  - Enable CRR for all S3 buckets used by the app (e.g., image uploads, logs, backups).
  - Use versioning to protect against overwrites or deletions.

- **Lifecycle Rules**:
  - Implement policies to transition older versions to Glacier or delete after retention period.

---

## 4. Lambda Functions (If Applicable)

If any part of your LAMP app uses Lambda (e.g., triggers, notifications):

- **Deploy Copies in DR Region**:
  - Maintain copies of the Lambda functions in DR (disabled by default).
  - Sync function code and configurations (env vars via Parameter Store or Secrets Manager).

- **Enable on DR Activation**:
  - Enable these functions and event sources (e.g., S3 triggers) upon failover.

---

## 5. Networking (VPC, Subnets, Load Balancer)

- **VPC Setup**:
  - Mirror the production VPC setup (subnets, security groups, route tables) in the DR region.
  - Automate via IaC for consistency.

- **Load Balancer & DNS**:
  - Pre-create an Application Load Balancer (ALB) in DR.
  - Use Route 53 with failover routing or manual DNS cutover when activating DR.

---

# 🚨 Disaster Recovery Process

1. **Disaster Trigger Detected**
   - Monitoring via CloudWatch or manual detection.

2. **Activate Pilot Light**
   - ECS: Scale services from 0 → desired count using IaC or AWS CLI.
   - RDS: Promote read replica.
   - Lambda: Enable functions and triggers.
   - Load Balancer: Attach ECS services and update target groups.

3. **DNS Failover**
   - Use Route 53 to switch to DR region’s ALB endpoint.

4. **Data Validation**
   - Ensure RDS and S3 data integrity.
   - Test LAMP app for availability and performance.

---

# 📋 Maintenance & Monitoring

- Regular Backup of Task Definitions and DB Snapshots  
- Monitor ECS, RDS, and Lambda Health with CloudWatch

---

# 💰 Cost Management

- Keep ECS services scaled to 0  
- Use T3 or burstable EC2 instances for pilot light if needed  
- Cross-region traffic is billable – optimize replication intervals if cost is a concern

---

# 📘 Documentation & Submission

- **Architecture Diagram**:
  - ECS clusters, RDS, S3, Lambda, and networking in both regions.
  - Indicate data flows and failover paths.

- **IaC Code**:
  - Store CloudFormation or Terraform in GitHub.
  - Include README with deployment instructions and DR trigger steps.

- **Demonstration**:
  - Walk through the failover and recovery process.
  - Optionally host a basic LAMP web page as proof-of-concept.
