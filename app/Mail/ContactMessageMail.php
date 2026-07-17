<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $senderEmail,
        public string $messageBody,
        public string $senderLocale,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('contact.mail_subject'),
            replyTo: [$this->senderEmail],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact.message',
            with: [
                'name' => $this->name,
                'senderEmail' => $this->senderEmail,
                'messageBody' => $this->messageBody,
                'locale' => $this->senderLocale,
            ],
        );
    }
}
