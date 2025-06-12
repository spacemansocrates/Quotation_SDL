<?php
/**
 * SettingsManager Class
 * 
 * Handles retrieval and management of company settings from the database.
 * Provides convenient methods for getting application defaults, particularly
 * for invoice-related settings like VAT percentage, PPDA levy, etc.
 */

// Uncomment the line below if not using autoloading
// require_once 'Database.php';

class SettingsManager {
    private $conn;
    private $db;
    private $cache; // Simple in-memory cache for settings during request lifecycle

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->connect();
        $this->cache = [];
    }

    /**
     * Get a single setting value by key
     * 
     * @param string $key The setting key to retrieve
     * @return string|null The setting value or null if not found
     */
    public function getSetting($key) {
        if (!$this->conn) {
            error_log("SettingsManager: Database connection failed");
            return null;
        }

        // Check cache first
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        try {
            $stmt = $this->conn->prepare("SELECT setting_value FROM company_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $value = $result ? $result['setting_value'] : null;
            
            // Cache the result
            $this->cache[$key] = $value;
            
            return $value;
        } catch (PDOException $e) {
            error_log("SettingsManager: Error retrieving setting '$key': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get multiple settings at once
     * 
     * @param array $keys Array of setting keys to retrieve
     * @return array Associative array of key => value pairs
     */
    public function getSettings($keys) {
        if (!$this->conn || empty($keys)) {
            return [];
        }

        $results = [];
        $uncached_keys = [];

        // Check cache first
        foreach ($keys as $key) {
            if (isset($this->cache[$key])) {
                $results[$key] = $this->cache[$key];
            } else {
                $uncached_keys[] = $key;
            }
        }

        // Fetch uncached settings from database
        if (!empty($uncached_keys)) {
            try {
                $placeholders = str_repeat('?,', count($uncached_keys) - 1) . '?';
                $stmt = $this->conn->prepare("SELECT setting_key, setting_value FROM company_settings WHERE setting_key IN ($placeholders)");
                $stmt->execute($uncached_keys);
                $db_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($db_results as $row) {
                    $results[$row['setting_key']] = $row['setting_value'];
                    $this->cache[$row['setting_key']] = $row['setting_value'];
                }

                // Set null for keys not found in database
                foreach ($uncached_keys as $key) {
                    if (!isset($results[$key])) {
                        $results[$key] = null;
                        $this->cache[$key] = null;
                    }
                }
            } catch (PDOException $e) {
                error_log("SettingsManager: Error retrieving multiple settings: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Get all relevant invoice defaults
     * Returns an array with default values for invoice creation
     * 
     * @return array Associative array of invoice defaults
     */
    public function getInvoiceDefaults() {
        $default_keys = [
            'default_invoice_vat_percentage',
            'default_invoice_ppda_levy_percentage', 
            'default_invoice_apply_ppda_levy',
            'default_invoice_payment_terms',
            'default_invoice_delivery_period',
            'company_tpin'
        ];

        $settings = $this->getSettings($default_keys);

        return [
            'vat_percentage' => $settings['default_invoice_vat_percentage'] ?: '16.50',
            'ppda_levy_percentage' => $settings['default_invoice_ppda_levy_percentage'] ?: '1.00',
            'apply_ppda_levy' => (bool)($settings['default_invoice_apply_ppda_levy'] ?: 0),
            'payment_terms' => $settings['default_invoice_payment_terms'] ?: null,
            'delivery_period' => $settings['default_invoice_delivery_period'] ?: null,
            'company_tpin' => $settings['company_tpin'] ?: null
        ];
    }

    /**
     * Get quotation defaults (if different from invoice defaults)
     * 
     * @return array Associative array of quotation defaults
     */
    public function getQuotationDefaults() {
        $default_keys = [
            'default_quotation_vat_percentage',
            'default_quotation_ppda_levy_percentage',
            'default_quotation_apply_ppda_levy',
            'default_quotation_validity_days'
        ];

        $settings = $this->getSettings($default_keys);

        return [
            'vat_percentage' => $settings['default_quotation_vat_percentage'] ?: '16.50',
            'ppda_levy_percentage' => $settings['default_quotation_ppda_levy_percentage'] ?: '1.00',
            'apply_ppda_levy' => (bool)($settings['default_quotation_apply_ppda_levy'] ?: 0),
            'validity_days' => $settings['default_quotation_validity_days'] ?: 30
        ];
    }

    /**
     * Get company information settings
     * 
     * @return array Company details
     */
    public function getCompanyInfo() {
        $company_keys = [
            'company_name',
            'company_address',
            'company_phone',
            'company_email',
            'company_tpin',
            'company_logo_path'
        ];

        return $this->getSettings($company_keys);
    }

    /**
     * Set a single setting value
     * 
     * @param string $key Setting key
     * @param string $value Setting value
     * @param int|null $user_id User ID for audit trail (if applicable)
     * @return bool Success status
     */
    public function setSetting($key, $value, $user_id = null) {
        if (!$this->conn) {
            return false;
        }

        try {
            $stmt = $this->conn->prepare("
                INSERT INTO company_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value),
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$key, $value]);

            // Update cache
            $this->cache[$key] = $value;

            return true;
        } catch (PDOException $e) {
            error_log("SettingsManager: Error setting '$key': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Set multiple settings at once
     * 
     * @param array $settings Associative array of key => value pairs
     * @param int|null $user_id User ID for audit trail (if applicable)
     * @return bool Success status
     */
    public function setSettings($settings, $user_id = null) {
        if (!$this->conn || empty($settings)) {
            return false;
        }

        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("
                INSERT INTO company_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value),
                    updated_at = CURRENT_TIMESTAMP
            ");

            foreach ($settings as $key => $value) {
                $stmt->execute([$key, $value]);
                $this->cache[$key] = $value;
            }

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("SettingsManager: Error setting multiple settings: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a setting exists
     * 
     * @param string $key Setting key to check
     * @return bool True if setting exists, false otherwise
     */
    public function settingExists($key) {
        if (!$this->conn) {
            return false;
        }

        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM company_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("SettingsManager: Error checking if setting '$key' exists: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all settings (useful for admin interfaces)
     * 
     * @param string|null $prefix Optional prefix to filter settings
     * @return array All settings or filtered by prefix
     */
    public function getAllSettings($prefix = null) {
        if (!$this->conn) {
            return [];
        }

        try {
            if ($prefix) {
                $stmt = $this->conn->prepare("SELECT setting_key, setting_value FROM company_settings WHERE setting_key LIKE ? ORDER BY setting_key");
                $stmt->execute([$prefix . '%']);
            } else {
                $stmt = $this->conn->query("SELECT setting_key, setting_value FROM company_settings ORDER BY setting_key");
            }

            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[$row['setting_key']] = $row['setting_value'];
                $this->cache[$row['setting_key']] = $row['setting_value'];
            }

            return $results;
        } catch (PDOException $e) {
            error_log("SettingsManager: Error retrieving all settings: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Clear the internal cache
     * Useful if settings are updated outside of this class
     */
    public function clearCache() {
        $this->cache = [];
    }

    /**
     * Get a setting with a specific data type
     * 
     * @param string $key Setting key
     * @param string $type Data type: 'int', 'float', 'bool', 'array' (JSON)
     * @param mixed $default Default value if setting not found
     * @return mixed Converted value or default
     */
    public function getTypedSetting($key, $type = 'string', $default = null) {
        $value = $this->getSetting($key);
        
        if ($value === null) {
            return $default;
        }

        switch ($type) {
            case 'int':
                return (int)$value;
            case 'float':
                return (float)$value;
            case 'bool':
                return (bool)$value || $value === '1' || strtolower($value) === 'true';
            case 'array':
                return json_decode($value, true) ?: $default;
            default:
                return $value;
        }
    }
}
?>