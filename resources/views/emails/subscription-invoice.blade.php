<!DOCTYPE html>
<html>
<head>
    <title>SafeeMeet Subscription Invoice</title>
</head>
<body>

    <h2>Hi {{ $userName }},</h2>

    <p>Thank you for your payment. Here are your subscription details:</p>

    <table cellpadding="6" cellspacing="0" border="0">
        <tr>
            <td><strong>Plan</strong></td>
            <td>{{ $planName }}</td>
        </tr>
        <tr>
            <td><strong>Billing Cycle</strong></td>
            <td>{{ ucfirst($billingCycle) }}</td>
        </tr>
        <tr>
            <td><strong>Amount Paid</strong></td>
            <td>{{ strtoupper($currency) }} {{ number_format($amount, 2) }}</td>
        </tr>
        <tr>
            <td><strong>Status</strong></td>
            <td>{{ ucfirst($status) }}</td>
        </tr>
        <tr>
            <td><strong>Payment Date</strong></td>
            <td>{{ $paymentDate }}</td>
        </tr>
        <tr>
            <td><strong>Next Billing Date</strong></td>
            <td>{{ $nextBillingDate ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Transaction ID</strong></td>
            <td>{{ $transactionId ?? '-' }}</td>
        </tr>
    </table>

    <p>
        Regards,<br>
        SafeeMeet Team
    </p>

</body>
</html>
