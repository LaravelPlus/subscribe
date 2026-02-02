<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Welcome to our newsletter!') }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #f9fafb; border-radius: 8px; padding: 40px; text-align: center;">
        <h1 style="color: #111; font-size: 24px; margin-bottom: 16px;">
            {{ __('Welcome!') }}
        </h1>

        @if($subscriber->first_name)
        <p style="color: #666; font-size: 16px; margin-bottom: 24px;">
            {{ __('Hi :name,', ['name' => $subscriber->first_name]) }}
        </p>
        @endif

        @if($list && $list->welcome_email_content)
        <div style="color: #666; font-size: 16px; margin-bottom: 24px; text-align: left;">
            {!! nl2br(e($list->welcome_email_content)) !!}
        </div>
        @else
        <p style="color: #666; font-size: 16px; margin-bottom: 24px;">
            {{ __('Thank you for subscribing to our newsletter. We\'re excited to have you on board!') }}
        </p>
        @endif
    </div>

    <div style="text-align: center; margin-top: 24px;">
        <p style="color: #999; font-size: 12px;">
            {{ __('If you no longer wish to receive these emails, you can') }}
            <a href="{{ $unsubscribeUrl }}" style="color: #2563eb;">{{ __('unsubscribe here') }}</a>.
        </p>
    </div>
</body>
</html>
