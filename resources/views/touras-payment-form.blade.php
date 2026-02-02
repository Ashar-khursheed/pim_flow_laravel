<!DOCTYPE html>
<html>
<head>
    <title>Touras Payment</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
        }
        .loading {
            text-align: center;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="loading">
        <h2>Redirecting to Payment Gateway...</h2>
        <p>Please wait while we redirect you to the secure payment page.</p>
    </div>

    <form id="paymentForm" method="POST" action="{{ $postUrl }}">
        <input type="hidden" name="me_id" value="{{ $meId }}">
        <input type="hidden" name="merchant_request" value="{{ $merchantRequest }}">
        <input type="hidden" name="hash" value="{{ $hash }}">
    </form>

    <script>
        // Auto-submit form on page load
        window.onload = function() {
            document.getElementById('paymentForm').submit();
        };
    </script>
</body>
</html>