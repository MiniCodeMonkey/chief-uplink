<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #333; line-height: 1.5; margin: 0; padding: 0; background-color: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; padding: 24px; }
        .card { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 24px; margin-bottom: 16px; }
        .header { font-size: 18px; font-weight: 600; margin-bottom: 16px; color: #0a0a0b; }
        .event { padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
        .event:last-child { border-bottom: none; }
        .event-title { font-weight: 600; color: #0a0a0b; }
        .event-body { color: #666; font-size: 14px; margin-top: 2px; }
        .event-meta { color: #999; font-size: 12px; margin-top: 4px; }
        .action { display: inline-block; background: #D97706; color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-size: 14px; font-weight: 500; margin-top: 8px; }
        .footer { text-align: center; padding: 16px 0; color: #999; font-size: 12px; }
        .footer a { color: #999; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                @if(count($events) === 1)
                    {{ $events[0]['title'] }}
                @else
                    {{ count($events) }} notifications from Chief
                @endif
            </div>

            @foreach($events as $event)
                <div class="event">
                    @if(count($events) > 1)
                        <div class="event-title">{{ $event['title'] }}</div>
                    @endif
                    <div class="event-body">{{ $event['body'] }}</div>
                    <div class="event-meta">
                        @if(!empty($event['server']))
                            {{ $event['server'] }}
                        @endif
                    </div>
                    @if(!empty($event['url']))
                        <a href="{{ config('app.url') }}{{ $event['url'] }}" class="action">View details</a>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="footer">
            <p>
                <a href="{{ $unsubscribeUrl }}">Unsubscribe from email notifications</a>
            </p>
            <p>Chief &mdash; chiefloop.com</p>
        </div>
    </div>
</body>
</html>
