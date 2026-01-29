<!DOCTYPE html>
<html>
<head>
	<title>Chatbot Pusher Test</title>
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<style>
		body {
			font-family: Arial, sans-serif;
			max-width: 800px;
			margin: 50px auto;
			padding: 20px;
		}
		.container {
			border: 1px solid #ddd;
			border-radius: 5px;
			padding: 20px;
		}
		.form-group {
			margin-bottom: 15px;
		}
		label {
			display: block;
			margin-bottom: 5px;
			font-weight: bold;
		}
		input, select, textarea {
			width: 100%;
			padding: 8px;
			border: 1px solid #ddd;
			border-radius: 4px;
		}
		button {
			background-color: #4CAF50;
			color: white;
			padding: 10px 20px;
			border: none;
			border-radius: 4px;
			cursor: pointer;
		}
		button:hover {
			background-color: #45a049;
		}
		.messages {
			margin-top: 30px;
			border: 1px solid #ddd;
			border-radius: 5px;
			padding: 20px;
			min-height: 200px;
			max-height: 400px;
			overflow-y: auto;
			background-color: #f9f9f9;
		}
		.message {
			background-color: white;
			padding: 10px;
			margin-bottom: 10px;
			border-radius: 5px;
			border-left: 3px solid #4CAF50;
		}
		.message-time {
			color: #666;
			font-size: 12px;
		}
		.success {
			color: green;
			padding: 10px;
			background-color: #d4edda;
			border-radius: 4px;
			margin-bottom: 15px;
		}
	</style>
</head>
<body>
	<div class="container">
		<h1>Chatbot Pusher Test</h1>

		@if(session('success'))
			<div class="success">{{ session('success') }}</div>
		@endif

		<h2>Real-time Messages (via Pusher)</h2>
		<div class="messages" id="messages">
			<p>Waiting for messages...</p>
		</div>
	</div>
	<!-- Pusher JS -->
	<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
	<script>
		/* Initialize Pusher */
		const pusher = new Pusher('deff98884a187f9a7472', {
			cluster: 'ap1'
		});

		/* Subscribe to channel */
		const channel = pusher.subscribe('horeca-channel');

		/* Listen for new messages */
		channel.bind('horeca-new-message', function(data) {
			console.log('New message received:', data);
		});

		/* Connection status */
		pusher.connection.bind('connected', function() {
			console.log('Pusher connected!');
		});

		pusher.connection.bind('error', function(err) {
			console.error('Pusher error:', err);
		});
	</script>
</body>
</html>