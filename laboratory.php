<?php
/**
 * AKIRA HOSPITAL Management System
 * Laboratory Management Page
 */

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Include database connection
require_once 'db_connect.php';

// Get admin information
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? 'Administrator';
$admin_role = $_SESSION['admin_role'] ?? 'admin';

// Check if action is specified (new, edit, view, delete)
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$test_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

// Get list of test categories
$test_categories = [];
try {
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'test_categories'")) {
        $test_categories = db_get_rows("SELECT * FROM test_categories ORDER BY name ASC");
    } else {
        // Create test_categories table if it doesn't exist
        db_query("
            CREATE TABLE IF NOT EXISTS test_categories (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT NULL
            )
        ");
        
        // Insert default test categories
        $default_categories = [
            ['name' => 'Hematology', 'description' => 'Blood tests'],
            ['name' => 'Biochemistry', 'description' => 'Tests related to chemical processes and substances'],
            ['name' => 'Microbiology', 'description' => 'Tests for microorganisms'],
            ['name' => 'Immunology', 'description' => 'Tests related to immune system'],
            ['name' => 'Radiology', 'description' => 'X-rays, CT scans, MRIs, etc.']
        ];
        
        foreach ($default_categories as $category) {
            db_query("INSERT INTO test_categories (name, description) VALUES (:name, :description)", [
                ':name' => $category['name'],
                ':description' => $category['description']
            ]);
        }
        
        $test_categories = db_get_rows("SELECT * FROM test_categories ORDER BY name ASC");
    }
} catch (PDOException $e) {
    error_log("Error fetching test categories: " . $e->getMessage());
}

// Get list of available tests
$available_tests = [];
try {
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'lab_tests'")) {
        $query = "SELECT t.*, c.name as category_name 
                FROM lab_tests t 
                LEFT JOIN test_categories c ON t.category_id = c.id";
        $available_tests = db_get_rows($query);
    } else {
        // Create lab_tests table if it doesn't exist
        db_query("
            CREATE TABLE IF NOT EXISTS lab_tests (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                category_id INT NULL,
                description TEXT NULL,
                price DECIMAL(10,2) NOT NULL DEFAULT 0,
                duration_minutes INT NULL,
                requirements TEXT NULL,
                FOREIGN KEY (category_id) REFERENCES test_categories(id) ON DELETE SET NULL
            )
        ");
        
        // Insert some default tests
        $default_tests = [
            ['name' => 'Complete Blood Count (CBC)', 'category_id' => 1, 'price' => 500.00, 'duration_minutes' => 60],
            ['name' => 'Blood Glucose Test', 'category_id' => 2, 'price' => 250.00, 'duration_minutes' => 30],
            ['name' => 'Lipid Profile', 'category_id' => 2, 'price' => 800.00, 'duration_minutes' => 90],
            ['name' => 'Chest X-Ray', 'category_id' => 5, 'price' => 1000.00, 'duration_minutes' => 30],
            ['name' => 'COVID-19 PCR Test', 'category_id' => 3, 'price' => 1500.00, 'duration_minutes' => 120]
        ];
        
        foreach ($default_tests as $test) {
            db_query("INSERT INTO lab_tests (name, category_id, price, duration_minutes) VALUES (:name, :category_id, :price, :duration_minutes)", [
                ':name' => $test['name'],
                ':category_id' => $test['category_id'],
                ':price' => $test['price'],
                ':duration_minutes' => $test['duration_minutes']
            ]);
        }
        
        $query = "SELECT t.*, c.name as category_name 
                FROM lab_tests t 
                LEFT JOIN test_categories c ON t.category_id = c.id";
        $available_tests = db_get_rows($query);
    }
} catch (PDOException $e) {
    error_log("Error fetching available tests: " . $e->getMessage());
}

// Get list of test orders
$test_orders = [];
try {
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'lab_orders'")) {
        $query = "SELECT o.*, p.name as patient_name, d.name as doctor_name, 
                    t.name as test_name, t.price
                FROM lab_orders o 
                JOIN patients p ON o.patient_id = p.id
                LEFT JOIN doctors d ON o.doctor_id = d.id
                JOIN lab_tests t ON o.test_id = t.id
                ORDER BY o.order_date DESC, o.order_time DESC";
        $test_orders = db_get_rows($query);
    } else {
        // Create lab_orders table if it doesn't exist
        db_query("
            CREATE TABLE IF NOT EXISTS lab_orders (
                id SERIAL PRIMARY KEY,
                patient_id INT NOT NULL,
                doctor_id INT NULL,
                test_id INT NOT NULL,
                order_date DATE NOT NULL,
                order_time TIME NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                results TEXT NULL,
                completed_at TIMESTAMP NULL,
                notes TEXT NULL,
                FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
                FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL,
                FOREIGN KEY (test_id) REFERENCES lab_tests(id) ON DELETE RESTRICT
            )
        ");
    }
} catch (PDOException $e) {
    error_log("Error fetching test orders: " . $e->getMessage());
}

// Get patients for dropdown
$patients = [];
try {
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'patients'")) {
        $patients = db_get_rows("SELECT id, name FROM patients ORDER BY name ASC");
    }
} catch (PDOException $e) {
    error_log("Error fetching patients: " . $e->getMessage());
}

// Get doctors for dropdown
$doctors = [];
try {
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'doctors'")) {
        $doctors = db_get_rows("SELECT id, name FROM doctors ORDER BY name ASC");
    }
} catch (PDOException $e) {
    error_log("Error fetching doctors: " . $e->getMessage());
}

// Handle form submission for new/edit test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_test'])) {
    try {
        $name = $_POST['name'] ?? '';
        $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
        $description = $_POST['description'] ?? '';
        $price = floatval($_POST['price'] ?? 0);
        $duration_minutes = !empty($_POST['duration_minutes']) ? intval($_POST['duration_minutes']) : null;
        $requirements = $_POST['requirements'] ?? '';
        
        // Validation
        if (empty($name)) {
            $error = "Test name is required";
        } else {
            if ($action === 'edit_test' && $test_id > 0) {
                // Update existing test
                $sql = "UPDATE lab_tests SET name = :name, category_id = :category_id, 
                        description = :description, price = :price, duration_minutes = :duration_minutes, 
                        requirements = :requirements WHERE id = :id";
                $params = [
                    ':name' => $name,
                    ':category_id' => $category_id,
                    ':description' => $description,
                    ':price' => $price,
                    ':duration_minutes' => $duration_minutes,
                    ':requirements' => $requirements,
                    ':id' => $test_id
                ];
                db_query($sql, $params);
                $success = "Test updated successfully";
            } else {
                // Insert new test
                $sql = "INSERT INTO lab_tests (name, category_id, description, price, duration_minutes, requirements) 
                        VALUES (:name, :category_id, :description, :price, :duration_minutes, :requirements)";
                $params = [
                    ':name' => $name,
                    ':category_id' => $category_id,
                    ':description' => $description,
                    ':price' => $price,
                    ':duration_minutes' => $duration_minutes,
                    ':requirements' => $requirements
                ];
                db_query($sql, $params);
                $success = "Test added successfully";
            }
            // Redirect to test list
            header("Location: laboratory.php?action=tests");
            exit;
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Handle form submission for new test order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    try {
        $patient_id = intval($_POST['patient_id'] ?? 0);
        $doctor_id = !empty($_POST['doctor_id']) ? intval($_POST['doctor_id']) : null;
        $test_id = intval($_POST['test_id'] ?? 0);
        $order_date = $_POST['order_date'] ?? date('Y-m-d');
        $order_time = $_POST['order_time'] ?? date('H:i:s');
        $notes = $_POST['notes'] ?? '';
        
        // Validation
        if ($patient_id <= 0) {
            $error = "Patient is required";
        } elseif ($test_id <= 0) {
            $error = "Test is required";
        } else {
            // Insert new order
            $sql = "INSERT INTO lab_orders (patient_id, doctor_id, test_id, order_date, order_time, status, notes) 
                    VALUES (:patient_id, :doctor_id, :test_id, :order_date, :order_time, 'pending', :notes)";
            $params = [
                ':patient_id' => $patient_id,
                ':doctor_id' => $doctor_id,
                ':test_id' => $test_id,
                ':order_date' => $order_date,
                ':order_time' => $order_time,
                ':notes' => $notes
            ];
            db_query($sql, $params);
            $success = "Test order placed successfully";
            // Redirect to order list
            header("Location: laboratory.php");
            exit;
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Handle form submission for updating test results
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_results'])) {
    try {
        $order_id = intval($_POST['order_id'] ?? 0);
        $results = $_POST['results'] ?? '';
        $status = $_POST['status'] ?? 'pending';
        
        // Update test results
        $sql = "UPDATE lab_orders SET results = :results, status = :status";
        $params = [
            ':results' => $results,
            ':status' => $status,
            ':id' => $order_id
        ];
        
        // If status is completed, set the completed_at timestamp
        if ($status === 'completed') {
            $sql .= ", completed_at = CURRENT_TIMESTAMP";
        }
        
        $sql .= " WHERE id = :id";
        
        db_query($sql, $params);
        $success = "Test results updated successfully";
        // Redirect to order list
        header("Location: laboratory.php");
        exit;
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Get test data for edit
$test = null;
if ($action === 'edit_test' && $test_id > 0) {
    try {
        $test = db_get_row("SELECT * FROM lab_tests WHERE id = :id", [':id' => $test_id]);
        if (!$test) {
            $error = "Test not found";
            $action = 'tests'; // Fallback to list
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        $action = 'tests'; // Fallback to list
    }
}

// Get order data for update results
$order = null;
if ($action === 'update_results' && $test_id > 0) {
    try {
        $query = "SELECT o.*, p.name as patient_name, d.name as doctor_name, 
                t.name as test_name, t.price
                FROM lab_orders o 
                JOIN patients p ON o.patient_id = p.id
                LEFT JOIN doctors d ON o.doctor_id = d.id
                JOIN lab_tests t ON o.test_id = t.id
                WHERE o.id = :id";
        $order = db_get_row($query, [':id' => $test_id]);
        if (!$order) {
            $error = "Order not found";
            $action = 'list'; // Fallback to list
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        $action = 'list'; // Fallback to list
    }
}

// Handle delete test action
if ($action === 'delete_test' && $test_id > 0) {
    try {
        // Check if test is in use
        $orders_count = db_get_row("SELECT COUNT(*) as count FROM lab_orders WHERE test_id = :id", [':id' => $test_id]);
        if ($orders_count && $orders_count['count'] > 0) {
            $error = "Cannot delete test: it has associated orders";
        } else {
            db_query("DELETE FROM lab_tests WHERE id = :id", [':id' => $test_id]);
            $success = "Test deleted successfully";
        }
        // Redirect to test list
        header("Location: laboratory.php?action=tests");
        exit;
    } catch (PDOException $e) {
        $error = "Delete failed: " . $e->getMessage();
    }
}

// Handle delete order action
if ($action === 'delete_order' && $test_id > 0) {
    try {
        db_query("DELETE FROM lab_orders WHERE id = :id", [':id' => $test_id]);
        $success = "Order deleted successfully";
        // Redirect to order list
        header("Location: laboratory.php");
        exit;
    } catch (PDOException $e) {
        $error = "Delete failed: " . $e->getMessage();
    }
}

// Page title based on action
$page_title = "Laboratory";
if ($action === 'new_order') {
    $page_title = "New Test Order";
} elseif ($action === 'update_results') {
    $page_title = "Update Test Results";
} elseif ($action === 'tests') {
    $page_title = "Available Tests";
} elseif ($action === 'new_test') {
    $page_title = "Add New Test";
} elseif ($action === 'edit_test') {
    $page_title = "Edit Test";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - AKIRA HOSPITAL</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #0c4c8a;
            --secondary-color: #10aade;
            --accent-color: #21c286;
            --text-color: #333333;
            --light-bg: #f8f9fa;
        }
        
        body {
            background-color: var(--light-bg);
            color: var(--text-color);
        }
        
        .sidebar {
            background: linear-gradient(to bottom, #1e293b, #0c4c8a);
            color: white;
            min-height: 100vh;
            padding-top: 1rem;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.5rem 1rem;
            margin-bottom: 0.25rem;
            border-radius: 5px;
        }
        
        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            font-weight: 500;
        }
        
        .logo-container {
            padding: 1rem;
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .hospital-logo {
            color: #10b981;
            font-weight: bold;
            font-size: 1.75rem;
            margin-bottom: 0;
        }
        
        .topbar {
            background-color: white;
            border-bottom: 1px solid #dee2e6;
            padding: 0.75rem 1.5rem;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            position: relative;
            padding-left: 15px;
        }
        
        .section-title::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background-color: var(--primary-color);
            border-radius: 4px;
        }
        
        .user-welcome {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .test-card {
            transition: transform 0.2s;
            border-left: 4px solid var(--primary-color);
        }
        
        .test-card:hover {
            transform: translateY(-5px);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #083b6f;
            border-color: #083b6f;
        }
        
        .status-pending {
            background-color: #ffc107;
            color: #212529;
        }
        
        .status-in-progress {
            background-color: #17a2b8;
            color: white;
        }
        
        .status-completed {
            background-color: #28a745;
            color: white;
        }
        
        .status-cancelled {
            background-color: #dc3545;
            color: white;
        }
        
        .category-badge {
            background-color: var(--secondary-color);
            color: white;
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
        }
        
        .price-tag {
            font-weight: bold;
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="logo-container">
                    <h1 class="hospital-logo">AKIRA</h1>
                    <h1 class="hospital-logo">HOSPITAL</h1>
                </div>
                
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="patients.php">
                            <i class="fas fa-user-injured me-2"></i> Patients
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="doctors.php">
                            <i class="fas fa-user-md me-2"></i> Doctors
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="appointments.php">
                            <i class="fas fa-calendar-check me-2"></i> Appointments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pharmacy.php">
                            <i class="fas fa-pills me-2"></i> Pharmacy
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="laboratory.php">
                            <i class="fas fa-flask me-2"></i> Laboratory
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="billing.php">
                            <i class="fas fa-file-invoice-dollar me-2"></i> Billing
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i> Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog me-2"></i> Settings
                        </a>
                    </li>
                    <li class="nav-item mt-5">
                        <a class="nav-link text-danger" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Top Navigation Bar -->
                <div class="topbar d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="mb-0"><?php echo $page_title; ?></h4>
                    </div>
                    <div class="user-welcome">
                        Welcome, <span class="user-name"><?php echo htmlspecialchars($admin_name); ?></span> (<?php echo htmlspecialchars($admin_role); ?>)
                    </div>
                </div>
                
                <!-- Content based on action -->
                <div class="container-fluid">
                    <!-- Display alerts -->
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Different content based on action -->
                    <?php if ($action === 'list'): ?>
                        <!-- Test Orders List View -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Laboratory Test Orders</h5>
                                <div>
                                    <a href="laboratory.php?action=tests" class="btn btn-outline-secondary btn-sm me-2">
                                        <i class="fas fa-vial me-1"></i> Manage Tests
                                    </a>
                                    <a href="laboratory.php?action=new_order" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus me-1"></i> New Test Order
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($test_orders)): ?>
                                    <div class="alert alert-info">
                                        No test orders found.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Patient</th>
                                                    <th>Test</th>
                                                    <th>Date & Time</th>
                                                    <th>Referred By</th>
                                                    <th>Price (₹)</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($test_orders as $order): ?>
                                                    <tr>
                                                        <td><?php echo $order['id']; ?></td>
                                                        <td><?php echo htmlspecialchars($order['patient_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($order['test_name']); ?></td>
                                                        <td>
                                                            <?php echo date('d-m-Y', strtotime($order['order_date'])); ?>
                                                            <span class="text-muted d-block small"><?php echo date('h:i A', strtotime($order['order_time'])); ?></span>
                                                        </td>
                                                        <td><?php echo $order['doctor_name'] ? htmlspecialchars($order['doctor_name']) : '<span class="text-muted">Self</span>'; ?></td>
                                                        <td class="price-tag">₹<?php echo number_format($order['price'], 2); ?></td>
                                                        <td>
                                                            <?php
                                                            $statusClass = 'status-pending';
                                                            switch ($order['status']) {
                                                                case 'in-progress':
                                                                    $statusClass = 'status-in-progress';
                                                                    break;
                                                                case 'completed':
                                                                    $statusClass = 'status-completed';
                                                                    break;
                                                                case 'cancelled':
                                                                    $statusClass = 'status-cancelled';
                                                                    break;
                                                            }
                                                            ?>
                                                            <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($order['status']); ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <?php if ($order['status'] !== 'completed' && $order['status'] !== 'cancelled'): ?>
                                                                    <a href="laboratory.php?action=update_results&id=<?php echo $order['id']; ?>" class="btn btn-primary">
                                                                        <i class="fas fa-edit"></i> Results
                                                                    </a>
                                                                <?php else: ?>
                                                                    <a href="#" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#resultsModal<?php echo $order['id']; ?>">
                                                                        <i class="fas fa-eye"></i> View
                                                                    </a>
                                                                    
                                                                    <!-- Results Modal -->
                                                                    <div class="modal fade" id="resultsModal<?php echo $order['id']; ?>" tabindex="-1" aria-hidden="true">
                                                                        <div class="modal-dialog modal-lg">
                                                                            <div class="modal-content">
                                                                                <div class="modal-header">
                                                                                    <h5 class="modal-title">Test Results - <?php echo htmlspecialchars($order['test_name']); ?></h5>
                                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                                </div>
                                                                                <div class="modal-body">
                                                                                    <div class="mb-3">
                                                                                        <h6>Patient: <?php echo htmlspecialchars($order['patient_name']); ?></h6>
                                                                                        <p class="mb-1">Test Date: <?php echo date('d-m-Y', strtotime($order['order_date'])); ?></p>
                                                                                        <p>Completed: <?php echo $order['completed_at'] ? date('d-m-Y h:i A', strtotime($order['completed_at'])) : 'N/A'; ?></p>
                                                                                    </div>
                                                                                    <div class="card">
                                                                                        <div class="card-body">
                                                                                            <h6 class="card-title">Results:</h6>
                                                                                            <div class="card-text">
                                                                                                <?php if (!empty($order['results'])): ?>
                                                                                                    <pre class="p-3 bg-light"><?php echo htmlspecialchars($order['results']); ?></pre>
                                                                                                <?php else: ?>
                                                                                                    <p class="text-muted">No detailed results available.</p>
                                                                                                <?php endif; ?>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="modal-footer">
                                                                                    <button type="button" class="btn btn-primary" onclick="window.print()">Print Results</button>
                                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($order['status'] !== 'completed'): ?>
                                                                    <a href="javascript:void(0)" onclick="confirmDeleteOrder(<?php echo $order['id']; ?>)" class="btn btn-danger">
                                                                        <i class="fas fa-trash"></i>
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php elseif ($action === 'tests'): ?>
                        <!-- Available Tests List View -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Available Laboratory Tests</h5>
                                <div>
                                    <a href="laboratory.php" class="btn btn-outline-secondary btn-sm me-2">
                                        <i class="fas fa-clipboard-list me-1"></i> Test Orders
                                    </a>
                                    <a href="laboratory.php?action=new_test" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus me-1"></i> Add New Test
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($available_tests)): ?>
                                    <div class="alert alert-info">
                                        No laboratory tests available.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Test Name</th>
                                                    <th>Category</th>
                                                    <th>Duration</th>
                                                    <th>Price (₹)</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($available_tests as $test): ?>
                                                    <tr>
                                                        <td><?php echo $test['id']; ?></td>
                                                        <td><?php echo htmlspecialchars($test['name']); ?></td>
                                                        <td>
                                                            <?php if (!empty($test['category_name'])): ?>
                                                                <span class="category-badge"><?php echo htmlspecialchars($test['category_name']); ?></span>
                                                            <?php else: ?>
                                                                <span class="text-muted">Uncategorized</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($test['duration_minutes']): ?>
                                                                <?php 
                                                                $hours = floor($test['duration_minutes'] / 60);
                                                                $minutes = $test['duration_minutes'] % 60;
                                                                if ($hours > 0) {
                                                                    echo $hours . ' hr ';
                                                                }
                                                                if ($minutes > 0 || $hours == 0) {
                                                                    echo $minutes . ' min';
                                                                }
                                                                ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">Varies</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="price-tag">₹<?php echo number_format($test['price'], 2); ?></td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <a href="laboratory.php?action=edit_test&id=<?php echo $test['id']; ?>" class="btn btn-primary">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                                <a href="javascript:void(0)" onclick="confirmDeleteTest(<?php echo $test['id']; ?>)" class="btn btn-danger">
                                                                    <i class="fas fa-trash"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php elseif ($action === 'new_test' || $action === 'edit_test'): ?>
                        <!-- Add/Edit Test Form -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><?php echo $action === 'new_test' ? 'Add New Laboratory Test' : 'Edit Laboratory Test'; ?></h5>
                            </div>
                            <div class="card-body">
                                <form method="post" action="<?php echo $action === 'edit_test' ? "laboratory.php?action=edit_test&id={$test_id}" : "laboratory.php?action=new_test"; ?>">
                                    <input type="hidden" name="test_id" value="<?php echo $test_id; ?>">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="name" class="form-label">Test Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="name" name="name" value="<?php echo $test['name'] ?? ''; ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="category_id" class="form-label">Category</label>
                                            <select class="form-select" id="category_id" name="category_id">
                                                <option value="">Select Category</option>
                                                <?php foreach ($test_categories as $cat): ?>
                                                    <option value="<?php echo $cat['id']; ?>" <?php echo (isset($test['category_id']) && $test['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($cat['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="price" class="form-label">Price (₹) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" value="<?php echo $test['price'] ?? '0.00'; ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="duration_minutes" class="form-label">Duration (minutes)</label>
                                            <input type="number" step="1" min="0" class="form-control" id="duration_minutes" name="duration_minutes" value="<?php echo $test['duration_minutes'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo $test['description'] ?? ''; ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="requirements" class="form-label">Patient Requirements/Preparations</label>
                                        <textarea class="form-control" id="requirements" name="requirements" rows="3"><?php echo $test['requirements'] ?? ''; ?></textarea>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between">
                                        <a href="laboratory.php?action=tests" class="btn btn-secondary">Cancel</a>
                                        <button type="submit" name="save_test" class="btn btn-primary">Save Test</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php elseif ($action === 'new_order'): ?>
                        <!-- New Test Order Form -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Place New Test Order</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" action="laboratory.php?action=new_order">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="patient_id" class="form-label">Patient <span class="text-danger">*</span></label>
                                            <select class="form-select" id="patient_id" name="patient_id" required>
                                                <option value="">Select Patient</option>
                                                <?php foreach ($patients as $patient): ?>
                                                    <option value="<?php echo $patient['id']; ?>" <?php echo ($patient_id == $patient['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($patient['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="doctor_id" class="form-label">Referred By</label>
                                            <select class="form-select" id="doctor_id" name="doctor_id">
                                                <option value="">Self</option>
                                                <?php foreach ($doctors as $doctor): ?>
                                                    <option value="<?php echo $doctor['id']; ?>">
                                                        <?php echo htmlspecialchars($doctor['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="test_id" class="form-label">Test <span class="text-danger">*</span></label>
                                            <select class="form-select" id="test_id" name="test_id" required>
                                                <option value="">Select Test</option>
                                                <?php foreach ($available_tests as $test): ?>
                                                    <option value="<?php echo $test['id']; ?>" data-price="<?php echo $test['price']; ?>">
                                                        <?php echo htmlspecialchars($test['name']) . ' - ₹' . number_format($test['price'], 2); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label for="order_date" class="form-label">Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="order_date" name="order_date" value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label for="order_time" class="form-label">Time <span class="text-danger">*</span></label>
                                            <input type="time" class="form-control" id="order_time" name="order_time" value="<?php echo date('H:i'); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Notes</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                                    </div>
                                    
                                    <div class="card mb-3 bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">Order Summary</h6>
                                            <div class="d-flex justify-content-between">
                                                <span>Test Fee:</span>
                                                <span class="price-tag" id="test_price">₹0.00</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between">
                                        <a href="laboratory.php" class="btn btn-secondary">Cancel</a>
                                        <button type="submit" name="place_order" class="btn btn-primary">Place Order</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php elseif ($action === 'update_results'): ?>
                        <!-- Update Test Results Form -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Update Test Results</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($order): ?>
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <h6>Order Details</h6>
                                            <p class="mb-1"><strong>Patient:</strong> <?php echo htmlspecialchars($order['patient_name']); ?></p>
                                            <p class="mb-1"><strong>Test:</strong> <?php echo htmlspecialchars($order['test_name']); ?></p>
                                            <p class="mb-1"><strong>Date:</strong> <?php echo date('d-m-Y', strtotime($order['order_date'])); ?></p>
                                            <p><strong>Time:</strong> <?php echo date('h:i A', strtotime($order['order_time'])); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>Current Status</h6>
                                            <p class="mb-1"><strong>Status:</strong> 
                                                <span class="badge status-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span>
                                            </p>
                                            <p class="mb-1"><strong>Referred By:</strong> <?php echo $order['doctor_name'] ? htmlspecialchars($order['doctor_name']) : 'Self'; ?></p>
                                            <p><strong>Price:</strong> ₹<?php echo number_format($order['price'], 2); ?></p>
                                        </div>
                                    </div>
                                    
                                    <form method="post" action="laboratory.php?action=update_results&id=<?php echo $order['id']; ?>">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        
                                        <div class="mb-3">
                                            <label for="status" class="form-label">Update Status <span class="text-danger">*</span></label>
                                            <select class="form-select" id="status" name="status" required>
                                                <option value="pending" <?php echo ($order['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                <option value="in-progress" <?php echo ($order['status'] == 'in-progress') ? 'selected' : ''; ?>>In Progress</option>
                                                <option value="completed" <?php echo ($order['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo ($order['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="results" class="form-label">Test Results</label>
                                            <textarea class="form-control" id="results" name="results" rows="10"><?php echo $order['results'] ?? ''; ?></textarea>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between">
                                            <a href="laboratory.php" class="btn btn-secondary">Cancel</a>
                                            <button type="submit" name="update_results" class="btn btn-primary">Update Results</button>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <div class="alert alert-danger">
                                        Order not found.
                                    </div>
                                    <a href="laboratory.php" class="btn btn-secondary">Back to Orders</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript for confirmation dialogs -->
    <script>
        function confirmDeleteTest(id) {
            if (confirm('Are you sure you want to delete this test? This action cannot be undone.')) {
                window.location.href = 'laboratory.php?action=delete_test&id=' + id;
            }
        }
        
        function confirmDeleteOrder(id) {
            if (confirm('Are you sure you want to delete this order? This action cannot be undone.')) {
                window.location.href = 'laboratory.php?action=delete_order&id=' + id;
            }
        }
        
        // Update test price in order form
        document.addEventListener('DOMContentLoaded', function() {
            const testSelect = document.getElementById('test_id');
            const priceDisplay = document.getElementById('test_price');
            
            if (testSelect && priceDisplay) {
                testSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const price = selectedOption.getAttribute('data-price') || 0;
                    priceDisplay.textContent = '₹' + parseFloat(price).toFixed(2);
                });
            }
        });
    </script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>