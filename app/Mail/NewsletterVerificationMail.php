<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public NewsletterSubscriber $subscriber)
    {
        // رندر ایمیل با زبان خود مشترک (en/tr)
        $this->locale($subscriber->locale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('newsletter.mail_verify_subject', locale: $this->subscriber->locale),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter.verify',
            with: [
                'verifyUrl' => url('/newsletter/verify/'.$this->subscriber->verification_token),
                'unsubscribeUrl' => url('/newsletter/unsubscribe/'.$this->subscriber->unsubscribe_token),
            ],
        );
    }
}
