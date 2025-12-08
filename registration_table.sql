-- Registration table to store all registration data
CREATE TABLE IF NOT EXISTS registration (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registration_type ENUM('individual', 'entity') NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    date_of_birth DATE NOT NULL,
    pan_card_number VARCHAR(10) NOT NULL,
    email VARCHAR(100) NOT NULL,
    mobile VARCHAR(10) NOT NULL,
    pan_verification_data TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_pan (pan_card_number),
    INDEX idx_mobile (mobile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add password_reset_token column to users table for password reset functionality
-- Note: If columns already exist, you may get an error. In that case, comment out the ALTER TABLE statement.
ALTER TABLE users 
ADD COLUMN password_reset_token VARCHAR(255) NULL,
ADD COLUMN password_reset_expires DATETIME NULL;

-- Create index on password_reset_token (skip if already exists)
CREATE INDEX idx_reset_token ON users (password_reset_token);

