<h2>SafeeMeet Verification Rejected/Unsuccessful</h2>

<p>Hello {{ $user->name }},</p>

<p>
    Unfortunately, we were unable to approve your identity verification.
</p>

<p>
    Reason: {{ $reason ?? 'Your verification was declined.' }}
</p>

<p>
    Please review your details and try again.
</p>

<p>
    Regards,<br>
    SafeeMeet Team
</p>