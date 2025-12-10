<?php
/**
 * AKIRA HOSPITAL Management System
 * Billing Module XAMPP Fix
 * 
 * This file provides a quick fix for billing/invoice issues in XAMPP environment
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'db_connect.php';

// Define database variables from db_connect.php if not defined
if (!isset($pdo)) {
    die("Database connection not established. Check db_connect.php.");
}

// Check if invoices table exists
$invoice_table_exists = false;
try {
    $check_invoice_sql = "SELECT 1 FROM information_schema.tables WHERE table_name = 'invoices'";
    $stmt = $pdo->query($check_invoice_sql);
    $invoice_table_exists = $stmt && $stmt->fetchColumn();
} catch (PDOException $e) {
    // Table might not exist
}

// Check if invoice_items table exists
$invoice_items_table_exists = false;
try {
    $check_items_sql = "SELECT 1 FROM information_schema.tables WHERE table_name = 'invoice_items'";
    $stmt = $pdo->query($check_items_sql);
    $invoice_items_table_exists = $stmt && $stmt->fetchColumn();
} catch (PDOException $e) {
    // Table might not exist
}

// Start HTML output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AKIRA HOSPITAL - Billing Fix for XAMPP</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .container {
            width: 80%;
            margin: 20px auto;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #483D8B;
            padding-bottom: 10px;
            border-bottom: 2px solid #6A5ACD;
        }
        .success {
            background: #E6FFE6;
            border-left: 5px solid #4CAF50;
            padding: 10px;
            margin: 10px 0;
        }
        .warning {
            background: #FFFFCC;
            border-left: 5px solid #FFC107;
            padding: 10px;
            margin: 10px 0;
        }
        .error {
            background: #FFEBEE;
            border-left: 5px solid #F44336;
            padding: 10px;
            margin: 10px 0;
        }
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 3px;
            overflow-x: auto;
        }
        code {
            font-family: Consolas, monospace;
            background: #f0f0f0;
            padding: 2px 4px;
            border-radius: 3px;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #6A5ACD;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 5px 0;
            cursor: pointer;
            border: none;
        }
        .btn:hover {
            background: #483D8B;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>AKIRA HOSPITAL Management System</h1>
        <h2>Billing Module XAMPP Fix</h2>
        
        <div class="status">
            <h3>Status Check</h3>
            <?php
            // Check if the PDO connection is working
            if ($pdo instanceof PDO) {
                echo "<div class='success'>Database connection established ✓</div>";
            } else {
                echo "<div class='error'>Database connection failed. Check db_connect.php.</div>";
            }
            
            // Check if xampp_sync.php exists
            if (file_exists('xampp_sync.php')) {
                echo "<div class='success'>XAMPP compatibility helper file exists ✓</div>";
                
                // Include it for testing
                include_once 'xampp_sync.php';
                
                // Check if the helper functions exist
                if (function_exists('pdo_query') && function_exists('create_invoice_pdo')) {
                    echo "<div class='success'>XAMPP compatibility helper functions loaded ✓</div>";
                } else {
                    echo "<div class='error'>XAMPP compatibility helper functions not found. Check xampp_sync.php.</div>";
                }
            } else {
                echo "<div class='error'>XAMPP compatibility helper file (xampp_sync.php) not found.</div>";
            }
            
            // Check invoice tables
            if ($invoice_table_exists) {
                echo "<div class='success'>Invoices table exists ✓</div>";
            } else {
                echo "<div class='warning'>Invoices table does not exist.</div>";
            }
            
            if ($invoice_items_table_exists) {
                echo "<div class='success'>Invoice items table exists ✓</div>";
            } else {
                echo "<div class='warning'>Invoice items table does not exist.</div>";
            }
            ?>
        </div>
        
        <?php
        // Check if billing.php includes xampp_sync.php
        $billing_includes_helper = false;
        if (file_exists('billing.php')) {
            $billing_content = file_get_contents('billing.php');
            $billing_includes_helper = strpos($billing_content, "xampp_sync.php") !== false;
            
            if ($billing_includes_helper) {
                echo "<div class='success'>billing.php includes the compatibility helper ✓</div>";
            } else {
                echo "<div class='warning'>billing.php does not include the compatibility helper.</div>";
            }
        } else {
            echo "<div class='error'>billing.php not found.</div>";
        }
        ?>
        
        <div class="fix-instructions">
            <h3>Fix Instructions</h3>
            
            <?php if (!$invoice_table_exists || !$invoice_items_table_exists): ?>
            <div class="step">
                <h4>Step 1: Create Missing Tables</h4>
                <form method="post" action="">
                    <input type="hidden" name="action" value="create_tables">
                    <button type="submit" class="btn">Create Invoice Tables</button>
                </form>
                
                <?php
                // Process create tables action
                if (isset($_POST['action']) && $_POST['action'] == 'create_tables') {
                    try {
                        // Start transaction
                        $pdo->beginTransaction();
                        
                        // Create invoices table
                        if (!$invoice_table_exists) {
                            $pdo->exec("
                                CREATE TABLE IF NOT EXISTS `invoices` (
                                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                                    `patient_id` INT NOT NULL,
                                    `invoice_date` DATE NOT NULL,
                                    `due_date` DATE NULL,
                                    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
                                    `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
                                    `payment_status` VARCHAR(20) NOT NULL DEFAULT 'pending',
                                    `payment_method` VARCHAR(50) NULL,
                                    `payment_date` DATE NULL,
                                    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
                                    `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
                                    `notes` TEXT NULL,
                                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                            ");
                            echo "<div class='success'>Created invoices table successfully!</div>";
                        }
                        
                        // Create invoice_items table
                        if (!$invoice_items_table_exists) {
                            $pdo->exec("
                                CREATE TABLE IF NOT EXISTS `invoice_items` (
                                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                                    `invoice_id` INT NOT NULL,
                                    `item_type` VARCHAR(50) NULL DEFAULT 'service',
                                    `item_id` INT NULL,
                                    `description` VARCHAR(255) NOT NULL,
                                    `quantity` INT NOT NULL DEFAULT 1,
                                    `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0,
                                    `amount` DECIMAL(10,2) NOT NULL,
                                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                    FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                            ");
                            echo "<div class='success'>Created invoice_items table successfully!</div>";
                        }
                        
                        // Commit transaction
                        $pdo->commit();
                        
                        // Refresh the page to update status
                        echo "<script>setTimeout(function() { window.location.reload(); }, 2000);</script>";
                        echo "<div class='success'>Tables created successfully! Refreshing page...</div>";
                    } catch (PDOException $e) {
                        // Rollback transaction
                        $pdo->rollBack();
                        echo "<div class='error'>Failed to create tables: " . $e->getMessage() . "</div>";
                    }
                }
                ?>
            </div>
            <?php endif; ?>
            
            <?php if (!file_exists('xampp_sync.php') || !$billing_includes_helper): ?>
            <div class="step">
                <h4>Step 2: Fix Compatibility Files</h4>
                <form method="post" action="">
                    <input type="hidden" name="action" value="fix_compatibility">
                    <button type="submit" class="btn">Fix Compatibility Files</button>
                </form>
                
                <?php
                // Process fix compatibility action
                if (isset($_POST['action']) && $_POST['action'] == 'fix_compatibility') {
                    $success = true;
                    $messages = [];
                    
                    // Create xampp_sync.php if it doesn't exist
                    if (!file_exists('xampp_sync.php')) {
                        $xampp_sync_content = '<?php
/**
 * AKIRA HOSPITAL Management System
 * XAMPP Compatibility Helper
 * 
 * This file provides helper functions to ensure database operations
 * work properly in XAMPP/MySQL environment
 */

// Include database connection
require_once \'db_connect.php\';

/**
 * Performs direct PDO query - use this instead of db_query in XAMPP
 * 
 * @param string $sql SQL query with named parameters
 * @param array $params Array of parameters
 * @return PDOStatement|false The result set
 */
function pdo_query($sql, $params = []) {
    global $pdo;
    
    if (!$pdo) {
        die("Database connection failed. Check db_connect.php");
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        // Log error
        error_log("Database error: " . $e->getMessage());
        // Return false to indicate failure
        return false;
    }
}

/**
 * Creates a new invoice using PDO and returns the new ID
 * 
 * @param int $patientId Patient ID
 * @param string $invoiceDate Invoice date
 * @param float $amount Total amount
 * @param string $status Status
 * @return int|false New invoice ID or false on failure
 */
function create_invoice_pdo($patientId, $invoiceDate, $amount, $status) {
    global $pdo;
    
    try {
        $sql = "INSERT INTO invoices (patient_id, invoice_date, total_amount, payment_status) 
                VALUES (:patient_id, :invoice_date, :total_amount, :status)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            \':patient_id\' => $patientId,
            \':invoice_date\' => $invoiceDate,
            \':total_amount\' => $amount,
            \':status\' => $status
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Invoice creation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Creates a new invoice item using PDO
 * 
 * @param int $invoiceId Invoice ID
 * @param string $description Item description
 * @param float $amount Item amount
 * @return bool Success status
 */
function create_invoice_item_pdo($invoiceId, $description, $amount) {
    global $pdo;
    
    try {
        $sql = "INSERT INTO invoice_items (invoice_id, description, amount) 
                VALUES (:invoice_id, :description, :amount)";
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            \':invoice_id\' => $invoiceId,
            \':description\' => $description,
            \':amount\' => $amount
        ]);
        
        return $success;
    } catch (PDOException $e) {
        error_log("Invoice item creation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Deletes an invoice and its items using PDO transaction
 * 
 * @param int $invoiceId Invoice ID to delete
 * @return bool Success status
 */
function delete_invoice_pdo($invoiceId) {
    global $pdo;
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Delete invoice items first
        $deleteItemsStmt = $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = :id");
        $deleteItemsStmt->execute([\':id\' => $invoiceId]);
        
        // Delete invoice
        $deleteInvoiceStmt = $pdo->prepare("DELETE FROM invoices WHERE id = :id");
        $deleteInvoiceStmt->execute([\':id\' => $invoiceId]);
        
        // Commit transaction
        $pdo->commit();
        
        return true;
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log("Invoice deletion error: " . $e->getMessage());
        return false;
    }
}';
                        
                        if (file_put_contents('xampp_sync.php', $xampp_sync_content)) {
                            $messages[] = "<div class='success'>Created xampp_sync.php successfully!</div>";
                        } else {
                            $success = false;
                            $messages[] = "<div class='error'>Failed to create xampp_sync.php. Check file permissions.</div>";
                        }
                    }
                    
                    // Update billing.php to include xampp_sync.php if needed
                    if (file_exists('billing.php') && !$billing_includes_helper) {
                        $billing_content = file_get_contents('billing.php');
                        
                        // Find the include for db_connect.php
                        $pattern = '/require_once\s+[\'"]db_connect\.php[\'"]\s*;/i';
                        $replacement = "require_once 'db_connect.php';\nrequire_once 'xampp_sync.php'; // Include XAMPP compatibility helper";
                        
                        $updated_content = preg_replace($pattern, $replacement, $billing_content);
                        
                        if ($updated_content !== $billing_content) {
                            if (file_put_contents('billing.php', $updated_content)) {
                                $messages[] = "<div class='success'>Updated billing.php to include compatibility helper!</div>";
                            } else {
                                $success = false;
                                $messages[] = "<div class='error'>Failed to update billing.php. Check file permissions.</div>";
                            }
                        } else {
                            $success = false;
                            $messages[] = "<div class='warning'>Could not locate the right spot to update billing.php. Please manually add:<br><code>require_once 'xampp_sync.php';</code> after the DB connection include.</div>";
                        }
                    }
                    
                    // Output results
                    foreach ($messages as $message) {
                        echo $message;
                    }
                    
                    if ($success) {
                        echo "<div class='success'>Fixed compatibility files successfully! Refreshing page...</div>";
                        echo "<script>setTimeout(function() { window.location.reload(); }, 2000);</script>";
                    }
                }
                ?>
            </div>
            <?php endif; ?>
            
            <div class="step">
                <h4>Step 3: Test Invoice Creation</h4>
                <p>Once all the fixes are applied, you can test creating an invoice:</p>
                <a href="billing.php?action=new" class="btn">Create New Invoice</a>
                
                <p>If you still experience issues, you can manually add test data:</p>
                <form method="post" action="">
                    <input type="hidden" name="action" value="test_invoice">
                    <label for="patient_id">Patient ID:</label>
                    <input type="number" name="patient_id" id="patient_id" value="1" required><br><br>
                    
                    <button type="submit" class="btn">Create Test Invoice</button>
                </form>
                
                <?php
                // Process test invoice action
                if (isset($_POST['action']) && $_POST['action'] == 'test_invoice') {
                    try {
                        // Only proceed if xampp_sync.php is included
                        if (!function_exists('create_invoice_pdo')) {
                            include_once 'xampp_sync.php';
                        }
                        
                        $patient_id = intval($_POST['patient_id']);
                        $invoice_date = date('Y-m-d');
                        $amount = 100.00;
                        
                        // Begin transaction
                        $pdo->beginTransaction();
                        
                        // Create invoice using helper function
                        $invoice_id = create_invoice_pdo($patient_id, $invoice_date, $amount, 'pending');
                        
                        if (!$invoice_id) {
                            throw new Exception("Failed to create invoice");
                        }
                        
                        // Create invoice item
                        $item_result = create_invoice_item_pdo(
                            $invoice_id,
                            'Test Consultation',
                            $amount
                        );
                        
                        if (!$item_result) {
                            throw new Exception("Failed to create invoice item");
                        }
                        
                        // Commit transaction
                        $pdo->commit();
                        
                        echo "<div class='success'>Created test invoice (#$invoice_id) successfully!</div>";
                        echo "<a href='billing.php?action=view&id=$invoice_id' class='btn'>View Test Invoice</a>";
                    } catch (Exception $e) {
                        // Rollback transaction
                        if (isset($pdo) && $pdo instanceof PDO) {
                            $pdo->rollBack();
                        }
                        echo "<div class='error'>Failed to create test invoice: " . $e->getMessage() . "</div>";
                    }
                }
                ?>
            </div>
        </div>
        
        <div class="navigation">
            <h3>Navigation</h3>
            <a href="index.php" class="btn">Return to Homepage</a>
            <a href="xampp_deployment_guide.php" class="btn">XAMPP Deployment Guide</a>
            <a href="billing.php" class="btn">Billing Module</a>
        </div>
    </div>
</body>
</html>