<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Newsletter Subscription</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6;">
    <h2>Hello {{ $data['name'] ?? 'Subscriber' }},</h2>

    <p>Thank you for subscribing to our newsletter!</p>

    <p>We’ve successfully added <strong>{{ $data['email'] }}</strong> to our mailing list.</p>

    <p>You’ll now receive updates, news, and promotions directly in your inbox.</p>

    <br>
    <p>Regards,<br>Your Company Name</p>
</body>
</html>
