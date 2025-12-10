<?php
/**
 * AKIRA HOSPITAL Management System
 * Dashboard Page for XAMPP PostgreSQL
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

// Get counts from database (with error handling)
try {
    // Count patients
    $patient_count = 0;
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'patients'")) {
        $result = db_get_row("SELECT COUNT(*) as count FROM patients");
        $patient_count = $result['count'] ?? 0;
    }
    
    // Count doctors
    $doctor_count = 0;
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'doctors'")) {
        $result = db_get_row("SELECT COUNT(*) as count FROM doctors");
        $doctor_count = $result['count'] ?? 0;
    }
    
    // Count appointments
    $appointment_count = 0;
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'appointments'")) {
        $result = db_get_row("SELECT COUNT(*) as count FROM appointments");
        $appointment_count = $result['count'] ?? 0;
    }
    
    // Count staff
    $staff_count = 0;
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'users'")) {
        $result = db_get_row("SELECT COUNT(*) as count FROM users WHERE role != 'admin'");
        $staff_count = $result['count'] ?? 0;
    }
} catch (PDOException $e) {
    error_log("Error fetching dashboard data: " . $e->getMessage());
    // Set defaults if database queries fail
    $patient_count = 0;
    $doctor_count = 0;
    $appointment_count = 0;
    $staff_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AKIRA HOSPITAL</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
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
        
        .stat-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }
        
        .stat-patients {
            background-color: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        .stat-doctors {
            background-color: rgba(14, 165, 233, 0.1);
            color: #0ea5e9;
        }
        
        .stat-appointments {
            background-color: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }
        
        .stat-staff {
            background-color: rgba(99, 102, 241, 0.1);
            color: #6366f1;
        }
        
        .stat-number {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0;
        }
        
        .stat-label {
            color: #6c757d;
            margin-bottom: 0;
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
                        <a class="nav-link active" href="dashboard.php">
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
                        <a class="nav-link" href="laboratory.php">
                            <i class="fas fa-file-medical-alt me-2"></i> Laboratory
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
                        <h4 class="mb-0">Dashboard</h4>
                    </div>
                    <div class="user-welcome">
                        Welcome, <span class="user-name"><?php echo htmlspecialchars($admin_name); ?></span> (<?php echo htmlspecialchars($admin_role); ?>)
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-6 col-xl-3 mb-4">
                        <div class="stat-card d-flex align-items-center">
                            <div class="stat-icon stat-patients">
                                <i class="fas fa-user-injured"></i>
                            </div>
                            <div>
                                <p class="stat-number"><?php echo number_format($patient_count); ?></p>
                                <p class="stat-label">Patients</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-xl-3 mb-4">
                        <div class="stat-card d-flex align-items-center">
                            <div class="stat-icon stat-doctors">
                                <i class="fas fa-user-md"></i>
                            </div>
                            <div>
                                <p class="stat-number"><?php echo number_format($doctor_count); ?></p>
                                <p class="stat-label">Doctors</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-xl-3 mb-4">
                        <div class="stat-card d-flex align-items-center">
                            <div class="stat-icon stat-appointments">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div>
                                <p class="stat-number"><?php echo number_format($appointment_count); ?></p>
                                <p class="stat-label">Appointments</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-xl-3 mb-4">
                        <div class="stat-card d-flex align-items-center">
                            <div class="stat-icon stat-staff">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <p class="stat-number"><?php echo number_format($staff_count); ?></p>
                                <p class="stat-label">Staff Members</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Main Content Sections -->
                <div class="row">
                    <div class="col-lg-8 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="section-title">Hospital Overview</h5>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6 class="card-title"><i class="fas fa-bed text-primary me-2"></i> Bed Occupancy</h6>
                                                <div class="progress mb-2" style="height: 10px;">
                                                    <div class="progress-bar bg-primary" role="progressbar" style="width: 75%;" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <p class="card-text small text-muted">75% of beds currently occupied</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6 class="card-title"><i class="fas fa-user-md text-success me-2"></i> Staff Attendance</h6>
                                                <div class="progress mb-2" style="height: 10px;">
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: 92%;" aria-valuenow="92" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <p class="card-text small text-muted">92% staff present today</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6 class="card-title"><i class="fas fa-ambulance text-danger me-2"></i> Ambulance Status</h6>
                                                <div class="progress mb-2" style="height: 10px;">
                                                    <div class="progress-bar bg-danger" role="progressbar" style="width: 60%;" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <p class="card-text small text-muted">60% of ambulances available</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6 class="card-title"><i class="fas fa-calendar-check text-info me-2"></i> Today's Schedule</h6>
                                                <p class="card-text mb-1">
                                                    <span class="badge bg-primary me-1">15</span> Appointments
                                                </p>
                                                <p class="card-text small text-muted">8 completed, 7 pending</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="section-title">Quick Links</h5>
                                
                                <div class="list-group">
                                    <a href="appointments.php?action=new" class="list-group-item list-group-item-action d-flex align-items-center">
                                        <i class="fas fa-calendar-plus me-3 text-primary"></i>
                                        <span>New Appointment</span>
                                    </a>
                                    <a href="patients.php?action=new" class="list-group-item list-group-item-action d-flex align-items-center">
                                        <i class="fas fa-user-plus me-3 text-success"></i>
                                        <span>Register Patient</span>
                                    </a>
                                    <a href="pharmacy.php" class="list-group-item list-group-item-action d-flex align-items-center">
                                        <i class="fas fa-pills me-3 text-warning"></i>
                                        <span>Pharmacy Inventory</span>
                                    </a>
                                    <a href="laboratory.php?action=results" class="list-group-item list-group-item-action d-flex align-items-center">
                                        <i class="fas fa-file-medical me-3 text-info"></i>
                                        <span>Lab Results</span>
                                    </a>
                                    <a href="billing.php?action=new" class="list-group-item list-group-item-action d-flex align-items-center">
                                        <i class="fas fa-file-invoice me-3 text-danger"></i>
                                        <span>Generate Invoice</span>
                                    </a>
                                </div>
                                
                                <h5 class="section-title mt-4">System Status</h5>
                                <div class="p-3 bg-light rounded">
                                    <p class="mb-2"><i class="fas fa-database me-2 text-success"></i> Database: <span class="badge bg-success">Connected</span></p>
                                    <p class="mb-2"><i class="fas fa-server me-2 text-success"></i> Server: <span class="badge bg-success">Online</span></p>
                                    <p class="mb-0"><i class="fas fa-clock me-2 text-success"></i> Last Update: <span class="text-muted"><?php echo date('M d, Y H:i'); ?></span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>