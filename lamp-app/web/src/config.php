<?php
    $host = getenv('DB_HOST') ?: 'localhost';
    $dbname = getenv('DB_NAME') ?: 'lampdb';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: 'rootpassword';
    
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
