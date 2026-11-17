<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $headline }}</title>
</head>
<body style="margin:0;padding:24px;background:#0f1115;font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;color:#e7e9ee;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;margin:0 auto;background:#171a21;border:1px solid #2a2f3a;border-radius:12px;">
        <tr>
            <td style="padding:24px;">
                <p style="margin:0 0 4px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#8b93a7;">
                    {{ config('app.name') }}
                </p>

                <h1 style="margin:0 0 12px;font-size:20px;line-height:1.3;">{{ $headline }}</h1>

                <p style="margin:0 0 12px;font-size:14px;line-height:1.6;color:#c3c9d6;">
                    {{ $notice }}
                </p>

                @if ($ip)
                    <p style="margin:0 0 12px;font-size:13px;color:#8b93a7;">
                        IP address:
                        <span style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;color:#c3c9d6;">{{ $ip }}</span>
                    </p>
                @endif

                <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#c3c9d6;">
                    If this was not you, change your password and remove any device you do not recognise.
                </p>

                <a href="{{ route('settings.devices') }}"
                   style="display:inline-block;padding:10px 16px;border-radius:8px;background:#e7e9ee;color:#11131a;font-size:14px;font-weight:600;text-decoration:none;">
                    Review your devices
                </a>

                <p style="margin:20px 0 0;font-size:12px;line-height:1.5;color:#8b93a7;">
                    You are receiving this because security email alerts are enabled for this account.
                    Sign-in and device activity always appears in your in-app notifications.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
