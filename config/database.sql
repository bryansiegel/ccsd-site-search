-- CCSD Search Application Database Schema

CREATE DATABASE IF NOT EXISTS ccsd_search;
USE ccsd_search;

-- Users table for authentication
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Websites to scrape
CREATE TABLE websites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    url VARCHAR(500) NOT NULL,
    base_domain VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive', 'error') DEFAULT 'active',
    last_scraped TIMESTAMP NULL,
    scrape_frequency INT DEFAULT 3600, -- seconds between scrapes
    max_depth INT DEFAULT 3,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_base_domain (base_domain),
    INDEX idx_status (status)
);

-- Scraped pages content
CREATE TABLE scraped_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    website_id INT NOT NULL,
    url VARCHAR(1000) NOT NULL,
    title VARCHAR(500),
    content LONGTEXT,
    meta_description TEXT,
    keywords TEXT,
    h1_tags TEXT,
    h2_tags TEXT,
    status_code INT DEFAULT 200,
    content_hash VARCHAR(64), -- SHA256 hash to detect changes
    scraped_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE,
    UNIQUE KEY unique_page (website_id, url),
    INDEX idx_website_id (website_id),
    INDEX idx_title (title),
    INDEX idx_scraped_at (scraped_at),
    FULLTEXT idx_content_search (title, content, meta_description, keywords)
);

-- Search analytics
CREATE TABLE search_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    query VARCHAR(500) NOT NULL,
    results_count INT DEFAULT 0,
    ip_address VARCHAR(45),
    user_agent TEXT,
    searched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_query (query),
    INDEX idx_searched_at (searched_at)
);

-- Scraping queue for background processing
CREATE TABLE scrape_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    website_id INT NOT NULL,
    url VARCHAR(1000) NOT NULL,
    priority INT DEFAULT 5, -- 1-10, 10 = highest priority
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    error_message TEXT,
    scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE,
    INDEX idx_status_priority (status, priority),
    INDEX idx_scheduled_at (scheduled_at)
);

-- Insert default admin user (password: admin123 - should be changed)
INSERT INTO users (username, email, password_hash, role) VALUES 
('admin', 'admin@ccsd.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');