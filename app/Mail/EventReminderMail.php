<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\EventAttendee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Event $event,
        public EventAttendee $attendee,
        public string $window,
    ) {}

    public function envelope(): Envelope
    {
        $name = (string) ($this->event->payload['name'] ?? 'Event');

        return new Envelope(subject: "Reminder: {$name} starts in {$this->window}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.event-reminder');
    }

    public function attachments(): array
    {
        return [];
    }
}
