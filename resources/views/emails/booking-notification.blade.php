{{-- resources/views/emails/booking-notification.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <title>New Service Booking</title>
</head>
<body>
    <h1>New Service Booking Alert</h1>
    <p>Dear Coordinator,</p>
    <p>A new service booking has been made:</p>
    <ul>
        <li><strong>Transaction Reference:</strong> {{ $data['transactionReference'] }}</li>
        <li><strong>Farmer Name:</strong> {{ $data['farmerName'] }}</li>
        <li><strong>Service:</strong> {{ $data['serviceName'] }}</li>
        <li><strong>Quantity:</strong> {{ $data['quantity'] }}</li>
        <li><strong>Total Cost:</strong> NGN {{ number_format($data['totalCost']) }}</li>
        <!-- <li><strong>Hub/LGA:</strong> {{ $data['hubName'] }}</li> -->
    </ul>
    <p>Please review and process this booking promptly.</p>
    <p>Best regards,<br>MamaTrak Team</p>
</body>
</html>