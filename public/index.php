<?php

declare(strict_types=1);

/**
 * Phoenix Dashboard — Entry Point
 *
 * Usage: php -S localhost:8080 -t public/
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Phoenix\Dashboard\DashboardController;
use Phoenix\Storage\ErrorStore;
use Phoenix\Storage\FixHistory;
use Phoenix\Patcher\BackupManager;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$config = require __DIR__ . '/../config/phoenix.php';

// Initialize components
$errorStore = new ErrorStore($config['storage']['db_path']);
$history = new FixHistory($config['storage']['db_path']);
$backupManager = new BackupManager($config['patcher']['backup_dir']);

// Route the request
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($path === '/api/stats') {
    header('Content-Type: application/json');
    echo json_encode($errorStore->getStats());
    exit;
}

if ($path === '/api/errors') {
    header('Content-Type: application/json');
    echo json_encode($errorStore->getRecentErrors((int) ($_GET['limit'] ?? 50)));
    exit;
}

if ($path === '/api/history') {
    header('Content-Type: application/json');
    echo json_encode($history->getRecent((int) ($_GET['limit'] ?? 50)));
    exit;
}

// Default: render dashboard
$dashboard = new DashboardController($errorStore, $history, $backupManager);
echo $dashboard->render();
