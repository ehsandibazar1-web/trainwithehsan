<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:0;background:#f6f6f6;font-family:Arial,Helvetica,sans-serif">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f6f6;padding:30px 0">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;padding:32px;max-width:94%">
                    <tr><td style="font-size:15px;color:#222;line-height:1.8">
                        <p style="margin:0 0 4px"><strong>From:</strong> {{ $name }} ({{ $senderEmail }})</p>
                        <p style="margin:0 0 22px"><strong>Language:</strong> {{ strtoupper($locale) }}</p>
                        <p style="margin:0 0 22px;white-space:pre-line;border-top:1px solid #eee;padding-top:16px">{{ $messageBody }}</p>
                        <p style="margin:0;font-size:12px;color:#777">Sent from the Contact page on trainwithehsan.com. Reply directly to this email to respond to {{ $name }}.</p>
                    </td></tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
