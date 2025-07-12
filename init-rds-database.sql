-- Initialize RDS MySQL Database for TaskFlow Application
-- Run this after RDS instance is available

USE lampdb;

CREATE TABLE IF NOT EXISTS todo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task VARCHAR(255) NOT NULL,
    status ENUM('Pending','In Progress', 'Completed') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample data (optional)
INSERT INTO todo (task, status) VALUES 
('Welcome to TaskFlow on RDS!', 'Completed'),
('Test RDS connectivity', 'In Progress'),
('Implement disaster recovery', 'Pending');