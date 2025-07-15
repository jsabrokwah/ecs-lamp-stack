# Import existing DR DB Subnet Group
resource "aws_db_subnet_group" "main" {
  name       = "ecs-lamp-dr-db-subnet-group"
  subnet_ids = var.private_subnet_ids

  tags = merge(var.tags, {
    Name = "ecs-lamp-dr-db-subnet-group"
  })

  lifecycle {
    ignore_changes = [subnet_ids]
  }
}

# Import existing RDS Read Replica
resource "aws_db_instance" "mysql" {
  identifier = "ecs-lamp-mysql-replica"

  # Read replica configuration
  replicate_source_db = var.source_db_identifier
  instance_class      = var.db_instance_class

  vpc_security_group_ids = [var.rds_security_group_id]
  db_subnet_group_name   = aws_db_subnet_group.main.name

  storage_encrypted   = true
  publicly_accessible = false

  skip_final_snapshot = true
  deletion_protection = false

  tags = merge(var.tags, {
    Name = "ecs-lamp-mysql-replica"
  })

  lifecycle {
    ignore_changes = [
      allocated_storage,
      engine_version,
      backup_retention_period,
      backup_window,
      maintenance_window
    ]
  }
}