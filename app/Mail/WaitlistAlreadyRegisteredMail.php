<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WaitlistAlreadyRegisteredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Quầy Signage Desk vẫn còn');
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.waitlist-already-registered',
            text: 'mail.waitlist-already-registered-text',
            with: [
                'loginUrl' => rtrim((string) config('app.cms_url'), '/').'/login',
            ],
        );
    }
}
