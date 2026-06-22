<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendeeRequest;
use App\Mail\AttendeeConfirmationMail;
use App\Models\Event;
use App\Models\EventAttendee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class AttendeeController extends Controller
{
    public function store(StoreAttendeeRequest $request, Event $event): RedirectResponse
    {
        $attendee = EventAttendee::firstOrCreate(
            ['event_id' => $event->id, 'email' => $request->email],
            ['name' => $request->name],
        );

        if ($attendee->wasRecentlyCreated) {
            Mail::to($attendee->email)->queue(new AttendeeConfirmationMail($event, $attendee));
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $attendee->wasRecentlyCreated
                ? "You're on the list! Check your email for confirmation."
                : "You're already registered for this event.",
        ]);

        return redirect()->back();
    }
}
