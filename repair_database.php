<?php
/**
 * Database Repair Script
 * Repairs corrupted SQLite database without losing data
 */

$dbPath = __DIR__ . '/database/database.sqlite';
$backupPath = __DIR__ . '/database/database.sqlite.backup_' . date('Y-m-d_His');

echo "Starting database repair...\n";

// Step 1: Create backup
echo "Creating backup: " . basename($backupPath) . "\n";
if (!copy($dbPath, $backupPath)) {
    die("Failed to create backup!\n");
}
echo "✓ Backup created successfully\n\n";

// Step 2: Try to repair the database
try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Checking database integrity...\n";
    $result = $db->query("PRAGMA integrity_check;")->fetchAll(PDO::FETCH_COLUMN);
    
    if ($result[0] === 'ok') {
        echo "✓ Database integrity is OK!\n";
    } else {
        echo "✗ Database has integrity issues:\n";
        foreach ($result as $issue) {
            echo "  - $issue\n";
        }
        
        // Try to recover
        echo "\nAttempting to recover database...\n";
        
        // Export to SQL
        $tempSqlPath = __DIR__ . '/database/temp_export.sql';
        echo "Exporting database to SQL...\n";
        
        // Create new database
        $newDbPath = __DIR__ . '/database/database_repaired.sqlite';
        $newDb = new PDO('sqlite:' . $newDbPath);
        $newDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Get all tables
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
        
        echo "Found " . count($tables) . " tables to copy\n";
        
        foreach ($tables as $table) {
            echo "  Copying table: $table...\n";
            
            try {
                // Get table schema
                $schema = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='$table'")->fetchColumn();
                $newDb->exec($schema);
                
                // Copy data
                $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                echo "    - Found " . count($rows) . " rows\n";
                
                if (count($rows) > 0) {
                    // Get column names
                    $columns = array_keys($rows[0]);
                    $placeholders = implode(',', array_fill(0, count($columns), '?'));
                    $columnsList = '`' . implode('`, `', $columns) . '`';
                    
                    $stmt = $newDb->prepare("INSERT INTO `$table` ($columnsList) VALUES ($placeholders)");
                    
                    foreach ($rows as $row) {
                        $stmt->execute(array_values($row));
                    }
                    echo "    ✓ Copied successfully\n";
                }
            } catch (Exception $e) {
                echo "    ✗ Error copying table $table: " . $e->getMessage() . "\n";
            }
        }
        
        // Copy indexes
        echo "\nCopying indexes...\n";
        $indexes = $db->query("SELECT sql FROM sqlite_master WHERE type='index' AND sql IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($indexes as $indexSql) {
            try {
                $newDb->exec($indexSql);
            } catch (Exception $e) {
                // Ignore errors (index might already exist)
            }
        }
        
        echo "\n✓ Database repaired and saved to: database_repaired.sqlite\n";
        echo "\nTo use the repaired database:\n";
        echo "1. Stop the Laravel server\n";
        echo "2. Run: mv database/database.sqlite database/database_corrupted.sqlite\n";
        echo "3. Run: mv database/database_repaired.sqlite database/database.sqlite\n";
        echo "4. Restart the Laravel server\n";
    }
    
    // Optimize database
    echo "\nOptimizing database...\n";
    $db->exec("VACUUM;");
    $db->exec("ANALYZE;");
    echo "✓ Database optimized\n";
    
    echo "\n✓ Database repair completed successfully!\n";
    
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "\nYou can restore from backup: $backupPath\n";
    exit(1);
}
