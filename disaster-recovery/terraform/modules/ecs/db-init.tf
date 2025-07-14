# CloudWatch Log Group for DB Init Task
resource "aws_cloudwatch_log_group" "db_init" {
  name              = "/ecs/${var.project_name}-db-init"
  retention_in_days = 7

  tags = var.tags
}

# ECS Task Definition for Database Initialization
resource "aws_ecs_task_definition" "db_init" {
  family                   = "${var.project_name}-db-init"
  network_mode             = "awsvpc"
  requires_compatibilities = ["FARGATE"]
  cpu                      = "256"
  memory                   = "512"
  execution_role_arn       = aws_iam_role.ecs_task_execution.arn

  container_definitions = jsonencode([
    {
      name      = "mysql-client"
      image     = "mysql:8.0"
      essential = true

      command = [
        "sh", "-c",
        "sleep 120 && mysql -h ${replace(var.rds_endpoint, ":3306", "")} -P 3306 -u ${var.db_username} -p${var.db_password} -e 'CREATE DATABASE IF NOT EXISTS ${var.db_name}; USE ${var.db_name}; CREATE TABLE IF NOT EXISTS todo (id INT AUTO_INCREMENT PRIMARY KEY, task VARCHAR(255) NOT NULL, status ENUM(\"Pending\",\"In Progress\", \"Completed\") DEFAULT \"Pending\", created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP); INSERT INTO todo (task, status) VALUES (\"Welcome to TaskFlow on RDS!\", \"Completed\"), (\"Test RDS connectivity\", \"In Progress\"), (\"Implement disaster recovery\", \"Pending\");' && echo 'Database initialized successfully'"
      ]

      logConfiguration = {
        logDriver = "awslogs"
        options = {
          "awslogs-group"         = aws_cloudwatch_log_group.db_init.name
          "awslogs-region"        = var.aws_region
          "awslogs-stream-prefix" = "ecs"
        }
      }
    }
  ])

  tags = var.tags
}

# Null resource to run the database initialization task
resource "null_resource" "db_init" {
  depends_on = [
    aws_ecs_cluster.main,
    aws_ecs_task_definition.db_init
  ]

  provisioner "local-exec" {
    command = <<-EOT
      aws ecs run-task \
        --cluster ${aws_ecs_cluster.main.name} \
        --task-definition ${aws_ecs_task_definition.db_init.arn} \
        --launch-type FARGATE \
        --network-configuration "awsvpcConfiguration={subnets=[${join(",", var.private_subnet_ids)}],securityGroups=[${var.ecs_security_group_id}]}" \
        --region ${var.aws_region}
    EOT
  }

  triggers = {
    rds_endpoint = var.rds_endpoint
  }
}