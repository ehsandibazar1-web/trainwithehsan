<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<body style="margin:0;padding:0;background:#f6f6f6;font-family:Arial,Helvetica,sans-serif">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f6f6;padding:30px 0">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;padding:32px;max-width:94%">
                    <tr><td style="font-size:15px;color:#222;line-height:1.8">
                        <p style="margin:0 0 12px">{{ __('newsletter.mail_verify_greeting') }}</p>
                        <p style="margin:0 0 22px">{{ __('newsletter.mail_verify_line1') }}</p>
                        <p style="margin:0 0 22px;text-align:center">
                            <a href="{{ $verifyUrl }}" style="display:inline-block;background:#d9bb75;color:#111;text-decoration:none;font-weight:bold;padding:12px 28px;border-radius:6px">{{ __('newsletter.mail_verify_button') }}</a>
                        </p>
                        <p style="margin:0 0 6px;font-size:12px;color:#777">{{ __('newsletter.mail_verify_expiry') }}</p>
                        <p style="margin:0;font-size:12px;color:#777">{{ __('newsletter.mail_verify_ignore') }}</p>
                    </td></tr>
                    <tr><td style="border-top:1px solid #eee;padding-top:14px;font-size:11px;color:#999;text-align:center">
                        {{ __('newsletter.mail_unsubscribe_text') }}
                        <a href="{{ $unsubscribeUrl }}" style="color:#999">{{ __('newsletter.mail_unsubscribe_link') }}</a>
                    </td></tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
