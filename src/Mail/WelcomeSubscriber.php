<?php

declare(strict_types=1);

namespace Nejcc\Subscribe\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Nejcc\Subscribe\Models\Subscriber;
use Nejcc\Subscribe\Models\SubscriptionList;

final class WelcomeSubscriber extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Subscriber $subscriber,
        public ?SubscriptionList $list = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->list?->welcome_email_subject
            ?? config('subscribe.welcome_email.subject', 'Welcome to our newsletter!');

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'subscribe::emails.welcome',
            with: [
                'subscriber' => $this->subscriber,
                'list' => $this->list,
                'unsubscribeUrl' => route('subscribe.unsubscribe.form', base64_encode($this->subscriber->email)),
            ],
        );
    }
}
