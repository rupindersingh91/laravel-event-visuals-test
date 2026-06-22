<?php

namespace App\Console\Commands;

use App\Mail\EventReminderMail;
use App\Models\EventAttendee;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendEventReminders extends Command
{
    protected $signature = 'events:send-reminders';

    protected $description = 'Send 3-day and 24-hour reminder emails to event attendees';

    public function handle(): void
    {
        $now = Carbon::now();
        $in3Days = $now->copy()->addDays(3);

        EventAttendee::with('event')
            ->whereHas('event', function ($q) use ($now, $in3Days) {
                // Only load attendees whose event starts within the next 3 days
                $q->whereBetween('created_time', [$now->timestamp + 1, $in3Days->timestamp]);
            })
            ->where(function ($q) {
                $q->whereNull('reminder_3d_sent_at')
                    ->orWhereNull('reminder_24h_sent_at');
            })
            ->chunkById(200, function ($attendees) use ($now) {
                $in3Days = $now->copy()->addDays(3);
                $in24Hours = $now->copy()->addHours(24);

                foreach ($attendees as $attendee) {
                    $event = $attendee->event;
                    if (! $event || ! $event->created_time) {
                        continue;
                    }

                    $startsAt = (int) $event->created_time;

                    if ($startsAt > $now->timestamp
                        && $startsAt <= $in3Days->timestamp
                        && is_null($attendee->reminder_3d_sent_at)
                    ) {
                        Mail::to($attendee->email)->queue(
                            new EventReminderMail($event, $attendee, '3 days')
                        );
                        $attendee->update(['reminder_3d_sent_at' => $now]);
                    }

                    if ($startsAt > $now->timestamp
                        && $startsAt <= $in24Hours->timestamp
                        && is_null($attendee->reminder_24h_sent_at)
                    ) {
                        Mail::to($attendee->email)->queue(
                            new EventReminderMail($event, $attendee, '24 hours')
                        );
                        $attendee->update(['reminder_24h_sent_at' => $now]);
                    }
                }
            });

        $this->info('Reminders dispatched.');
    }
}
