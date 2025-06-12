@component('mail::message')

# Hello {{ $name }}!

You requested a password reset.

@component('mail::button', ['url' => $resetUrl])
Reset Password
@endcomponent

If you did not request a password reset, no further action is required.

Thanks,<br>
{{ config('mail.from.name') }}

@endcomponent




{{-- <!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
</head>
<body>
    <h1>Hello {{ $name }}!</h1>

    <p>You requested a password reset.</p>

    <p><a href="{{ $resetUrl }}">Reset Password</a></p>

    <p>If you did not request a password reset, no further action is required.</p>

    <br><br>

    <p>Regards,<br>{{ config('mail.from.name') }}</p>
</body>
</html> --}}
