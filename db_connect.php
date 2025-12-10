<?php
/**
 * AKIRA HOSPITAL Management System
 * Database Connection for XAMPP with PostgreSQL/MySQL Fallback
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include helper files for XAMPP compatibility if needed
$helper_file = __DIR__ . '/db_connect_helper.php';
if (file_exists($helper_file)) {
    include_once $helper_file;
}

// Database type - can be 'postgres' or 'mysql'
$db_type = 'mysql';  // Using MySQL by default for XAMPP compatibility

// Database configuration - Edit these values to match your XAMPP setup
$db_host = "localhost";
$db_name = "akira_hospital"; // This will be created automatically if it doesn't exist

// PostgreSQL specific settings
$pg_port = "5432";
$pg_user = "postgres";
$pg_password = "postgres"; // Change this to your PostgreSQL password

// MySQL specific settings
$mysql_port = "3306";
$mysql_user = "root";
$mysql_password = ""; // Default XAMPP MySQL password is blank

// Read from environment variables if available (for production environments)
if ($db_type == 'postgres') {
    $db_host = getenv('PGHOST') ?: $db_host;
    $pg_port = getenv('PGPORT') ?: $pg_port;
    $db_name = getenv('PGDATABASE') ?: $db_name;
    $pg_user = getenv('PGUSER') ?: $pg_user;
    $pg_password = getenv('PGPASSWORD') ?: $pg_password;
}

// Global PDO object
$pdo = null;
$db_connected = false;
$active_db_type = null;

// Try PostgreSQL first (if selected)
if ($db_type == 'postgres') {
    try {
        // Check if PostgreSQL PDO driver is available
        if (!extension_loaded('pdo_pgsql')) {
            throw new PDOException("PostgreSQL PDO driver not available");
        }
        
        // Connection string for PostgreSQL
        $connection_string = "pgsql:host={$db_host};port={$pg_port};dbname={$db_name}";
        
        // Create a PDO instance for PostgreSQL
        $pdo = new PDO($connection_string, $pg_user, $pg_password);
        
        // Set error mode to exceptions
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Set default fetch mode to associative array
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Use prepared statements for security
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        
        $db_connected = true;
        $active_db_type = 'postgres';
        error_log("PostgreSQL connection established successfully");
    } catch (PDOException $e) {
        // Log the error
        error_log("PostgreSQL connection failed: " . $e->getMessage());
        
        // If PostgreSQL fails, we'll try MySQL next
    }
}

// Try MySQL if PostgreSQL failed or if MySQL was selected
if (!$db_connected) {
    try {
        // Check if MySQL PDO driver is available
        if (!extension_loaded('pdo_mysql')) {
            throw new PDOException("MySQL PDO driver not available");
        }
        
        // Connection string for MySQL
        $connection_string = "mysql:host={$db_host};port={$mysql_port};dbname={$db_name}";
        
        // Create a PDO instance for MySQL
        try {
            $pdo = new PDO($connection_string, $mysql_user, $mysql_password);
        } catch (PDOException $e) {
            // If the database doesn't exist, try to create it (for MySQL)
            if (strpos($e->getMessage(), "Unknown database") !== false) {
                $temp_pdo = new PDO("mysql:host={$db_host};port={$mysql_port}", $mysql_user, $mysql_password);
                $temp_pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $temp_pdo = null; // Close temporary connection
                
                // Try connecting again
                $pdo = new PDO($connection_string, $mysql_user, $mysql_password);
            } else {
                throw $e; // Re-throw other exceptions
            }
        }
        
        // Set error mode to exceptions
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Set default fetch mode to associative array
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Use prepared statements for security
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        
        $db_connected = true;
        $active_db_type = 'mysql';
        error_log("MySQL connection established successfully");
        
        // Create basic tables if they don't exist (for MySQL)
        createBasicTablesIfNeeded($pdo);
    } catch (PDOException $e) {
        // Log the error
        error_log("MySQL connection failed: " . $e->getMessage());
        
        // Provide a comprehensive error message
        $error_message = "
========================================================================
DATABASE CONNECTION ERROR

We tried both PostgreSQL and MySQL, but couldn't connect to either.

Please check:
1. Either PostgreSQL or MySQL service is running in XAMPP
2. For PostgreSQL: 
   - User: {$pg_user}
   - Database: {$db_name}
   - Port: {$pg_port}
3. For MySQL:
   - User: {$mysql_user}
   - Database: {$db_name}
   - Port: {$mysql_port}
4. Make sure the PHP PDO extensions are enabled in php.ini

Error details: {$e->getMessage()}
========================================================================
        ";
        error_log($error_message);
        die("Database error: Could not connect to database. Please check XAMPP configuration and make sure either PostgreSQL or MySQL is running.");
    }
}

/**
 * Creates the basic tables needed for the system if they don't exist (MySQL)
 * 
 * @param PDO $pdo The PDO connection
 */
function createBasicTablesIfNeeded($pdo) {
    global $active_db_type;
    
    if ($active_db_type != 'mysql') {
        return; // Only run this for MySQL
    }
    
    try {
        // Create users table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) NOT NULL UNIQUE,
                `password` VARCHAR(255) NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `email` VARCHAR(100) NULL,
                `phone` VARCHAR(20) NULL,
                `role` ENUM('admin', 'doctor', 'nurse', 'receptionist', 'pharmacist', 'lab_technician', 'staff') NOT NULL DEFAULT 'staff',
                `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        
        // Check if admin user exists
        $stmt = $pdo->query("SELECT * FROM `users` WHERE `username` = 'admin'");
        $admin = $stmt->fetch();
        
        // Create admin user if it doesn't exist
        if (!$admin) {
            $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $pdo->exec("
                INSERT INTO `users` (`username`, `password`, `name`, `email`, `role`)
                VALUES ('admin', '{$hashedPassword}', 'System Admin', 'admin@akira.hospital', 'admin')
            ");
            error_log("Created default admin user for MySQL");
        }
    } catch (PDOException $e) {
        error_log("Error creating basic tables: " . $e->getMessage());
    }
}

/**
 * Helper function to execute SQL queries and handle errors
 * 
 * @param string $sql The SQL query with placeholders
 * @param array $params The parameters to bind to the query
 * @return PDOStatement The executed statement
 */
function execute_query($sql, $params = []) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query execution failed: " . $e->getMessage());
        error_log("SQL: " . $sql);
        error_log("Params: " . print_r($params, true));
        
        // Re-throw the exception for the calling code to handle
        throw $e;
    }
}

/**
 * Helper function to get a single row from database
 * 
 * @param string $sql The SQL query with placeholders
 * @param array $params The parameters to bind to the query
 * @return array|null The fetched row or null if not found
 */
function db_get_row($sql, $params = []) {
    try {
        $stmt = execute_query($sql, $params);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Failed to get row: " . $e->getMessage());
        return null;
    }
}

/**
 * Helper function to get multiple rows from database
 * 
 * @param string $sql The SQL query with placeholders
 * @param array $params The parameters to bind to the query
 * @return array The fetched rows
 */
function db_get_rows($sql, $params = []) {
    try {
        $stmt = execute_query($sql, $params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to get rows: " . $e->getMessage());
        return [];
    }
}

/**
 * Helper function to insert a record and get the inserted ID
 * 
 * @param string $table The table to insert into
 * @param array $data Associative array of column => value
 * @return int|null The last inserted ID or null on failure
 */
function db_insert($table, $data) {
    global $pdo;
    
    try {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = "INSERT INTO " . $table . " (" . implode(', ', $columns) . ") 
                VALUES (" . implode(', ', $placeholders) . ") RETURNING id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($data));
        
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Insert failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Helper function to update a record
 * 
 * @param string $table The table to update
 * @param array $data Associative array of column => value to update
 * @param string $where The WHERE clause
 * @param array $whereParams Parameters for the WHERE clause
 * @return bool True on success, false on failure
 */
function db_update($table, $data, $where, $whereParams = []) {
    try {
        $setClauses = [];
        $params = [];
        
        foreach ($data as $column => $value) {
            $setClauses[] = "$column = ?";
            $params[] = $value;
        }
        
        $sql = "UPDATE " . $table . " SET " . implode(', ', $setClauses) . " WHERE " . $where;
        
        // Combine the SET parameters with the WHERE parameters
        $allParams = array_merge($params, $whereParams);
        
        $stmt = execute_query($sql, $allParams);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Update failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Helper function to delete records
 * 
 * @param string $table The table to delete from
 * @param string $where The WHERE clause
 * @param array $params Parameters for the WHERE clause
 * @return bool True on success, false on failure
 */
function db_delete($table, $where, $params = []) {
    try {
        $sql = "DELETE FROM " . $table . " WHERE " . $where;
        $stmt = execute_query($sql, $params);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Delete failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Helper function to execute a SQL query
 * This function is used for INSERT, UPDATE, DELETE operations
 * 
 * @param string $sql The SQL query with placeholders
 * @param array $params The parameters to bind to the query
 * @return PDOStatement|bool The executed statement or false on failure
 */
function db_query($sql, $params = []) {
    try {
        return execute_query($sql, $params);
    } catch (PDOException $e) {
        error_log("Query execution failed: " . $e->getMessage());
        error_log("SQL: " . $sql);
        error_log("Params: " . print_r($params, true));
        return false;
    }
}