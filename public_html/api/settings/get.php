<?php
/**
 * Settings API - Get
 * 
 * GET /api/settings/get.php?key=clinic_name
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../auth/middleware.php';

handleCors();
requireMethod('GET');

$user = requireAuth();

$key = getParam('key', '');

try {
    $db = Database::getInstance();
    
    if ($key) {
        $stmt = $db->prepare('SELECT setting_key, setting_value, setting_group FROM site_settings WHERE setting_key = :key');
        $stmt->execute([':key' => $key]);
        $setting = $stmt->fetch();
        
        if (!$setting) {
            errorResponse('Setting not found', 404);
        }
        
        $setting['setting_value'] = json_decode($setting['setting_value'], true);
        successResponse($setting);
    } else {
        $stmt = $db->query('SELECT setting_key, setting_value, setting_group FROM site_settings ORDER BY setting_group, setting_key');
        $settings = $stmt->fetchAll();
        
        foreach ($settings as &$s) {
            $s['setting_value'] = json_decode($s['setting_value'], true);
        }
        
        successResponse($settings);
    }
} catch (\Exception $e) {
    error_log('Get settings error: ' . $e->getMessage());
    errorResponse('Failed to fetch settings', 500);
}
