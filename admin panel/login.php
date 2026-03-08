<?php
include '../components/connect.php';

session_start();

if (isset($_POST['submit'])) {
    $email = $_POST['email'];
    $email = filter_var($email, FILTER_SANITIZE_STRING);
    $password = $_POST['password'];
    $password = filter_var($password, FILTER_SANITIZE_STRING);

    $stmt = $conn->prepare("SELECT * FROM `admin` WHERE Email = :email AND Password = PASSWORD(:password)");
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':password', $password);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        $_SESSION['admin_email'] = $admin['Email'];
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Servicing - Admin Login</title>
    <link rel="stylesheet" href="../css/admin_style.css">
</head>
<body>

<div class="form-container">
    <form action="" method="post">
        <h3>Admin Login</h3>
        
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="input-box">
            <p>Your Email<span>*</span></p>
            <input type="email" name="email" placeholder="Enter your email" class="box" 
                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
        </div>
        
        <div class="input-box">
            <p>Your Password<span>*</span></p>
            <input type="password" name="password" placeholder="Enter your password" class="box" required>
        </div>
        
        <input type="submit" value="Login Now" class="btn" name="submit">
        
        <div class="back-home">
            <a href="../index.php">Back to Home</a>
        </div>
    </form>
</div>
    
</body>
</html>