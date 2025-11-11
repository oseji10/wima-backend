<!DOCTYPE html>
<html>
<head>
    <title>Membership Application Update</title>
</head>
<body>
    <h2>{{ $greeting }}</h2>
    <p>Your membership application for <strong>{{ $membershipType }}</strong> has been updated to <strong>{{ $status }}</strong>.</p>
    
    @if($status === 'approved')
        <p>Congratulations! Your application has been approved. Welcome to the community.</p>
    @elseif($status === 'rejected')
        <p>We regret to inform you that your application has been rejected. If you have questions, please contact us.</p>
    @else
        <p>Thanks for your interest in joining us.</p>
    @endif
    
    <p>Best regards,<br>MamaTrak Team</p>
</body>
</html>