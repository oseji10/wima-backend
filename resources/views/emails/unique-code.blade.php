<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Unique Code</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333;">
    <div style="max-width:600px;margin:auto;border:1px solid #ddd;padding:20px;border-radius:10px;">

        <div style="background:#2563eb;color:white;padding:15px;text-align:center;">
            <h2>ISSAM Project</h2>
            <p>WIMA/ISSAM CL/SubCL - Unique Code</p>
        </div>

        <div style="padding:20px;">
            <p>Dear <strong>{{ $fullName }}</strong>,</p>

            <p>
                Thank you for filling the attendance form as a
                <strong>{{ $category }}</strong>.
            </p>

            <p>Your registration has been successful.</p>

            <div style="
                background:#f0f4ff;
                border:2px dashed #2563eb;
                padding:20px;
                text-align:center;
                margin:20px 0;
            ">
                <p>Your Unique Code</p>

                <div style="
                    font-size:32px;
                    font-weight:bold;
                    color:#2563eb;
                    letter-spacing:4px;
                ">
                    {{ $code }}
                </div>

                <p>
                    <strong>Category:</strong>
                    {{ $category }}
                </p>
            </div>

            <p><strong>Please keep this code safe.</strong></p>

            <ul>
                <li>Future identification and verification</li>
                <li>Accessing project resources and opportunities</li>
                <li>Tracking participation in ISSAM activities</li>
            </ul>

            <p>
                If you did not register for the ISSAM Project,
                please ignore this email.
            </p>
        </div>

        <hr>

        <p style="font-size:12px;text-align:center;">
            © {{ date('Y') }} WIMA/ISSAM Project. All rights reserved.
        </p>

    </div>
</body>
</html>