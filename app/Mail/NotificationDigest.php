<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NotificationDigest extends Mailable
{
    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    public function __construct(
        public readonly array $events,
        public readonly string $unsubscribeUrl,
    ) {}

    public function envelope(): Envelope
    {
        $count = count($this->events);
        $subject = $count === 1
            ? $this->events[0]['title']
            : "{$count} notifications from Chief";

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notification-digest',
            text: 'emails.notification-digest-text',
        );
    }
}
