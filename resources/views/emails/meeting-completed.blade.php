<!DOCTYPE html>
<html>
<head>
    <title>Meeting Completed</title>
</head>
<body>

    <h2>Hi {{ $recipientName }},</h2>

    <p>Your meeting with {{ $otherPartyName }} has been marked as completed.</p>

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
        @if($rating)
        <tr>
            <td><strong>Your Rating</strong></td>
            <td>{{ $rating }} / 5</td>
        </tr>
        @endif
    </table>

    <p>Thanks for meeting safely with SafeeMeet.</p>

    <p>
        Regards,<br>
        SafeeMeet Team
    </p>

</body>
</html>
