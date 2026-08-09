<?php
/**
 * Removed endpoint: setup-test
 * Development/test endpoint removed during modernization.
 * Use the documented API endpoints instead. This endpoint returns 410 Gone.
 */

require_once __DIR__ . '/../config_loader.php';
require_once __DIR__ . '/response.php';

Response::error('Endpoint removed: setup-test is deprecated and removed. Use production API endpoints.', [], 410);
