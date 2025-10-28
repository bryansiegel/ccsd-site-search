-- Add unique constraint to prevent duplicate URLs per website
-- This should be run after cleaning up existing duplicates

ALTER TABLE scraped_pages 
ADD CONSTRAINT unique_website_url 
UNIQUE (website_id, url(191));