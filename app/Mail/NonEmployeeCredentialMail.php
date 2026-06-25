<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\NonEmployeeUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NonEmployeeCredentialMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly NonEmployeeUser $user,
        public readonly string $password,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your COI Declaration System Account',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.non-employee-credential',
            with: [
                'user' => $this->user,
                'password' => $this->password,
                'appName' => config('coi.name'),
                'appUrl' => config('coi.url'),
                'supportEmail' => config('coi.support_email'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}