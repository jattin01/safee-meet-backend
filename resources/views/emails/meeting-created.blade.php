<!DOCTYPE html>
<html>
<head>
    <title>New Meeting Request</title>
</head>
<body>

    <h2>Hi {{ $recipientName }},</h2>

    <p>{{ $otherPartyName }} wants to meet you on SafeeMeet.</p>

    <table cellpadding="6" cellspacing="0" border="0">
        <tr>
            <td><strong>Location</strong></td>
            <td>{{ $location }}</td>
        </tr>
        <tr>
            <td><strong>Date</strong></td>
            <td>{{ $meetingDate }}</td>
        </tr>
        <tr>
            <td><strong>Time</strong></td>
            <td>{{ $meetingTime }}</td>
        </tr>
        <tr>
            <td><strong>Type</strong></td>
            <td>{{ ucfirst($type) }}</td>
        </tr>
        @if($purpose)
        <tr>
            <td><strong>Purpose</strong></td>
            <td>{{ $purpose }}</td>
        </tr>
        @endif
    </table>

    <p>Open the SafeeMeet app to review and respond to this request.</p>

    <p>
        Regards,<br>
        SafeeMeet Team
    </p>

</body>
</html>
