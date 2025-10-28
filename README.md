# CCSD Site Search

A PHP/MySQL application that replaces the CCSD search functionality by scraping configured websites and providing a comprehensive search interface. Built specifically for Clark County School District to provide unified search across all CCSD websites.

## Features

- **User Authentication**: Secure login system with rate limiting
- **Website Management**: Admin interface to add and manage websites for scraping
- **Advanced Web Scraping**: Automated content extraction with duplicate prevention and URL normalization
- **Intelligent Search Engine**: Full-text search with CCSD.net domain prioritization
- **Search Interface**: Clean interface matching original CCSD search design
- **Search Analytics**: Track searches and generate usage statistics
- **Content Highlighting**: Search term highlighting in results with intelligent snippet generation

## System Requirements

- **PHP**: 8.0 or higher
- **MySQL**: 5.7 or higher (with FULLTEXT support)
- **Web Server**: Apache or Nginx
- **Memory**: Minimum 512MB PHP memory limit (1GB recommended for large scraping operations)
- **Disk Space**: 1GB+ for content storage (varies by number of scraped pages)

## Installation Instructions

### Step 1: Environment Setup

1. **Create project directory** on your web server:
   ```bash
   cd /var/www/html
   sudo mkdir ccsd-site-search
   cd ccsd-site-search
   ```

2. **Download/Clone** the project files to this directory

3. **Install Composer** (if not already installed):
   ```bash
   curl -sS https://getcomposer.org/installer | php
   ```

4. **Install PHP Dependencies**:
   ```bash
   php composer.phar install
   ```

### Step 2: Database Setup

1. **Create MySQL Database**:
   ```bash
   mysql -u root -p
   ```
   ```sql
   CREATE DATABASE ccsd_search CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   EXIT;
   ```

2. **Import Database Schema**:
   ```bash
   mysql -u root -p ccsd_search < _mysql_dumps/ccsd_search.sql
   ```

3. **Verify Database Creation**:
   ```bash
   mysql -u root -p -e "USE ccsd_search; SHOW TABLES;"
   ```
   You should see 6 tables: users, websites, scraped_pages, search_logs, scrape_queue, rate_limits

### Step 3: Configuration

1. **Create Environment File**:
   ```bash
   cp .env.example .env
   ```

2. **Edit Configuration** (`nano .env`):
   ```env
   DB_HOST=localhost
   DB_NAME=ccsd_search
   DB_USER=root
   DB_PASS=your_mysql_password
   DB_PORT=3306
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-domain.ccsd.net
   ```

3. **Set File Permissions**:
   ```bash
   sudo chown -R www-data:www-data /var/www/html/ccsd-site-search
   sudo chmod -R 755 /var/www/html/ccsd-site-search
   sudo chmod 600 .env
   ```

### Step 4: Web Server Configuration

#### For Apache:
1. **Create Virtual Host** (`/etc/apache2/sites-available/ccsd-search.conf`):
   ```apache
   <VirtualHost *:80>
       ServerName search.ccsd.net
       DocumentRoot /var/www/html/ccsd-site-search
       
       <Directory /var/www/html/ccsd-site-search>
           AllowOverride All
           Require all granted
       </Directory>
       
       ErrorLog ${APACHE_LOG_DIR}/ccsd-search_error.log
       CustomLog ${APACHE_LOG_DIR}/ccsd-search_access.log combined
   </VirtualHost>
   ```

2. **Enable Site and Rewrite Module**:
   ```bash
   sudo a2ensite ccsd-search
   sudo a2enmod rewrite
   sudo systemctl reload apache2
   ```

#### For Nginx:
1. **Create Server Block** (`/etc/nginx/sites-available/ccsd-search`):
   ```nginx
   server {
       listen 80;
       server_name search.ccsd.net;
       root /var/www/html/ccsd-site-search;
       index index.php index.html;

       location / {
           try_files $uri $uri/ =404;
       }

       location ~ \.php$ {
           include snippets/fastcgi-php.conf;
           fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
       }

       location ~ /\.env {
           deny all;
       }
   }
   ```

2. **Enable Site**:
   ```bash
   sudo ln -s /etc/nginx/sites-available/ccsd-search /etc/nginx/sites-enabled/
   sudo systemctl reload nginx
   ```

### Step 5: Initial Setup

1. **Test Installation**: Navigate to your domain in a web browser

2. **Login to Admin Panel**:
   - URL: `https://your-domain.ccsd.net/login.php`
   - Default credentials: `admin` / `admin123`
   - **IMPORTANT**: Change these credentials immediately after first login

3. **Add Initial Websites** via the admin panel (`admin.php`):
   - **CCSD Main**: https://ccsd.net (Depth: 50, Frequency: Unlimited)
   - **Transportation**: https://transportation.ccsd.net (Depth: 10)
   - **Engagement**: https://engage.ccsd.net (Depth: 10)
   - **Facilities**: https://facilities.ccsd.net (Depth: 10)
   - **Special Services**: https://ssd.ccsd.net (Depth: 10)
   - Add other CCSD subdomain sites as needed

### Step 6: Initial Content Scraping

1. **Start Comprehensive Scraping**:
   ```bash
   cd /var/www/html/ccsd-site-search
   php comprehensive_scrape.php > initial_scrape.log 2>&1 &
   ```

2. **Monitor Progress**:
   ```bash
   tail -f initial_scrape.log
   ```

3. **Verify Content**: Check admin panel for scraped page counts

## Automated Maintenance (Production Setup)

### Cron Jobs for Regular Content Updates

1. **Create Cron Script** (`/var/www/html/ccsd-site-search/cron-scrape.sh`):
   ```bash
   #!/bin/bash
   cd /var/www/html/ccsd-site-search
   php comprehensive_scrape.php >> logs/cron-scrape.log 2>&1
   ```

2. **Make Executable**:
   ```bash
   chmod +x cron-scrape.sh
   mkdir logs
   ```

3. **Add to Crontab** (`sudo crontab -e`):
   ```bash
   # Update CCSD content daily at 2 AM
   0 2 * * * /var/www/html/ccsd-site-search/cron-scrape.sh
   
   # Clean old search logs weekly
   0 3 * * 0 /usr/bin/mysql -u root -pYOUR_PASSWORD ccsd_search -e "DELETE FROM search_logs WHERE searched_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
   ```

## Usage

### Admin Interface (`admin.php`)

**Website Management:**
- Add new CCSD websites to scrape
- Configure scraping depth (how many levels deep to crawl)
- Set scraping frequency (unlimited recommended for CCSD.net)
- Monitor website status and page counts
- Use "Scrape All" to update all websites

**Important Notes:**
- Individual "Scrape" buttons are disabled for stability
- Use "Scrape All" for bulk content updates
- CCSD.net requires depth of 50+ for comprehensive coverage

### Search Interface

- **Main Search**: `index.php` - Homepage with search box
- **Search Results**: `search.php?addsearch=query` - Results page matching original CCSD design
- **Features**: 
  - CCSD.net results appear first (domain prioritization)
  - Highlighted search terms in content
  - Intelligent content snippets
  - Clean, familiar interface

## Database Schema

- `users` - User accounts and authentication
- `websites` - Configured websites for scraping
- `scraped_pages` - Extracted page content with full-text search
- `search_logs` - Search analytics and logging
- `scrape_queue` - Background scraping queue
- `rate_limits` - Login rate limiting

## Security Features

- Password hashing with PHP's `password_hash()`
- Rate limiting on login attempts (5 attempts, 15-minute lockout)
- SQL injection prevention with prepared statements
- XSS protection with proper output escaping
- CSRF protection considerations
- Secure headers via `.htaccess`

## API/Classes

- `Database` - PDO database wrapper with prepared statements
- `Auth` - User authentication and session management
- `RateLimiter` - Login attempt rate limiting
- `Scraper` - Web scraping and content extraction
- `SearchEngine` - Full-text search functionality

## Configuration

### Environment Variables (.env)
```
DB_HOST=localhost
DB_NAME=ccsd_search
DB_USER=root
DB_PASS=your_password
DB_PORT=3306
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost
```

### Scraping Settings
- **Max Depth**: How many levels deep to crawl (default: 3)
- **Frequency**: Seconds between re-scraping (default: 3600)
- **Timeout**: HTTP request timeout (default: 30 seconds)

## Monitoring and Maintenance

### Regular Administrative Tasks

1. **Content Monitoring**:
   - Check admin panel daily for scraping status
   - Monitor page counts for significant changes
   - Review failed scrapes and resolve issues

2. **Performance Monitoring**:
   - Monitor search response times
   - Check database size and optimize if needed
   - Review server logs for errors

3. **Content Quality**:
   - Test search functionality with common queries
   - Verify CCSD.net content is up-to-date
   - Check for duplicate content or broken links

### Database Maintenance

```bash
# Optimize database tables monthly
mysql -u root -p ccsd_search -e "OPTIMIZE TABLE scraped_pages, search_logs;"

# Check database size
mysql -u root -p ccsd_search -e "SELECT table_name, ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)' FROM information_schema.tables WHERE table_schema = 'ccsd_search';"

# Backup database
mysqldump -u root -p ccsd_search > backup_$(date +%Y%m%d).sql
```

## Troubleshooting

### Common Issues

1. **Database Connection Errors**: 
   - Check `.env` file credentials
   - Verify MySQL service is running: `sudo systemctl status mysql`
   - Test connection: `mysql -u root -p ccsd_search`

2. **Scraping Issues**:
   - **Timeouts**: Increase timeout in `Scraper.php` (currently 60 seconds)
   - **Memory Issues**: Increase PHP memory limit in `php.ini`
   - **403 Errors**: Some CCSD pages block certain requests (normal)
   - **Duplicate Errors**: Run duplicate cleanup scripts if needed

3. **Search Problems**:
   - **No Results**: Verify FULLTEXT indexes exist and content is scraped
   - **Slow Search**: Check database optimization and indexes
   - **Wrong Results**: CCSD.net should appear first (check SearchEngine.php)

4. **Admin Panel Issues**:
   - **Login Fails**: Check rate limiting: `SELECT * FROM rate_limits;`
   - **Scraping Disabled**: Individual scrape buttons are intentionally commented out
   - **Permission Errors**: Check file ownership and web server permissions

### Debug Commands

```bash
# Check scraped content count
mysql -u root -p ccsd_search -e "SELECT w.name, COUNT(sp.id) as pages FROM websites w LEFT JOIN scraped_pages sp ON w.id = sp.website_id GROUP BY w.id;"

# Test search functionality
mysql -u root -p ccsd_search -e "SELECT COUNT(*) FROM scraped_pages WHERE MATCH(title, content) AGAINST('test' IN BOOLEAN MODE);"

# Check for errors in logs
tail -f /var/log/apache2/ccsd-search_error.log

# Monitor scraping progress
tail -f logs/cron-scrape.log
```

### Performance Optimization

```bash
# Add MySQL indexes if missing
mysql -u root -p ccsd_search -e "SHOW INDEX FROM scraped_pages;"

# Check MySQL configuration
mysql -u root -p -e "SHOW VARIABLES LIKE 'ft_min_word_len';"

# PHP memory and execution time (add to php.ini)
memory_limit = 1G
max_execution_time = 3600
```

## Support and Contact

For technical support or questions about this CCSD Search implementation:

- **IT Department**: Contact CCSD IT Services
- **Documentation**: This README file
- **Logs**: Check `/var/log/apache2/` and application `logs/` directory

## Security and Compliance

- **CCSD Internal Use Only**: This system is designed specifically for Clark County School District
- **Data Privacy**: All scraped content is from public CCSD websites
- **Access Control**: Admin access required for management functions
- **Regular Updates**: Keep system updated with latest security patches

## License

This project is proprietary software developed for Clark County School District internal use. All CCSD website content remains property of CCSD. Ensure compliance with district policies when deploying and maintaining this system.