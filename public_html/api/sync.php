<?php
/**
 * Removed endpoint placeholder
 * Offline sync functionality has been removed as part of backend modernization.
 * All business data must be stored in MySQL via the PHP REST API.
 */

require_once __DIR__ . '/../config_loader.php';
require_once __DIR__ . '/response.php';

Response::error('Endpoint removed: offline sync has been removed. Use API endpoints under /api/* for all data operations.', [], 410);
