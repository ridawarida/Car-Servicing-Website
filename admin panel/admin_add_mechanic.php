<?php
session_start();
include '../components/connect.php';

if (!isset($_SESSION['admin_email'])) {
    header('Location: admin panel/login.php');
    exit();
}

if (isset($_POST['add_mechanic'])) {
    $mechanic_name = filter_var($_POST['mechanic_name'], FILTER_SANITIZE_STRING);
    $max_appointments = filter_var($_POST['max_appointments'], FILTER_SANITIZE_NUMBER_INT);
    
    $errors = [];
    
   if (empty($mechanic_name)) {
        $errors[] = "Mechanic name is required";
    }
    
    if (empty($max_appointments) || $max_appointments < 1) {
        $errors[] = "Max appointments must be at least 1";
    }
    $check = $conn->prepare("SELECT * FROM mechanics WHERE mechanic_name = ?");
    $check->execute([$mechanic_name]);
    
    if ($check->rowCount() > 0) {
        $errors[] = "A mechanic with this name already exists";
    }
    
    if (empty($errors)) {
        $insert = $conn->prepare("INSERT INTO mechanics (mechanic_name, max_appointments) VALUES (?, ?)");
        
        if ($insert->execute([$mechanic_name, $max_appointments])) {
            $success = "Mechanic added successfully!";
        } else {
            $error = "Failed to add mechanic. Please try again.";
        }
    }
}


if (isset($_POST['delete_mechanic'])) {
    $mechanic_id = $_POST['mechanic_id'];
    
    $check_appointments = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE mechanic_id = ?");
    $check_appointments->execute([$mechanic_id]);
    $appointment_count = $check_appointments->fetch(PDO::FETCH_ASSOC);
    
    if ($appointment_count['count'] > 0) {
        $error = "Cannot delete mechanic with existing appointments. Please reassign or cancel their appointments first.";
    } else {
        $delete = $conn->prepare("DELETE FROM mechanics WHERE mechanic_id = ?");
        if ($delete->execute([$mechanic_id])) {
            $success = "Mechanic deleted successfully!";
        } else {
            $error = "Failed to delete mechanic.";
        }
    }
}

$mechanics = $conn->query("SELECT * FROM mechanics ORDER BY mechanic_id");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Servicing - Manage Mechanics</title>
    <link rel="stylesheet" href="../css/admin_style.css">
    <style>
        
    </style>
</head>
<body>

<div class="manage-container">

    <div class="page-header">
        <h1>Manage Mechanics</h1>
        <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
    </div>
    
    <?php if (isset($success)): ?>
        <div class="message success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="message error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <div class="message error">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?php echo $err; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="form-section">
        <h2>Add New Mechanic</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="mechanic_name">Mechanic Name *</label>
                <input type="text" id="mechanic_name" name="mechanic_name" 
                       placeholder="Enter mechanic's full name" required>
            </div>
            
            <div class="form-group">
                <label for="max_appointments">Maximum Appointments Per Day *</label>
                <input type="number" id="max_appointments" name="max_appointments" 
                       min="1" max="10" value="4" required>
                <small style="color: #666;">Set between 1-10 appointments per day</small>
            </div>
            
            <button type="submit" name="add_mechanic" class="submit-btn">Add Mechanic</button>
        </form>
    </div>
    
    
    <div class="mechanics-list">
        <h2>Current Mechanics</h2>
        
        <?php if ($mechanics->rowCount() > 0): ?>
            <div class="mechanic-grid">
                <?php while($mechanic = $mechanics->fetch(PDO::FETCH_ASSOC)): 
                    $total_appts = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE mechanic_id = ?");
                    $total_appts->execute([$mechanic['mechanic_id']]);
                    $total = $total_appts->fetch(PDO::FETCH_ASSOC);
                    
                    $today_appts = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE mechanic_id = ? AND appointment_date = CURDATE()");
                    $today_appts->execute([$mechanic['mechanic_id']]);
                    $today = $today_appts->fetch(PDO::FETCH_ASSOC);
                ?>
                    <div class="mechanic-card">
                        <h3><?php echo htmlspecialchars($mechanic['mechanic_name']); ?></h3>
                        <div class="max-badge">Max: <?php echo $mechanic['max_appointments']; ?> per day</div>
                        <div class="appointment-count">
                            <div>Today: <?php echo $today['count']; ?> appointments</div>
                            <div>Total: <?php echo $total['count']; ?> appointments</div>
                        </div>
                        
                        <div class="mechanic-actions">
                    
                            <?php if ($total['count'] == 0): ?>

                                <form method="POST" style="flex: 1;" 
                                      onsubmit="return confirm('Are you sure you want to delete this mechanic?');">
                                    <input type="hidden" name="mechanic_id" value="<?php echo $mechanic['mechanic_id']; ?>">
                                    <button type="submit" name="delete_mechanic" class="delete-btn">Delete</button>
                                </form>

                            <?php else: ?>
                                <button class="delete-btn" style=" cursor: not-allowed;" 
                                        title="Cannot delete mechanic with existing appointments" disabled>
                                    Delete
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($total['count'] > 0): ?>
                            <small style="color: #ff8c42; display: block; margin-top: 10px;">
                                Has <?php echo $total['count']; ?> appointments - reassign before deleting
                            </small>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="info-message">
                No mechanics added yet. Use the form above to add your first mechanic.
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>