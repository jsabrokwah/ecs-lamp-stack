
# Data sources
data "aws_caller_identity" "current" {}
data "aws_availability_zones" "available" {
  state = "available"
}

# VPC Module
module "vpc" {
  source = "./modules/vpc"
  
  project_name = var.project_name
  vpc_cidr     = var.vpc_cidr
  azs          = slice(data.aws_availability_zones.available.names, 0, 2)
  
  tags = var.tags
}

# Security Groups Module
module "security" {
  source = "./modules/security"
  
  project_name = var.project_name
  vpc_id       = module.vpc.vpc_id
  
  tags = var.tags
}

# RDS Module
module "rds" {
  source = "./modules/rds"
  
  project_name        = var.project_name
  db_instance_class   = var.db_instance_class
  db_name            = var.db_name
  db_username        = var.db_username
  db_password        = var.db_password
  private_subnet_ids = module.vpc.private_subnet_ids
  rds_security_group_id = module.security.rds_security_group_id
  
  tags = var.tags
}

# ECR Module
module "ecr" {
  source = "./modules/ecr"
  
  project_name = var.project_name
  
  tags = var.tags
}

# ECS Module
module "ecs" {
  source = "./modules/ecs"
  
  project_name           = var.project_name
  account_id            = data.aws_caller_identity.current.account_id
  aws_region            = var.aws_region
  vpc_id                = module.vpc.vpc_id
  private_subnet_ids    = module.vpc.private_subnet_ids
  public_subnet_ids     = module.vpc.public_subnet_ids
  alb_security_group_id = module.security.alb_security_group_id
  ecs_security_group_id = module.security.ecs_security_group_id
  ecr_repository_url    = module.ecr.repository_url
  rds_endpoint          = module.rds.rds_endpoint
  db_name               = var.db_name
  db_username           = var.db_username
  db_password           = var.db_password
  
  tags = var.tags
}