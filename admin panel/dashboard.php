<?php
session_start();
include '../components/connect.php';
if (!isset($_SESSION['admin_email'])) {
    header('Location: admin panel/login.php');
    exit();
}

function reassignExcessAppointments($conn, $mechanic_id, $target_date, $current_count, $max_allowed) {
    if ($current_count<=$max_allowed) {
        return true; 
    }
    
    $excess = $current_count-$max_allowed;
    $reassigned = 0;
    
    $get_excess = $conn->prepare("SELECT appointment_id FROM appointments WHERE mechanic_id = ? AND appointment_date = ? ORDER BY created_at DESC LIMIT ?");
    $get_excess->execute([$mechanic_id, $target_date, $excess]);
    $excess_appointments = $get_excess->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($excess_appointments as $appt) {
        $find_available = $conn->prepare("SELECT m.mechanic_id, m.mechanic_name, (SELECT COUNT(*) FROM appointments a2 WHERE a2.mechanic_id = m.mechanic_id AND a2.appointment_date = ?) as booked
            FROM mechanics m WHERE m.mechanic_id != ? HAVING booked < m.max_appointments ORDER BY booked ASC LIMIT 1");
        $find_available->execute([$target_date, $mechanic_id]);
        $available = $find_available->fetch(PDO::FETCH_ASSOC);
        
        if ($available) {
            $reassign = $conn->prepare("UPDATE appointments SET mechanic_id = ? WHERE appointment_id = ?");
            $reassign->execute([$available['mechanic_id'], $appt['appointment_id']]);
            $reassigned++;
        }
    }
    
    return $reassigned;
}

if (isset($_POST['update_max_limit'])) {
    $mechanic_id = $_POST['mechanic_id'];
    $new_max = $_POST['max_appointments'];
    $target_date = $_POST['limit_date'];
    
    $conn->beginTransaction();
    
    try {
        $get_mechanic = $conn->prepare("SELECT * FROM mechanics WHERE mechanic_id = ?");
        $get_mechanic->execute([$mechanic_id]);
        $mechanic = $get_mechanic->fetch(PDO::FETCH_ASSOC);
        $old_max = $mechanic['max_appointments'];
        
        $update_max = $conn->prepare("UPDATE mechanics SET max_appointments = ? WHERE mechanic_id = ?");
        $update_max->execute([$new_max, $mechanic_id]);
        
        
        $check_count = $conn->prepare("SELECT COUNT(*) as booked FROM appointments WHERE mechanic_id = ? AND appointment_date = ?");
        $check_count->execute([$mechanic_id, $target_date]);
        $current = $check_count->fetch(PDO::FETCH_ASSOC);
        
        
        if ($current['booked'] > $new_max) {
            $reassigned = reassignExcessAppointments($conn, $mechanic_id, $target_date, $current['booked'], $new_max);
            $conn->commit();
            $success = "Max limit updated from $old_max to $new_max. $reassigned appointments were reassigned to other mechanics.";
        } else {
            $conn->commit();
            $success = "Max limit updated from $old_max to $new_max successfully!";
        }
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Error updating limit: " . $e->getMessage();
    }
}


if (isset($_POST['update_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    $new_date = $_POST['appointment_date'];
    $new_mechanic = $_POST['mechanic_id'];
    
    
    $get_max = $conn->prepare("SELECT max_appointments FROM mechanics WHERE mechanic_id = ?");
    $get_max->execute([$new_mechanic]);
    $max_limit = $get_max->fetchColumn();
    
    $check = $conn->prepare("SELECT COUNT(*) as booked FROM appointments 
                             WHERE mechanic_id = ? AND appointment_date = ? 
                             AND appointment_id != ?");
    $check->execute([$new_mechanic, $new_date, $appointment_id]);
    $result = $check->fetch(PDO::FETCH_ASSOC);
    
    if ($result['booked'] < $max_limit) {
        $update = $conn->prepare("UPDATE appointments SET appointment_date = ?, mechanic_id = ? 
                                  WHERE appointment_id = ?");
        $update->execute([$new_date, $new_mechanic, $appointment_id]);
        $success = "Appointment updated successfully!";
    } else {
        $error = "Selected mechanic is fully booked on this date! (Max: $max_limit)";
    }
}


$appointments = $conn->query("SELECT a.*, m.mechanic_name, s.service_name, s.price 
                              FROM appointments a 
                              JOIN mechanics m ON a.mechanic_id = m.mechanic_id 
                              LEFT JOIN services s ON a.service_id = s.service_id 
                              ORDER BY a.appointment_date DESC");
$mechanics = $conn->query("SELECT * FROM mechanics ORDER BY mechanic_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Car Servicing - Admin Dashboard</title>
    <link rel="stylesheet" href="../css/admin_style.css">
    <style>
        
    </style>
</head>
<body>

<div class="dashboard-container">
    
    
    <div class="header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <img src="https://media.istockphoto.com/id/1936226112/photo/african-man-mechanic-in-uniform-at-the-car-repair-station-portrait.jpg?s=612x612&w=0&k=20&c=PT6yUCt3KXfpTjfuCgyy4j5AGoaX9DmJC-C0E4ShtNM=" 
                 alt="Mechanic" 
                 style="height: 200px; width: 300px; border-radius: 10px; border: 3px solid white;">
            <h2>Welcome, Dear Admin</h2>
        </div>
        
        <div class="header-buttons" style="display: flex; gap: 15px; margin-top: 20px;">
            <a href="admin_add_mechanic.php" class="btn">Manage Mechanics</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    
    
    <?php if (isset($success)): ?>
        <div class="success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>
    
   
    <div class="max-limit-section">
        <h3 style="color: #ff8c42; margin-bottom: 20px;">Manage Mechanic Daily Limits</h3>
        
        <form method="POST" class="max-limit-form">
            <div class="form-group">
                <label for="mechanic_id">Select Mechanic:</label>
                <select name="mechanic_id" id="mechanic_id" required>
                    <option value="">-- Choose Mechanic --</option>
                    <?php 
                    $mechanics->execute();
                    while($mech = $mechanics->fetch(PDO::FETCH_ASSOC)): 
                    ?>
                        <option value="<?php echo $mech['mechanic_id']; ?>">
                            <?php echo $mech['mechanic_name']; ?> (Current: <?php echo $mech['max_appointments']; ?> per day)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="max_appointments">New Max Per Day:</label>
                <input type="number" name="max_appointments" id="max_appointments" min="1" max="10" required>
            </div>
            
            <div class="form-group">
                <label for="limit_date">Apply From Date:</label>
                <input type="date" name="limit_date" id="limit_date" value="<?php echo date('d-m-Y'); ?>" required>
            </div>
            
            <button type="submit" name="update_max_limit">Update Limit</button>
        </form>
        
        <p class="reassign-notice">
            <strong>Note:</strong> If you reduce a mechanic's limit below their current appointments on the selected date, 
            excess appointments will be automatically reassigned to available mechanics.
        </p>
    </div>
    
    <?php
    
    $total_appointments = $conn->query("SELECT COUNT(*) as count FROM appointments")->fetch(PDO::FETCH_ASSOC);
    $today_appointments = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = CURDATE()");
    $today_appointments->execute();
    $today = $today_appointments->fetch(PDO::FETCH_ASSOC);
    ?>
    
    <div class="stats-container">
        <div class="stat-card">
            <h3>Total Appointments</h3>
            <div class="number"><?php echo $total_appointments['count']; ?></div>
        </div>
        
        <div class="stat-card">
            <h3>Today's Appointments</h3>
            <div class="number"><?php echo $today['count']; ?></div>
        </div>
        
        <div class="stat-card">
            <h3>Active Mechanics</h3>
            <div class="number"><?php echo $conn->query("SELECT COUNT(*) as count FROM mechanics")->fetchColumn(); ?></div>
        </div>
    </div>
    
    <div class="mechanic-info">
        <h3 style="color: #ff8c42; margin-bottom: 10px;">Mechanic Information</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">

            <?php
            $mech_stats = $conn->query("SELECT m.mechanic_id, m.mechanic_name, m.max_appointments,
                COUNT(a.appointment_id) as total_booked,
                SUM(CASE WHEN a.appointment_date = CURDATE() THEN 1 ELSE 0 END) as today_booked FROM mechanics m
                LEFT JOIN appointments a ON m.mechanic_id = a.mechanic_id GROUP BY m.mechanic_id");
            
            while ($stat = $mech_stats->fetch(PDO::FETCH_ASSOC)):
                $available_today = $stat['max_appointments'] - $stat['today_booked'];
            ?>
            <div class="mechanic-card">
                <strong><?php echo $stat['mechanic_name']; ?></strong>
                <span class="max-badge">Max: <?php echo $stat['max_appointments']; ?>/day</span>
                <br>
                <small>Today: <?php echo $stat['today_booked']; ?>/<?php echo $stat['max_appointments']; ?> booked</small><br>
                <small>Total: <?php echo $stat['total_booked']; ?> appointments</small>
                <?php if ($available_today > 0): ?>
                    <div style="color: #4CAF50; margin-top: 8px;"> <?php echo $available_today; ?> slots free today</div>
                <?php else: ?>
                    <div style="color: #ff4444; margin-top: 8px;">Fully booked today</div>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    
    
    <div class="appointments-table">
    <h3>All Appointments</h3>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Client Name</th>
                <th>Phone</th>
                <th>Service</th>
                <th>Price</th>
                <th>Car License</th>
                <th>Current Mechanic</th>
                <th>Current Date</th>
                <th>Update Mechanic</th>
                <th>Update Date</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($appointments->rowCount() > 0): ?>
                <?php while($row = $appointments->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr>
                        <td><?php echo $row['appointment_id']; ?></td>
                        <td><?php echo htmlspecialchars($row['client_name']); ?></td>
                        <td>0<?php echo $row['client_phone']; ?></td>
                        <td><?php echo $row['service_name'] ?? 'Not specified'; ?></td>
                        <td>Tk <?php echo isset($row['price']) ? number_format($row['price'], 2) : '0.00'; ?></td>
                        <td><?php echo htmlspecialchars($row['car_license']); ?></td>
                        <td><?php echo $row['mechanic_name']; ?></td>
                        <td><?php echo date('d-m-Y', strtotime($row['appointment_date'])); ?></td>
                        
                        <form method="POST" style="display: contents;">
                            <input type="hidden" name="appointment_id" value="<?php echo $row['appointment_id']; ?>">
                            <td>
                                <select name="mechanic_id" class="edit-form" required>
                                    <option value="">Select Mechanic</option>
                                    <?php 
                                    $mechanics->execute();
                                    while($mech = $mechanics->fetch(PDO::FETCH_ASSOC)): 
                                    ?>
                                        <option value="<?php echo $mech['mechanic_id']; ?>"
                                            <?php echo ($mech['mechanic_id'] == $row['mechanic_id']) ? 'selected' : ''; ?>>
                                            <?php echo $mech['mechanic_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </td>
                            <td>
                                <input type="date" name="appointment_date" 
                                    value="<?php echo $row['appointment_date']; ?>" 
                                    min="<?php echo date('d-m-Y'); ?>" required>
                            </td>
                            <td>
                                <button type="submit" name="update_appointment" class="update-btn">
                                    Update
                                </button>
                            </td>
                        </form>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="11" style="text-align: center; padding: 30px;">
                        No appointments found.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
    
</div>

</body>
</html>