<?php
/**
 * Settings Helper
 * Retrieves settings from database
 */

/**
 * Get a setting value from the database
 * 
 * @param PDO $pdo Database connection
 * @param string $key Setting key
 * @param mixed $default Default value if not found
 * @return mixed Setting value or default
 */
function get_setting(PDO $pdo, string $key, $default = null) {
    try {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = ? LIMIT 1");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['value'] !== null) {
            return $result['value'];
        }
        
        return $default;
    } catch (Exception $e) {
        error_log("Error getting setting {$key}: " . $e->getMessage());
        return $default;
    }
}

/**
 * Get multiple settings at once
 * 
 * @param PDO $pdo Database connection
 * @param array $keys Array of setting keys
 * @return array Associative array of key => value
 */
function get_settings(PDO $pdo, array $keys): array {
    if (empty($keys)) {
        return [];
    }
    
    try {
        $placeholders = str_repeat('?,', count($keys) - 1) . '?';
        $stmt = $pdo->prepare("SELECT `key`, value FROM settings WHERE `key` IN ({$placeholders})");
        $stmt->execute($keys);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['key']] = $row['value'];
        }
        
        return $settings;
    } catch (Exception $e) {
        error_log("Error getting settings: " . $e->getMessage());
        return [];
    }
}

/**
 * Set a setting value
 * 
 * @param PDO $pdo Database connection
 * @param string $key Setting key
 * @param mixed $value Setting value
 * @return bool Success status
 */
function set_setting(PDO $pdo, string $key, $value): bool {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO settings (`key`, `value`) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE `value` = ?
        ");
        $valStr = is_scalar($value) ? (string)$value : json_encode($value);
        $stmt->execute([$key, $valStr, $valStr]);
        return true;
    } catch (Exception $e) {
        error_log("Error setting {$key}: " . $e->getMessage());
        return false;
    }
}





