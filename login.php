<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once "config/database.php";
require_once "config/logger.php"; // 📍 1. LOGGER FILE INCLUDE KI GAYI HAI

if(isset($_SESSION['employee_id'])){
    header("Location:dashboard.php");
    exit();
}

$error = "";
if(isset($_POST['login'])){

    $employee_id = trim($_POST['employee_id']);
    $password    = $_POST['password'];

    $sql = "SELECT *
            FROM employees
            WHERE employee_id='$employee_id'
            AND status='Active'
            LIMIT 1";

    $result = mysqli_query($conn,$sql);

    if(mysqli_num_rows($result)==1){

        $user = mysqli_fetch_assoc($result);

        if(password_verify($password,$user['password'])){

            $_SESSION['user_id']      = $user['id'];
            $_SESSION['employee_id']  = $user['employee_id'];
            $_SESSION['full_name']    = $user['full_name'];
            $_SESSION['role']         = $user['role'];
            $_SESSION['department']   = $user['department'];
            $_SESSION['warehouse']    = $user['warehouse'];

            // 📍 2. YAHAN LOG ACTIVITY ADD KI GAYI HAI (HEADER REDIRECT SE THIK PEHLE)
            logActivity($conn, 'Authentication', 'LOGIN', 'Employee logged in successfully');

            header("Location:dashboard.php");
            exit();

        }else{
            $error="Invalid Employee ID or Password.";
        }

    }else{
        $error="Invalid Employee ID or Password.";
    }

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>VORTEX WMS | Enterprise Login</title>

<!-- Google Fonts: Cursive Heading & Body Font -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="assets/css/login.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

<!-- Moving Light Particles Canvas -->
<canvas id="particlesCanvas"></canvas>

<div class="login-box">

    <div class="left">
        <i class="fa-solid fa-warehouse brand-icon"></i>
        <h1 class="cursive-heading">Vortex WMS</h1>
        <p>
            Enterprise Warehouse Management System
            <br><br>
            ✔ Smart Inventory<br>
            ✔ Fast Inbound<br>
            ✔ Secure Operations<br>
            ✔ Real-Time Tracking
        </p>
    </div>

    <div class="right">
        <h2 class="cursive-subheading">Welcome Back 👋</h2>
        <p>Login to continue.</p>

        <?php if(!empty($error)){ ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error); ?>
            </div>
        <?php } ?>

        <form method="POST">
            <div class="form-group">
                <label>Employee ID</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fa-solid fa-id-badge"></i>
                    </span>
                    <input type="text" name="employee_id" class="form-control" placeholder="Enter Employee ID" required>
                </div>
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fa-solid fa-lock"></i>
                    </span>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter Password" required>
                    <button type="button" id="togglePassword">
                        <i id="eye" class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" name="login" class="btn-login">
                <i class="fa-solid fa-right-to-bracket"></i> &nbsp; Login
            </button>
        </form>

        <div class="footer">
            VORTEX WMS © <?= date("Y"); ?>
        </div>
    </div>

</div>

<script src="assets/js/login.js"></script>
</body>
</html>