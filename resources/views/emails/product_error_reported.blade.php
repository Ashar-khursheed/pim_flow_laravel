<!DOCTYPE html>
<html>
<head>
    <title>Product Error Report</title>
</head>
<body>
    <h2>Error Report for Product ID: {{ $data['product_id'] }}</h2>

    <p><strong>Title:</strong> {{ $data['title'] }}</p>
    <p><strong>Problem:</strong> {{ $data['problem'] }}</p>
    <p><strong>Reported At:</strong> {{ $data['problem_timestamp'] ?? 'N/A' }}</p>
    <p><strong>Email:</strong> {{ $data['email'] ?? 'N/A' }}</p>
</body>
</html>
