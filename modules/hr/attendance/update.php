<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Admin Session Check
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    return;
}

// 2. SEO Safety Headers
header('HTTP/1.1 503 Service Temporarily Unavailable');
header('Status: 503 Service Temporarily Unavailable');
header('Retry-After: 86400');
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coming Soon</title>
    
    <!-- Google Fonts (Pacifico, Poppins, Righteous) & FontAwesome Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Poppins:wght@300;500;700&family=Righteous&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(-45deg, #7206e6, #4648e7, #8e44ad, #3498db);
            background-size: 400% 400%;
            animation: gradientBG 12s ease infinite;
            color: white;
            font-family: 'Poppins', sans-serif;
            text-align: center;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .container {
            max-width: 550px;
            width: 90%;
            padding: 40px 25px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        }

        /* H1 Cursive Title */
        h1 {
            font-family: 'Pacifico', cursive;
            font-size: 4rem;
            margin: 0 0 5px 0;
            font-weight: normal;
            letter-spacing: 1px;
            text-shadow: 2px 4px 10px rgba(0, 0, 0, 0.2);
        }

        /* Modern Righteous Font + Gradient H2 */
h2 {
    font-family: 'Righteous', cursive;
    font-size: 1.35rem;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin: 0 0 15px 0;
    background: linear-gradient(90deg, #ffeaa7, #ff7675, #74b9ff);
    background-clip: text; /* Standard property for compatibility */
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    display: inline-block;
    filter: drop-shadow(0px 2px 8px rgba(0,0,0,0.3));
}
        p {
            font-size: 0.95rem;
            opacity: 0.85;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .countdown {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin: 25px 0;
        }

        .countdown-item {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 12px;
            padding: 12px 10px;
            min-width: 65px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .countdown-item span {
            display: block;
            font-size: 1.8rem;
            font-weight: 600;
            line-height: 1;
        }

        .countdown-item p {
            margin: 5px 0 0 0;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.8;
        }

        .subscribe-form {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
        }

        .subscribe-form input {
            flex: 1;
            padding: 12px 18px;
            border: none;
            border-radius: 30px;
            outline: none;
            font-size: 0.95rem;
        }

        .subscribe-form button {
            padding: 12px 25px;
            border: none;
            border-radius: 30px;
            background: #ff4757;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s ease;
        }

        .subscribe-form button:hover {
            background: #ff6b81;
            transform: translateY(-2px);
        }

        .social-links a {
            color: white;
            font-size: 1.3rem;
            margin: 0 10px;
            transition: 0.3s ease;
            display: inline-block;
        }

        .social-links a:hover {
            color: #ff4757;
            transform: scale(1.2);
        }

        @media (max-width: 480px) {
            h1 { font-size: 3rem; }
            h2 { font-size: 1.1rem; letter-spacing: 1px; }
            .countdown { gap: 8px; }
            .countdown-item { min-width: 50px; padding: 10px 6px; }
            .countdown-item span { font-size: 1.3rem; }
        }
    </style>
</head>
<body>

    <div class="container">
        <h1>Coming Soon</h1>
        <h2>Something Amazing is Crafting</h2>
        <p>Hum is page par kuch naya aur behtareen bana rahe hain. Launch hone me bacha samay:</p>
        
        <div class="countdown">
            <div class="countdown-item">
                <span id="days">00</span>
                <p>Days</p>
            </div>
            <div class="countdown-item">
                <span id="hours">00</span>
                <p>Hours</p>
            </div>
            <div class="countdown-item">
                <span id="minutes">00</span>
                <p>Minutes</p>
            </div>
            <div class="countdown-item">
                <span id="seconds">00</span>
                <p>Seconds</p>
            </div>
        </div>

        <form class="subscribe-form" action="#" method="POST">
            <input type="email" placeholder="Apna email darj karein..." required>
            <button type="submit">Notify Me</button>
        </form>

        <div class="social-links">
            <a href="#"><i class="fab fa-facebook-f"></i></a>
            <a href="#"><i class="fab fa-instagram"></i></a>
            <a href="#"><i class="fab fa-twitter"></i></a>
            <a href="#"><i class="fab fa-whatsapp"></i></a>
        </div>
    </div>

    <script>
        const targetDate = new Date();
        targetDate.setDate(targetDate.getDate() + 30); 

        function updateCountdown() {
            const now = new Date().getTime();
            const difference = targetDate - now;

            if (difference <= 0) {
                document.querySelector('.countdown').innerHTML = "<h3 style='margin:0;'>We are Live!</h3>";
                return;
            }

            const days = Math.floor(difference / (1000 * 60 * 60 * 24));
            const hours = Math.floor((difference % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((difference % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((difference % (1000 * 60)) / 1000);

            document.getElementById('days').innerText = days < 10 ? '0' + days : days;
            document.getElementById('hours').innerText = hours < 10 ? '0' + hours : hours;
            document.getElementById('minutes').innerText = minutes < 10 ? '0' + minutes : minutes;
            document.getElementById('seconds').innerText = seconds < 10 ? '0' + seconds : seconds;
        }

        updateCountdown();
        setInterval(updateCountdown, 1000);
    </script>

</body>
</html>
<?php 
exit(); 
?>