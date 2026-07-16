<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// اسکلت ارسال خبرنامه — از اکشن گروهی پنل ادمین استفاده می‌شود و برای
// کمپین‌ها/خبرنامه‌های زمان‌بندی‌شده‌ی آینده نیز پایه است. صف‌شونده تا ارسال انبوه پنل را قفل نکند.
class NewsletterCampaignMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public NewsletterSubscriber $subscriber,
        public string $subjectLine,
        public string $bodyHtml,
    ) {
        $this->locale($subscriber->locale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter.campaign',
            with: [
                // هر ایمیل خبرنامه باید لینک لغو اشتراک تک‌کلیکی داشته باشد
                'unsubscribeUrl' => url('/newsletter/unsubscribe/'.$this->subscriber->unsubscribe_token),
            ],
        );
    }
}
