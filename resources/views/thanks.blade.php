<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You</title>
    <style>
        body {
            background: linear-gradient(135deg, #00b09b, #96c93d);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #fff;
            margin: 0;
        }

        .thankyou-container {
            background: rgba(255, 255, 255, 0.1);
            padding: 40px 60px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            animation: fadeIn 1s ease-in-out;
        }

        .thankyou-container h1 {
            font-size: 3rem;
            margin-bottom: 10px;
        }

        .thankyou-container p {
            font-size: 1.2rem;
            margin-bottom: 25px;
        }

        .thankyou-container a {
            display: inline-block;
            background-color: #fff;
            color: #00b09b;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .thankyou-container a:hover {
            background-color: #00b09b;
            color: #fff;
            border: 2px solid #fff;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
    </style>
</head>
<body>
    <div class="thankyou-container">
        <h1>🎉 Thank You!</h1>
        <p>Your payment was successful. We appreciate your trust.</p>
        <p>Amount: {{ $amount}} and Transaction: {{ $transaction_id }}.</p>
        <a href="#">Back to Home</a>
    </div>
</body>
</html>
