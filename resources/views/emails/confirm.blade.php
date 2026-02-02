<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('subscribe.confirmation_email.subject', 'Confirm your subscription') }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #f9fafb; border-radius: 8px; padding: 40px; text-align: center;">
        <h1 style="color: #111; font-size: 24px; margin-bottom: 16px;">
            {{ __('Confirm your subscription') }}
        </h1>

        <p style="color: #666; font-size: 16px; margin-bottom: 24px;">
            {{ __('Thank you for subscribing! Please click the button below to confirm your subscription.') }}
        </p>

        <a href="{{ $confirmUrl }}" style="display: inline-block; background: #2563eb; color: #fff; font-size: 16px; font-weight: 600; text-decoration: none; padding: 12px 32px; border-radius: 6px;">
            {{ __('Confirm Subscription') }}
        </a>

        <p style="color: #999; font-size: 14px; margin-top: 24px;">
            {{ __('If you did not subscribe, you can safely ignore this email.') }}
        </p>
    </div>

    <div style="text-align: center; margin-top: 24px;">
        <p style="color: #999; font-size: 12px;">
            {{ __('If the button above does not work, copy and paste this link into your browser:') }}<br>
            <a href="{{ $confirmUrl }}" style="color: #2563eb;">{{ $confirmUrl }}</a>
        </p>
    </div>
</body>
</html>
