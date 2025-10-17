{{-- resources/views/emails/booking-customer-notification.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <title>Booking Confirmation</title>
</head>
<body>
    <h1>Thank You for Your Booking!</h1>
    <p>Dear {{ $data['farmerName'] }},</p>
    <p>Your service booking has been received successfully:</p>
    <ul>
        <li><strong>Transaction Reference:</strong> {{ $data['transactionReference'] }}</li>
        <li><strong>Service:</strong> {{ $data['serviceName'] }}</li>
        <li><strong>Quantity:</strong> {{ $data['quantity'] }}</li>
        <li><strong>Total Amount:</strong> NGN {{ number_format($data['totalCost']) }}</li>
        <li><strong>Location:</strong> {{ $data['hubName'] }}</li>
    </ul>
    <p>Our team will contact you soon to schedule the service.</p>
    <p>If you have any questions, please reply to this email.</p>
    <p>Best regards,<br>MamaTrak Team</p>
</body>
</html>