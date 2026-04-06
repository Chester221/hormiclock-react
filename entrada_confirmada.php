<?php
// entrada_confirmada.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Entrada Confirmada</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            min-height: 100vh;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 400px;
        }
        .check {
            font-size: 80px;
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .nombre {
            font-size: 24px;
            color: #4CAF50;
            font-weight: bold;
            margin: 20px 0;
        }
        .hora {
            color: #666;
            margin: 20px 0;
        }
        .btn {
            background: #4CAF50;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="check">✅</div>
        <h1>¡ENTRADA REGISTRADA!</h1>
        <div class="nombre"><?php echo $_SESSION['username']; ?></div>
        <div class="hora"><?php echo date('h:i A'); ?></div>
        <p>Tu jornada ha comenzado</p>
        <a href="dashboard.php" class="btn">IR AL DASHBOARD</a>
    </div>
</body>
</html>