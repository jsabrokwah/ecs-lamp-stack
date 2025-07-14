output "rds_endpoint" {
  description = "RDS instance endpoint"
  value       = aws_db_instance.mysql.endpoint
}

output "rds_port" {
  description = "RDS instance port"
  value       = aws_db_instance.mysql.port
}

output "db_instance_id" {
  description = "RDS instance ID"
  value       = aws_db_instance.mysql.id
}