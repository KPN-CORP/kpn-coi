<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

class LogSentEmail
{
    /**
     * Record every successfully sent email to the dedicated "email" channel.
     *
     * Only recipient and subject are logged -- never the body, passwords, or
     * reset tokens. Send failures throw before MessageSent fires, so they land
     * in the "error" channel via the global handler instead.
     */
    public function handle(MessageSent $event): void
    {
        $recipients = (new Collection($event->message->getTo()))
            ->map(fn (Address $address): string => $address->getAddress())
            ->implode(', ');

        Log::channel('email')->info('Email sent', [
            'to' => $recipients,
            'subject' => $event->message->getSubject(),
        ]);
    }
}
