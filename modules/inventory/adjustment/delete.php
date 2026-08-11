<?php
// Agar aap chahte hain ki admin isse dekh sake aur baaki log na dekh sakein,
// toh aap yahan session check bhi laga sakte hain.

?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coming Soon</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            text-align: center;
        }
        .container {
            max-width: 600px;
            padding: 20px;
        }
        h1 {
            font-size: 3.5rem;
            margin-bottom: 10px;
            letter-spacing: 2px;
        }
        p {
            font-size: 1.2rem;
            opacity: 0.8;
        }
    </style>
</head>
<body>

    <div class="container">
        <h1>Coming Soon</h1>
        <p>Hum is page par kuch naya aur behtareen bana rahe hain. Kripya thoda intezar karein!</p>
    </div>

</body>
</html>
<?php 
// Niche ke baaki code ko execute hone se rokne ke liye exit lagana zaroori hai
exit(); 
?>