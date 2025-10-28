<?php
require_once 'vendor/autoload.php';

use CCSD\Search\Scraper;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$scraper = new Scraper();

echo "Testing scrape of ccsd.net...\n";
$scraper->scrapeWebsite(1); // ccsd.net website ID is 1

echo "Test completed.\n";
?>