# CCSD Site Search

A PHP/MySQL application that replicates the CCSD search functionality by scraping configured websites and providing a search interface that matches the original CCSD design.

## Features

- **User Authentication**: Secure login system with rate limiting
- **Website Management**: Admin interface to add and manage websites for scraping
- **Web Scraping**: Automated content extraction from configured websites
- **Search Engine**: Full-text search with MySQL FULLTEXT indexes
- **Search Interface**: Clean interface matching CCSD design patterns
- **Search Analytics**: Track searches and generate usage statistics

## Installation

1. **Clone/Download** the project to your web server directory

2. **Install Dependencies**:
   ```bash
   php composer.phar install
   ```

3. **Configure Environment**:
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials
   ```

4. **Create Database**:
   ```bash
   mysql -u root -p < config/database.sql
   ```

5. **Set Permissions**:
   - Ensure the web server can read/write to the application directory
   - Make sure `.env` file is not publicly accessible

## Usage

### Admin Interface

1. **Login**: Navigate to `login.php`
   - Default credentials: `admin` / `admin123` (change immediately)

2. **Add Websites**: Use the admin interface at `admin.php` to:
   - Add websites to scrape
   - Configure scraping depth and frequency
   - Monitor scraping status

3. **Run Scraper**: 
   ```bash
   # Scrape a specific website
   php scrape.php [website_id]
   
   # Process scrape queue
   php scrape.php
   ```

### Search Interface

- **Homepage**: `index.php` - Main search interface
- **Search Results**: `search.php?addsearch=query` - Results page

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

## Maintenance

### Regular Tasks
1. **Monitor Scrape Queue**: Check `/admin.php` for failed scrapes
2. **Update Content**: Run scraper regularly to keep content fresh
3. **Review Analytics**: Monitor search patterns and popular queries
4. **Clean Logs**: Periodically clean old search logs and rate limit records

### Cron Jobs (Recommended)
```bash
# Run scraper every hour
0 * * * * /usr/bin/php /path/to/ccsd-site-search/scrape.php

# Clean old logs daily
0 2 * * * /usr/bin/mysql -u user -p database -e "DELETE FROM search_logs WHERE searched_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
```

## Troubleshooting

### Common Issues

1. **Database Connection Errors**: Check `.env` credentials and MySQL service
2. **Scraping Timeouts**: Increase timeout in `Scraper.php` or check target website
3. **Search Not Working**: Verify FULLTEXT indexes are created
4. **Login Issues**: Check rate limiting table and clear if needed

### Debug Mode
Set `APP_DEBUG=true` in `.env` to enable detailed error reporting.

## License

This project is for CCSD internal use. Ensure compliance with website terms of service when scraping external sites.