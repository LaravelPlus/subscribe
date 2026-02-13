<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use LaravelPlus\Subscribe\Models\Subscriber;

final class ConfirmSubscription extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Subscriber $subscriber,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: config('subscribe.confirmation_email.subject', 'Confirm your subscription'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'subscribe::emails.confirm',
            with: [
                'subscriber' => $this->subscriber,
                'confirmUrl' => route('subscribe.confirm', $this->subscriber->confirmation_token),
            ],
        );
    }
}
