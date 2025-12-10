<?php
/**
 * AKIRA HOSPITAL Management System
 * Appointments Management Page
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
$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : null;
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : null;

// Get list of appointments
$appointments = [];
try {
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'appointments'")) {
        $sql = "SELECT a.*, p.name as patient_name, d.name as doctor_name 
                FROM appointments a 
                LEFT JOIN patients p ON a.patient_id = p.id
                LEFT JOIN doctors d ON a.doctor_id = d.id";
        
        $params = [];
        
        // Filter by doctor if specified
        if ($doctor_id) {
            $sql .= " WHERE a.doctor_id = :doctor_id";
            $params[':doctor_id'] = $doctor_id;
        } 
        // Filter by patient if specified
        elseif ($patient_id) {
            $sql .= " WHERE a.patient_id = :patient_id";
            $params[':patient_id'] = $patient_id;
        }
        
        $sql .= " ORDER BY a.appointment_date DESC";
        $appointments = db_get_rows($sql, $params);
    }
} catch (PDOException $e) {
    error_log("Error fetching appointments: " . $e->getMessage());
}

// Get patients for dropdowns
$patients = [];
try {
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'patients'")) {
        $patients = db_get_rows("SELECT id, name FROM patients ORDER BY name ASC");
    }
} catch (PDOException $e) {
    error_log("Error fetching patients: " . $e->getMessage());
}

// Get doctors for dropdowns
$doctors = [];
try {
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'doctors'")) {
        $doctors = db_get_rows("SELECT id, name, specialization FROM doctors ORDER BY name ASC");
    }
} catch (PDOException $e) {
    error_log("Error fetching doctors: " . $e->getMessage());
}

// Handle form submission for new/edit appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_appointment'])) {
    try {
        $patient_id = !empty($_POST['patient_id']) ? intval($_POST['patient_id']) : null;
        $doctor_id = !empty($_POST['doctor_id']) ? intval($_POST['doctor_id']) : null;
        $appointment_date = $_POST['appointment_date'] ?? '';
        $appointment_time = $_POST['appointment_time'] ?? '';
        $reason = $_POST['reason'] ?? '';
        $status = $_POST['status'] ?? 'scheduled';
        $notes = $_POST['notes'] ?? '';
        
        // Combine date and time
        $appointment_datetime = $appointment_date . ' ' . $appointment_time;
        
        // Validation
        if (empty($patient_id)) {
            $error = "Patient is required";
        } elseif (empty($doctor_id)) {
            $error = "Doctor is required";
        } elseif (empty($appointment_date)) {
            $error = "Appointment date is required";
        } else {
            if ($action === 'new') {
                // Insert new appointment
                $sql = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, reason, status, notes) 
                        VALUES (:patient_id, :doctor_id, :appointment_date, :reason, :status, :notes)";
                $params = [
                    ':patient_id' => $patient_id,
                    ':doctor_id' => $doctor_id,
                    ':appointment_date' => $appointment_datetime,
                    ':reason' => $reason,
                    ':status' => $status,
                    ':notes' => $notes
                ];
                db_query($sql, $params);
                $success = "Appointment scheduled successfully";
                // Redirect to appointment list
                header("Location: appointments.php");
                exit;
            } elseif ($action === 'edit' && $appointment_id > 0) {
                // Update existing appointment
                $sql = "UPDATE appointments SET patient_id = :patient_id, doctor_id = :doctor_id, 
                        appointment_date = :appointment_date, reason = :reason, 
                        status = :status, notes = :notes WHERE id = :id";
                $params = [
                    ':patient_id' => $patient_id,
                    ':doctor_id' => $doctor_id,
                    ':appointment_date' => $appointment_datetime,
                    ':reason' => $reason,
                    ':status' => $status,
                    ':notes' => $notes,
                    ':id' => $appointment_id
                ];
                db_query($sql, $params);
                $success = "Appointment updated successfully";
                // Redirect to appointment list
                header("Location: appointments.php");
                exit;
            }
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Get appointment data for edit/view
$appointment = null;
if (($action === 'edit' || $action === 'view') && $appointment_id > 0) {
    try {
        $sql = "SELECT a.*, p.name as patient_name, d.name as doctor_name 
                FROM appointments a 
                LEFT JOIN patients p ON a.patient_id = p.id
                LEFT JOIN doctors d ON a.doctor_id = d.id
                WHERE a.id = :id";
        $appointment = db_get_row($sql, [':id' => $appointment_id]);
        if (!$appointment) {
            $error = "Appointment not found";
            $action = 'list'; // Fallback to list
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        $action = 'list'; // Fallback to list
    }
}

// Handle delete action
if ($action === 'delete' && $appointment_id > 0) {
    try {
        db_query("DELETE FROM appointments WHERE id = :id", [':id' => $appointment_id]);
        $success = "Appointment deleted successfully";
        // Redirect to appointment list
        header("Location: appointments.php");
        exit;
    } catch (PDOException $e) {
        $error = "Delete failed: " . $e->getMessage();
    }
}

// Page title based on action
$page_title = "Appointments";
if ($action === 'new') {
    $page_title = "Schedule New Appointment";
} elseif ($action === 'edit') {
    $page_title = "Edit Appointment";
} elseif ($action === 'view') {
    $page_title = "Appointment Details";
} elseif ($doctor_id) {
    // Get doctor name
    try {
        $doctor = db_get_row("SELECT name FROM doctors WHERE id = :id", [':id' => $doctor_id]);
        if ($doctor) {
            $page_title = "Appointments - Dr. " . $doctor['name'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching doctor: " . $e->getMessage());
    }
} elseif ($patient_id) {
    // Get patient name
    try {
        $patient = db_get_row("SELECT name FROM patients WHERE id = :id", [':id' => $patient_id]);
        if ($patient) {
            $page_title = "Appointments - " . $patient['name'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching patient: " . $e->getMessage());
    }
}

// Format date/time function
function format_date($datetime, $format = 'd M Y, h:i A') {
    if (empty($datetime)) return 'N/A';
    $date = new DateTime($datetime);
    return $date->format($format);
}

// Format appointment status with color badge
function format_status($status) {
    $status_colors = [
        'scheduled' => 'primary',
        'completed' => 'success',
        'cancelled' => 'danger',
        'no_show' => 'warning'
    ];
    
    $status_labels = [
        'scheduled' => 'Scheduled',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No Show'
    ];
    
    $color = $status_colors[$status] ?? 'secondary';
    $label = $status_labels[$status] ?? ucfirst($status);
    
    return "<span class=\"badge bg-$color\">$label</span>";
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
        
        .appointment-card {
            transition: transform 0.2s;
            border-left: 4px solid var(--accent-color);
        }
        
        .appointment-card:hover {
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
                        <a class="nav-link active" href="appointments.php">
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
                        <!-- Appointments List View -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <?php if ($doctor_id): ?>
                                        Appointments for <?php echo htmlspecialchars($doctor['name'] ?? 'Doctor'); ?>
                                    <?php elseif ($patient_id): ?>
                                        Appointments for <?php echo htmlspecialchars($patient['name'] ?? 'Patient'); ?>
                                    <?php else: ?>
                                        All Appointments
                                    <?php endif; ?>
                                </h5>
                                <a href="appointments.php?action=new<?php echo $doctor_id ? '&doctor_id=' . $doctor_id : ''; ?><?php echo $patient_id ? '&patient_id=' . $patient_id : ''; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus me-1"></i> Schedule Appointment
                                </a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($appointments)): ?>
                                    <div class="alert alert-info">
                                        No appointments found.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>#ID</th>
                                                    <th>Patient</th>
                                                    <th>Doctor</th>
                                                    <th>Date & Time</th>
                                                    <th>Reason</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($appointments as $appt): ?>
                                                    <tr>
                                                        <td><?php echo $appt['id']; ?></td>
                                                        <td>
                                                            <a href="patients.php?action=view&id=<?php echo $appt['patient_id']; ?>">
                                                                <?php echo htmlspecialchars($appt['patient_name'] ?? 'N/A'); ?>
                                                            </a>
                                                        </td>
                                                        <td>
                                                            <a href="doctors.php?action=view&id=<?php echo $appt['doctor_id']; ?>">
                                                                <?php echo htmlspecialchars($appt['doctor_name'] ?? 'N/A'); ?>
                                                            </a>
                                                        </td>
                                                        <td><?php echo format_date($appt['appointment_date']); ?></td>
                                                        <td><?php echo htmlspecialchars($appt['reason'] ?? 'N/A'); ?></td>
                                                        <td><?php echo format_status($appt['status']); ?></td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <a href="appointments.php?action=view&id=<?php echo $appt['id']; ?>" class="btn btn-info">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <a href="appointments.php?action=edit&id=<?php echo $appt['id']; ?>" class="btn btn-primary">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                                <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $appt['id']; ?>)" class="btn btn-danger">
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
                    <?php elseif ($action === 'new' || $action === 'edit'): ?>
                        <!-- Appointment Add/Edit Form -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><?php echo $action === 'new' ? 'Schedule New Appointment' : 'Edit Appointment'; ?></h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="patient_id" class="form-label">Patient <span class="text-danger">*</span></label>
                                            <select class="form-select" id="patient_id" name="patient_id" required>
                                                <option value="">Select Patient</option>
                                                <?php foreach ($patients as $pt): ?>
                                                    <option value="<?php echo $pt['id']; ?>" 
                                                        <?php 
                                                        if ((isset($appointment['patient_id']) && $appointment['patient_id'] == $pt['id']) || 
                                                            ($patient_id && $patient_id == $pt['id'])) {
                                                            echo 'selected';
                                                        }
                                                        ?>>
                                                        <?php echo htmlspecialchars($pt['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="doctor_id" class="form-label">Doctor <span class="text-danger">*</span></label>
                                            <select class="form-select" id="doctor_id" name="doctor_id" required>
                                                <option value="">Select Doctor</option>
                                                <?php foreach ($doctors as $dr): ?>
                                                    <option value="<?php echo $dr['id']; ?>" 
                                                        <?php 
                                                        if ((isset($appointment['doctor_id']) && $appointment['doctor_id'] == $dr['id']) || 
                                                            ($doctor_id && $doctor_id == $dr['id'])) {
                                                            echo 'selected';
                                                        }
                                                        ?>>
                                                        <?php 
                                                        echo htmlspecialchars($dr['name']);
                                                        if (!empty($dr['specialization'])) {
                                                            echo ' (' . htmlspecialchars($dr['specialization']) . ')';
                                                        }
                                                        ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <?php 
                                        // Parse appointment date and time if editing
                                        $appt_date = '';
                                        $appt_time = '';
                                        if (isset($appointment['appointment_date'])) {
                                            $dt = new DateTime($appointment['appointment_date']);
                                            $appt_date = $dt->format('Y-m-d');
                                            $appt_time = $dt->format('H:i');
                                        }
                                        ?>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="appointment_date" class="form-label">Appointment Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="appointment_date" name="appointment_date" required
                                                value="<?php echo $appt_date; ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="appointment_time" class="form-label">Appointment Time <span class="text-danger">*</span></label>
                                            <input type="time" class="form-control" id="appointment_time" name="appointment_time" required
                                                value="<?php echo $appt_time ?: '09:00'; ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="reason" class="form-label">Reason <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="reason" name="reason" required
                                                value="<?php echo isset($appointment['reason']) ? htmlspecialchars($appointment['reason']) : ''; ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="status" class="form-label">Status</label>
                                            <select class="form-select" id="status" name="status">
                                                <option value="scheduled" <?php echo (isset($appointment['status']) && $appointment['status'] === 'scheduled') || !isset($appointment['status']) ? 'selected' : ''; ?>>Scheduled</option>
                                                <option value="completed" <?php echo (isset($appointment['status']) && $appointment['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo (isset($appointment['status']) && $appointment['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                                <option value="no_show" <?php echo (isset($appointment['status']) && $appointment['status'] === 'no_show') ? 'selected' : ''; ?>>No Show</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-12 mb-3">
                                            <label for="notes" class="form-label">Notes</label>
                                            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo isset($appointment['notes']) ? htmlspecialchars($appointment['notes']) : ''; ?></textarea>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3 d-flex justify-content-between">
                                        <a href="appointments.php<?php echo $doctor_id ? '?doctor_id=' . $doctor_id : ''; ?><?php echo $patient_id ? '?patient_id=' . $patient_id : ''; ?>" class="btn btn-secondary">Cancel</a>
                                        <button type="submit" name="save_appointment" class="btn btn-primary">
                                            <?php echo $action === 'new' ? 'Schedule Appointment' : 'Update Appointment'; ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php elseif ($action === 'view' && $appointment): ?>
                        <!-- Appointment Details View -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Appointment Details</h5>
                                <div>
                                    <a href="appointments.php?action=edit&id=<?php echo $appointment['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-edit me-1"></i> Edit
                                    </a>
                                    <a href="appointments.php" class="btn btn-secondary btn-sm ms-2">
                                        <i class="fas fa-arrow-left me-1"></i> Back to List
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-muted mb-3">Appointment Information</h6>
                                        <table class="table">
                                            <tr>
                                                <th width="30%">Appointment ID</th>
                                                <td><?php echo $appointment['id']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Patient</th>
                                                <td>
                                                    <a href="patients.php?action=view&id=<?php echo $appointment['patient_id']; ?>">
                                                        <?php echo htmlspecialchars($appointment['patient_name'] ?? 'N/A'); ?>
                                                    </a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Doctor</th>
                                                <td>
                                                    <a href="doctors.php?action=view&id=<?php echo $appointment['doctor_id']; ?>">
                                                        <?php echo htmlspecialchars($appointment['doctor_name'] ?? 'N/A'); ?>
                                                    </a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Date & Time</th>
                                                <td><?php echo format_date($appointment['appointment_date']); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Status</th>
                                                <td><?php echo format_status($appointment['status']); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted mb-3">Additional Information</h6>
                                        <table class="table">
                                            <tr>
                                                <th width="30%">Reason</th>
                                                <td><?php echo htmlspecialchars($appointment['reason'] ?? 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Notes</th>
                                                <td><?php echo nl2br(htmlspecialchars($appointment['notes'] ?? 'N/A')); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                
                                <!-- Additional Actions -->
                                <div class="mt-4">
                                    <h6 class="text-muted mb-3">Actions</h6>
                                    <div class="d-flex gap-2">
                                        <?php if ($appointment['status'] === 'scheduled'): ?>
                                            <a href="prescriptions.php?action=new&appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-success">
                                                <i class="fas fa-prescription me-1"></i> Create Prescription
                                            </a>
                                            <a href="billing.php?action=new&appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-warning">
                                                <i class="fas fa-file-invoice-dollar me-1"></i> Generate Invoice
                                            </a>
                                            <a href="laboratory.php?action=request&appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-info">
                                                <i class="fas fa-flask me-1"></i> Request Lab Test
                                            </a>
                                        <?php endif; ?>
                                        
                                        <!-- Print Button -->
                                        <button onclick="window.print()" class="btn btn-outline-secondary">
                                            <i class="fas fa-print me-1"></i> Print Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this appointment? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(appointmentId) {
            const deleteBtn = document.getElementById('confirmDeleteBtn');
            deleteBtn.href = `appointments.php?action=delete&id=${appointmentId}`;
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>