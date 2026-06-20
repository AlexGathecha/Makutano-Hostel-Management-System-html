-- Create the database
CREATE DATABASE makutano_db CHARACTER SET utf8 COLLATE utf8_general_ci;

-- Create a user for the project
CREATE USER 'makutano_user'@'localhost' IDENTIFIED BY 'makutano2026';

-- Give the user access to the database
GRANT ALL PRIVILEGES ON makutano_db.* TO 'makutano_user'@'localhost';

FLUSH PRIVILEGES;

-- Switch to the database
USE makutano_db;

-- Create the users table
CREATE TABLE IF NOT EXISTS users (
  id         INT          AUTO_INCREMENT PRIMARY KEY,
  username   VARCHAR(100) NOT NULL,
  email      VARCHAR(150) NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  role       ENUM('admin', 'staff', 'tenant') NOT NULL DEFAULT 'tenant',
  created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin account (password: admin123)
INSERT INTO users (username, email, password, role) VALUES (
  'admin',
  'admin@makutano.com',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'admin'
);

-- Exit MySQL
EXIT;