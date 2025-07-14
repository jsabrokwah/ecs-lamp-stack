output "repository_url" {
  description = "URL of the ECR repository"
  value       = aws_ecr_repository.web.repository_url
}

output "repository_name" {
  description = "Name of the ECR repository"
  value       = aws_ecr_repository.web.name
}

output "registry_id" {
  description = "Registry ID of the ECR repository"
  value       = aws_ecr_repository.web.registry_id
}