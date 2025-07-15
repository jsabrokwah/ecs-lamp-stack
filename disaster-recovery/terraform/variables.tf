variable "aws_region" {
  description = "AWS region"
  type        = string
  default     = "eu-central-1"
}

variable "project_name" {
  description = "Name of the project"
  type        = string
  default     = "ecs-lamp"
}

variable "vpc_cidr" {
  description = "CIDR block for VPC"
  type        = string
  default     = "10.1.0.0/16"
}

variable "source_db_identifier" {
  description = "Source DB identifier for read replica"
  type        = string
  default     = "arn:aws:rds:eu-west-1:123456789012:db:ecs-lamp-mysql-primary"
}

variable "db_instance_class" {
  description = "RDS instance class"
  type        = string
  default     = "db.t3.micro"
}

variable "db_name" {
  description = "Database name"
  type        = string
  default     = "lampdb"
}

variable "db_username" {
  description = "Database username"
  type        = string
  default     = "root"
}

variable "db_password" {
  description = "Database password"
  type        = string
  sensitive   = true
  default     = "rootpassword"
}

variable "tags" {
  description = "A map of tags to assign to the resource"
  type        = map(string)
  default = {
    Environment = "dev"
    Project     = "ecs-lamp-stack"
    ManagedBy   = "terraform"
  }
}