<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event reminder</title>
    <style>
        body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #18181b; }
        .wrapper { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e4e4e7; }
        .header { background: #18181b; padding: 32px 40px; }
        .header h1 { margin: 0; color: #ffffff; font-size: 20px; font-weight: 600; }
        .header .window-badge { display: inline-block; margin-top: 8px; padding: 2px 10px; background: #3f3f46; border-radius: 999px; font-size: 12px; color: #d4d4d8; letter-spacing: 0.03em; }
        .body { padding: 32px 40px; }
        .body p { margin: 0 0 16px; font-size: 15px; line-height: 1.6; color: #3f3f46; }
        .body p:last-child { margin-bottom: 0; }
        .highlight { font-weight: 600; color: #18181b; }
        .detail-row { display: flex; gap: 8px; margin: 0 0 10px; font-size: 14px; color: #52525b; }
        .detail-label { min-width: 80px; font-weight: 500; color: #71717a; }
        .divider { border: none; border-top: 1px solid #e4e4e7; margin: 24px 0; }
        .footer { padding: 20px 40px; background: #fafafa; border-top: 1px solid #e4e4e7; font-size: 12px; color: #a1a1aa; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>Your event is coming up</h1>
            <span class="window-badge">In {{ $window }}</span>
        </div>
        <div class="body">
            <p>Hi <span class="highlight">{{ $attendee->name }}</span>,</p>
            <p>
                Just a reminder that
                <span class="highlight">{{ $event->payload['name'] ?? 'your event' }}</span>
                is starting in <span class="highlight">{{ $window }}</span>.
                We hope you're looking forward to it!
            </p>

            <hr class="divider">

            <div class="detail-row">
                <span class="detail-label">Event</span>
                <span>{{ $event->payload['name'] ?? '—' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Location</span>
                <span>{{ $event->location_name }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Venue</span>
                <span>{{ $event->payload['venue']['name'] ?? '—' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Starts</span>
                <span>{{ \Illuminate\Support\Carbon::createFromTimestamp((int) $event->created_time)->toFormattedDayDateString() }}</span>
            </div>

            <hr class="divider">

            <p>See you there!</p>
        </div>
        <div class="footer">
            You received this reminder because you registered for this event.
            Your registration email was <strong>{{ $attendee->email }}</strong>.
        </div>
    </div>
</body>
</html>
