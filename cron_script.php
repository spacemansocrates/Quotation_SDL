<?php
/**
 * MySQL to Supabase PostgreSQL Sync Script (Cron Version)
 * 
 * This script synchronizes data from a local MySQL database to a Supabase PostgreSQL database.
 * It should be set up as a cron job to run every 5 minutes.
 * 
 * Requirements:
 * - PHP 7.4+
 * - PDO extension for MySQL and PostgreSQL
 * - php-pgsql extension
 */

// Configuration
$config = [
    'mysql' => [
        'host' => 'localhost',
        'database' => 'supplies',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4'
    ],
'postgres' => [
    'host' => 'db.goafmvarojbmbpfjehfh.supabase.co',
    'port' => '5432',
    'database' => 'postgres',
    'username' => 'postgres',
    'password' => 'Naho1386#',
    'schema' => 'public'
    ]
];


// Tables to sync in order (respecting foreign key constraints)
$tables = [
    'users',
    'company_settings',
    'units_of_measurement',
    'shops',
    'customers',
    'products',
    'quotations',
    'quotation_items',
    'activity_log'
];

// Set execution time limit (0 = no limit)
set_time_limit(300); // 5 minutes

// Log function
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message" . PHP_EOL;
    
    // Also log to file
    $logFile = __DIR__ . '/sync_log.txt';
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

// Connect to MySQL database
function connectToMysql($config) {
    try {
        $dsn = "mysql:host={$config['mysql']['host']};dbname={$config['mysql']['database']};charset={$config['mysql']['charset']}";
        $pdo = new PDO($dsn, $config['mysql']['username'], $config['mysql']['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        logMessage("MySQL Connection Error: " . $e->getMessage());
        exit(1);
    }
}

// Connect to PostgreSQL database
function connectToPostgres($config) {
    try {
        $dsn = "pgsql:host={$config['postgres']['host']};port={$config['postgres']['port']};dbname={$config['postgres']['database']};user={$config['postgres']['username']};password={$config['postgres']['password']}";
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Set the schema
        $pdo->exec("SET search_path TO {$config['postgres']['schema']}");
        
        return $pdo;
    } catch (PDOException $e) {
        logMessage("PostgreSQL Connection Error: " . $e->getMessage());
        exit(1);
    }
}

// Get the primary key for a table
function getPrimaryKey($pdo, $table, $isPostgres = false) {
    if ($isPostgres) {
        $stmt = $pdo->prepare("
            SELECT a.attname as column_name
            FROM pg_index i
            JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
            WHERE i.indrelid = :table::regclass AND i.indisprimary
        ");
        $stmt->execute(['table' => $table]);
    } else {
        $stmt = $pdo->prepare("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = :database
            AND TABLE_NAME = :table
            AND CONSTRAINT_NAME = 'PRIMARY'
        ");
        $stmt->execute([
            'database' => $GLOBALS['config']['mysql']['database'],
            'table' => $table
        ]);
    }
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? ($isPostgres ? $result['column_name'] : $result['COLUMN_NAME']) : 'id';
}

// Check if a record exists in PostgreSQL
function recordExists($pgPdo, $table, $primaryKey, $primaryValue) {
    $stmt = $pgPdo->prepare("SELECT 1 FROM $table WHERE $primaryKey = :value");
    $stmt->execute(['value' => $primaryValue]);
    return $stmt->fetchColumn() !== false;
}

// Get the last sync time for a table
function getLastSyncTime($table) {
    $syncFile = __DIR__ . '/last_sync_time.json';
    
    if (file_exists($syncFile)) {
        $syncTimes = json_decode(file_get_contents($syncFile), true);
        return isset($syncTimes[$table]) ? $syncTimes[$table] : 0;
    }
    
    return 0;
}

// Update the last sync time for a table
function updateLastSyncTime($table) {
    $syncFile = __DIR__ . '/last_sync_time.json';
    
    if (file_exists($syncFile)) {
        $syncTimes = json_decode(file_get_contents($syncFile), true);
    } else {
        $syncTimes = [];
    }
    
    $syncTimes[$table] = time();
    file_put_contents($syncFile, json_encode($syncTimes));
}

// Convert MySQL data types to PostgreSQL compatible types
function convertMysqlValueToPostgres($value, $mysqlType) {
    if ($value === null) {
        return null;
    }
    
    // Handle boolean values
    if (preg_match('/tinyint\(1\)/', $mysqlType)) {
        return $value == 1 ? 't' : 'f';
    }
    
    // Handle date/time values
    if (preg_match('/(datetime|timestamp)/', $mysqlType) && $value == '0000-00-00 00:00:00') {
        return null;
    }
    
    return $value;
}

// Sync a table from MySQL to PostgreSQL
function syncTable($mysqlPdo, $postgresPdo, $table) {
    logMessage("Starting sync for table: $table");
    
    $lastSyncTime = getLastSyncTime($table);
    $lastSyncDate = date('Y-m-d H:i:s', $lastSyncTime);
    
    // Get MySQL table structure
    $stmt = $mysqlPdo->prepare("DESCRIBE $table");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $columnTypes = [];
    foreach ($columns as $column) {
        $columnTypes[$column['Field']] = $column['Type'];
    }
    
    // Get primary key
    $primaryKey = getPrimaryKey($mysqlPdo, $table);
    
    // Get records from MySQL that have been updated since the last sync
    $stmt = $mysqlPdo->prepare("
        SELECT * FROM $table 
        WHERE updated_at > :lastSync 
        OR created_at > :lastSync
        OR (updated_at IS NULL AND created_at IS NULL)
    ");
    $stmt->execute(['lastSync' => $lastSyncDate]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $insertCount = 0;
    $updateCount = 0;
    
    // Process each record
    foreach ($records as $record) {
        // Convert data types for PostgreSQL
        foreach ($record as $field => $value) {
            if (isset($columnTypes[$field])) {
                $record[$field] = convertMysqlValueToPostgres($value, $columnTypes[$field]);
            }
        }
        
        $primaryValue = $record[$primaryKey];
        
        // Check if the record exists in PostgreSQL
        $exists = recordExists($postgresPdo, $table, $primaryKey, $primaryValue);
        
        if ($exists) {
            // Build update query
            $updateFields = [];
            $params = [];
            
            foreach ($record as $field => $value) {
                if ($field !== $primaryKey) {
                    $updateFields[] = "$field = :$field";
                    $params[$field] = $value;
                }
            }
            
            $params[$primaryKey] = $primaryValue;
            
            $sql = "UPDATE $table SET " . implode(', ', $updateFields) . " WHERE $primaryKey = :$primaryKey";
            
            try {
                $stmt = $postgresPdo->prepare($sql);
                $stmt->execute($params);
                $updateCount++;
            } catch (PDOException $e) {
                logMessage("Error updating record in $table with ID $primaryValue: " . $e->getMessage());
            }
        } else {
            // Build insert query
            $fields = array_keys($record);
            $placeholders = array_map(function($field) {
                return ":$field";
            }, $fields);
            
            $sql = "INSERT INTO $table (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            
            try {
                $stmt = $postgresPdo->prepare($sql);
                $stmt->execute($record);
                $insertCount++;
            } catch (PDOException $e) {
                logMessage("Error inserting record into $table: " . $e->getMessage());
            }
        }
    }
    
    // Update last sync time
    updateLastSyncTime($table);
    
    logMessage("Completed sync for table: $table. Inserted: $insertCount, Updated: $updateCount");
}

// Main sync function
function syncDatabases($config, $tables) {
    try {
        logMessage("Starting database sync");
        
        // Connect to databases
        $mysqlPdo = connectToMysql($config);
        $postgresPdo = connectToPostgres($config);
        
        // Begin transaction in PostgreSQL
        $postgresPdo->beginTransaction();
        
        // Sync each table
        foreach ($tables as $table) {
            syncTable($mysqlPdo, $postgresPdo, $table);
        }
        
        // Commit transaction
        $postgresPdo->commit();
        
        logMessage("Database sync completed successfully");
    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($postgresPdo) && $postgresPdo->inTransaction()) {
            $postgresPdo->rollBack();
        }
        
        logMessage("Error during sync: " . $e->getMessage());
    }
}

// Run the sync process
logMessage("Sync script started");
syncDatabases($config, $tables);
logMessage("Sync script completed");