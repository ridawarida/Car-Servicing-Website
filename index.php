<?php
include 'components/connect.php';

$search_result = null;
$search_message = '';
$booking_result = '';
$booking_error = '';

if (isset($_POST['search_appointments'])) {
    $search_name = filter_var($_POST['search_name'], FILTER_SANITIZE_STRING);
    
    $stmt = $conn->prepare("SELECT a.*, m.mechanic_name 
                            FROM appointments a 
                            JOIN mechanics m ON a.mechanic_id = m.mechanic_id 
                            WHERE a.client_name LIKE ? 
                            ORDER BY a.appointment_date DESC");
    $search_term = "%$search_name%";
    $stmt->execute([$search_term]);
    $search_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($search_result) == 0) {
        $search_message = "No appointments found for '$search_name'";
    }
}

if (isset($_POST['book_appointment'])) {
    $client_name = filter_var($_POST['client_name'], FILTER_SANITIZE_STRING);
    $client_address = filter_var($_POST['client_address'], FILTER_SANITIZE_STRING);
    $client_phone = filter_var($_POST['client_phone'], FILTER_SANITIZE_NUMBER_INT);
    $car_license = filter_var($_POST['car_license'], FILTER_SANITIZE_STRING);
    $car_engine = filter_var($_POST['car_engine'], FILTER_SANITIZE_NUMBER_INT);
    $appointment_date = $_POST['appointment_date'];
    $mechanic_id = $_POST['mechanic_id'];
    $service_id = $_POST['service_id'];
    
    $errors = [];
    
    $check_car = $conn->prepare("SELECT * FROM appointments 
                                 WHERE car_license = ? AND appointment_date = ?");
    $check_car->execute([$car_license, $appointment_date]);
    if ($check_car->rowCount() > 0) {
        $errors[] = "This car already has an appointment on this date!";
    }
    
    $check_mechanic = $conn->prepare("SELECT COUNT(*) as booked FROM appointments 
                                      WHERE mechanic_id = ? AND appointment_date = ?");
    $check_mechanic->execute([$mechanic_id, $appointment_date]);
    $mechanic_count = $check_mechanic->fetch(PDO::FETCH_ASSOC);
    
    if ($mechanic_count['booked'] >= 4) {
        $errors[] = "Selected mechanic is fully booked on this date!";
    }

    if (empty($service_id)) {
        $errors[] = "Please select a service";
    }
    
    if (strtotime($appointment_date) < strtotime(date('d-m-Y'))) {
        $errors[] = "Appointment date cannot be in the past!";
    }
    
    if (empty($errors)) {
        $insert = $conn->prepare("INSERT INTO appointments 
                                  (client_name, client_address, client_phone, car_license, 
                                   car_engine, appointment_date, mechanic_id, service_id) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($insert->execute([$client_name, $client_address, $client_phone, $car_license, 
                             $car_engine, $appointment_date, $mechanic_id, $service_id])) {
            $booking_result = "Appointment booked successfully!";
        } else {
            $booking_error = "Booking failed. Please try again.";
        }
    }
}

$mechanics = $conn->query("SELECT * FROM mechanics ORDER BY mechanic_name");
$stmt = $conn->prepare("SELECT a.*, m.mechanic_name, s.service_name, s.price  FROM appointments a JOIN mechanics m ON a.mechanic_id = m.mechanic_id 
                        LEFT JOIN services s ON a.service_id = s.service_id WHERE a.client_name LIKE ? 
                        ORDER BY a.appointment_date DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Servicing - Home</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to Car Servicing</h1>
            <p>Your one-stop solution for all your car maintenance needs.</p>

             <div class="header-image">
        <img src="https://media.cnn.com/api/v1/images/stellar/prod/220721175751-woman-mechanic-stock.jpg?c=16x9&q=h_653,w_1160,c_fill/f_avif" 
             alt="Mechanic" 
             class="header-img">
    </div>
            <a href="admin panel/login.php" class="btn btn-orange">Admin Login</a>
        </div>
        
      
        <?php if ($booking_result): ?>
            <div class="success-message"><?php echo $booking_result; ?></div>
        <?php endif; ?>
        
        <?php if ($booking_error): ?>
            <div class="error-message"><?php echo $booking_error; ?></div>
        <?php endif; ?>
        
        <div class="section">
            <h2>View Your Appointments</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="search_name">Enter Your Name:</label>
                    <input type="text" id="search_name" name="search_name" 
                           placeholder="Enter your full name" required>
                </div>
                <button type="submit" name="search_appointments" class="btn btn-orange">Search Appointments</button>
            </form>
            
           <?php if ($search_message): ?>
                <div class="info-message"><?php echo $search_message; ?></div>
            <?php endif; ?>
            
            <?php if ($search_result): ?>
                <div class="appointments-table">
                    <h3>Your Appointments</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Service</th>
                                <th>Price</th>
                                <th>Mechanic</th>
                                <th>Car License</th>
                                <th>Car Engine</th>
                                <th>Address</th>
                                <th>Phone</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($search_result as $appointment): ?>
                                <tr>
                                    <td><?php echo date('d-m-Y', strtotime($appointment['appointment_date'])); ?></td>
                                    <td><?php echo $appointment['service_name'] ?? 'Not specified'; ?></td>
                                    <td>Tk<?php echo $appointment['price'] ? number_format($appointment['price'], 2) : '0.00'; ?></td>
                                    <td><?php echo htmlspecialchars($appointment['mechanic_name']); ?></td>
                                    <td><?php echo htmlspecialchars($appointment['car_license']); ?></td>
                                    <td><?php echo $appointment['car_engine']; ?></td>
                                    <td><?php echo htmlspecialchars($appointment['client_address']); ?></td>
                                    <td>0<?php echo $appointment['client_phone']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="section">
            <h2>Book New Appointment</h2>
            <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <ul style="margin-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="grid-2">
                    <div class="form-group">
                        <label for="client_name">Full Name *</label>
                        <input type="text" id="client_name" name="client_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="client_phone">Phone Number *</label>
                        <input type="tel" id="client_phone" name="client_phone" 
                               pattern="[0-9]{11}" title="Please enter 11 digit number" 
                               placeholder="017xxxxxxxx" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="car_license">Car License Number *</label>
                        <input type="text" id="car_license" name="car_license" 
                               placeholder="e.g., DHK-1234" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="car_engine">Car Engine Number *</label>
                        <input type="number" id="car_engine" name="car_engine" 
                               placeholder="Enter engine number" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="appointment_date">Appointment Date *</label>
                        <input type="date" id="appointment_date" name="appointment_date" 
                               min="<?php echo date('d-m-Y'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="mechanic_id">Select Mechanic *</label>
                        <select id="mechanic_id" name="mechanic_id" required>
                            <option value="">-- Choose Mechanic --</option>
                            <?php 
                            $mechanics->execute();
                            while ($mech = $mechanics->fetch(PDO::FETCH_ASSOC)): 
                            ?>
                                <option value="<?php echo $mech['mechanic_id']; ?>">
                                    <?php echo $mech['mechanic_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="service_id">Select Service *</label>
                        <select id="service_id" name="service_id" required>
                            <option value="">-- Choose Service --</option>
                            <?php
                            $services = $conn->query("SELECT * FROM services ORDER BY service_name");
                            while ($service = $services->fetch(PDO::FETCH_ASSOC)):
                            ?>
                                <option value="<?php echo $service['service_id']; ?>">
                                    <?php echo $service['service_name']; ?> - Tk <?php echo number_format($service['price'], 2); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="client_address">Address *</label>
                    <textarea id="client_address" name="client_address" required></textarea>
                </div>
                
                <button type="submit" name="book_appointment" class="btn btn-orange">Book Appointment</button>
            </form>
        </div>
    </div>

    
<footer class="footer">
    <div class="footer-container">
        <div class="footer-row">
            <div class="footer-col">
                <h4>About Us</h4>
                <p>Your trusted car servicing partner with 5+ years of experience in professional automotive care. We take pride in our team of expert mechanics and quality service.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>
            
            
            <div class="footer-col">
                <h4>Contact Info</h4>
                <ul class="contact-info">
                    <li>
                        <i class="fas fa-map-marker-alt"></i>
                        <span>123 Gulshan Avenue, Dhaka 1212, Bangladesh</span>
                    </li>
                    <li>
                        <i class="fas fa-phone"></i>
                        <span>+880 1712-345678</span>
                    </li>
                    <li>
                        <i class="fas fa-phone"></i>
                        <span>+880 1812-345678 (Hotline)</span>
                    </li>
                    <li>
                        <i class="fas fa-envelope"></i>
                        <span>info@carservicing.com</span>
                    </li>
                    <li>
                        <i class="fas fa-envelope"></i>
                        <span>support@carservicing.com</span>
                    </li>
                </ul>
            </div>
            
            <div class="footer-col">
                <h4>Business Hours</h4>
                <ul class="business-hours">
                    <li>
                        <span class="day">Monday - Friday:</span>
                        <span class="time">8:00 AM - 8:00 PM</span>
                    </li>
                    <li>
                        <span class="day">Saturday:</span>
                        <span class="time">9:00 AM - 6:00 PM</span>
                    </li>
                    <li>
                        <span class="day">Sunday:</span>
                        <span class="time">10:00 AM - 4:00 PM</span>
                    </li>
                    <li>
                        <span class="day">Emergency:</span>
                        <span class="time">24/7 Hotline Available</span>
                    </li>
                </ul>
            </div>
    
        </div>
        
    <div class="footer-bottom">
            <div class="copyright">
                &copy; <?php echo date('Y'); ?> Car Servicing. All rights reserved.
            </div>
            <div class="footer-bottom-links">
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Use</a>
                <a href="#">Sitemap</a>
            </div>
        </div>
    </div>
</footer>
</body>
</html>