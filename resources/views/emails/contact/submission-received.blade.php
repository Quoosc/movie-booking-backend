<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New contact submission</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5; color: #111827;">
    <h2 style="margin-bottom: 8px;">New Contact Submission</h2>
    <p style="margin-top: 0; color: #4b5563;">A new message was submitted from the website contact form.</p>

    <table cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; border-color: #e5e7eb; width: 100%; max-width: 680px;">
        <tr>
            <td style="width: 160px; font-weight: bold;">Name</td>
            <td>{{ $submission->name }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Email</td>
            <td>{{ $submission->email }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Source Page</td>
            <td>{{ $submission->source_page ?: '-' }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">IP Address</td>
            <td>{{ $submission->ip_address ?: '-' }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold; vertical-align: top;">Message</td>
            <td>{!! nl2br(e($submission->message)) !!}</td>
        </tr>
    </table>

    <p style="margin-top: 16px; color: #6b7280; font-size: 12px;">Submitted at {{ $submission->created_at?->format('Y-m-d H:i:s') }}</p>
</body>
</html>
