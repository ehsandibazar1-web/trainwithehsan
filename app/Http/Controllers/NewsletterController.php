<?php

namespace App\Http\Controllers;

use App\Mail\NewsletterVerificationMail;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NewsletterController extends Controller
{
    // ثبت‌نام از فرم فوتر (AJAX) — دابل آپت‌این: تا تأیید ایمیل، فعال نمی‌شود
    public function subscribe(Request $request)
    {
        $locale = in_array($request->input('locale'), ['en', 'tr'], true) ? $request->input('locale') : 'en';
        app()->setLocale($locale);

        // هانی‌پات: فیلد مخفی «website» برای انسان‌ها نامرئی است؛ پرشدنش یعنی بات.
        // به بات پاسخ موفق الکی می‌دهیم تا روش را عوض نکند — ولی هیچ رکوردی ساخته نمی‌شود.
        if ($request->filled('website')) {
            $this->logSuspicious($request, 'honeypot_filled');

            return response()->json(['ok' => true, 'message' => __('newsletter.subscribed')]);
        }

        // سد زمانی: فرم کمتر از ۳ ثانیه بعد از رندر صفحه ارسال شده = بات
        if (! $this->passesTimeGate($request)) {
            $this->logSuspicious($request, 'time_gate_failed');

            return response()->json(['ok' => true, 'message' => __('newsletter.subscribed')]);
        }

        try {
            $validated = $request->validate([
                'email' => ['required', 'string', 'email:rfc', 'max:255'],
            ]);
        } catch (ValidationException) {
            return response()->json(['ok' => false, 'message' => __('newsletter.invalid_email')], 422);
        }

        $email = Str::lower(trim($validated['email']));

        $subscriber = NewsletterSubscriber::where('email', $email)->first();

        // تکراری و فعال — رکورد جدید ساخته نمی‌شود
        if ($subscriber && $subscriber->isVerified() && $subscriber->status === 'subscribed') {
            return response()->json(['ok' => true, 'message' => __('newsletter.already_subscribed')]);
        }

        // تکراری ولی تأییدنشده (یا قبلاً لغو کرده و برگشته) — ایمیل تأیید دوباره ارسال می‌شود
        if ($subscriber) {
            if ($subscriber->verification_sent_at && $subscriber->verification_sent_at->gt(now()->subMinutes(2))) {
                return response()->json(['ok' => true, 'message' => __('newsletter.resend_cooldown')]);
            }

            $subscriber->update([
                'status' => 'subscribed',
                'locale' => $locale,
                'verification_token' => $subscriber->isVerified() ? $subscriber->verification_token : Str::random(64),
                'verification_sent_at' => now(),
                'unsubscribed_at' => null,
            ]);

            // اگر قبلاً تأیید شده بود (unsubscribe کرده و برگشته) نیازی به تأیید دوباره نیست
            if ($subscriber->isVerified()) {
                return response()->json(['ok' => true, 'message' => __('newsletter.already_subscribed')]);
            }

            Mail::to($subscriber->email)->send(new NewsletterVerificationMail($subscriber));

            return response()->json(['ok' => true, 'message' => __('newsletter.resent')]);
        }

        $subscriber = NewsletterSubscriber::create([
            'email' => $email,
            'status' => 'subscribed',
            'locale' => $locale,
            'source' => 'footer',
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'verification_sent_at' => now(),
        ]);

        Mail::to($subscriber->email)->send(new NewsletterVerificationMail($subscriber));

        return response()->json(['ok' => true, 'message' => __('newsletter.subscribed')]);
    }

    // ارسال دوبارهٔ ایمیل تأیید (لینک منقضی‌شده) — با همان محدودیت نرخ
    public function resend(Request $request)
    {
        $locale = in_array($request->input('locale'), ['en', 'tr'], true) ? $request->input('locale') : 'en';
        app()->setLocale($locale);

        try {
            $validated = $request->validate([
                'email' => ['required', 'string', 'email:rfc', 'max:255'],
            ]);
        } catch (ValidationException) {
            return response()->json(['ok' => false, 'message' => __('newsletter.invalid_email')], 422);
        }

        $subscriber = NewsletterSubscriber::where('email', Str::lower(trim($validated['email'])))
            ->whereNull('verified_at')
            ->first();

        // برای جلوگیری از شمارش ایمیل‌ها، در هر حالت پاسخ یکسان می‌دهیم
        if ($subscriber && (! $subscriber->verification_sent_at || $subscriber->verification_sent_at->lt(now()->subMinutes(2)))) {
            $subscriber->update(['verification_sent_at' => now()]);
            Mail::to($subscriber->email)->send(new NewsletterVerificationMail($subscriber));
        }

        return response()->json(['ok' => true, 'message' => __('newsletter.resent')]);
    }

    // تأیید ایمیل (دابل آپت‌این) — لینک ۲۴ ساعت اعتبار دارد
    public function verify(string $token)
    {
        $subscriber = NewsletterSubscriber::where('verification_token', $token)->first();

        if (! $subscriber) {
            return $this->resultPage('en', __('newsletter.verify_invalid_title', locale: 'en'), __('newsletter.verify_invalid_body', locale: 'en'), 404);
        }

        $locale = $subscriber->locale;
        app()->setLocale($locale);

        if ($subscriber->isVerified()) {
            return $this->resultPage($locale, __('newsletter.verify_already_title'), __('newsletter.verify_already_body'));
        }

        if ($subscriber->verificationExpired()) {
            return $this->resultPage($locale, __('newsletter.verify_expired_title'), __('newsletter.verify_expired_body'), 410);
        }

        $subscriber->markVerified();

        return $this->resultPage($locale, __('newsletter.verify_success_title'), __('newsletter.verify_success_body'));
    }

    // لغو اشتراک تک‌کلیکی — بدون نیاز به لاگین، همیشه معتبر
    public function unsubscribe(string $token)
    {
        $subscriber = NewsletterSubscriber::where('unsubscribe_token', $token)->first();

        if (! $subscriber) {
            return $this->resultPage('en', __('newsletter.unsubscribe_invalid_title', locale: 'en'), __('newsletter.unsubscribe_invalid_body', locale: 'en'), 404);
        }

        $locale = $subscriber->locale;
        app()->setLocale($locale);

        $subscriber->markUnsubscribed();

        return $this->resultPage($locale, __('newsletter.unsubscribe_title'), __('newsletter.unsubscribe_body'));
    }

    private function resultPage(string $locale, string $title, string $message, int $status = 200)
    {
        $view = $locale === 'tr' ? 'tr.newsletter-result' : 'newsletter-result';

        return response()->view($view, compact('title', 'message'), $status);
    }

    // سد زمانی: مُهر زمانی رمزشده هنگام رندر صفحه در فرم گذاشته می‌شود؛
    // دست‌کاری‌شده یا زودتر از ۳ ثانیه = بات
    private function passesTimeGate(Request $request): bool
    {
        try {
            $renderedAt = (int) Crypt::decryptString((string) $request->input('_nl_ts'));
        } catch (\Throwable) {
            return false;
        }

        return (now()->timestamp - $renderedAt) >= 3;
    }

    private function logSuspicious(Request $request, string $reason): void
    {
        Log::warning('newsletter.suspicious_request', [
            'reason' => $reason,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 300, ''),
            'email_input' => Str::limit((string) $request->input('email'), 100, ''),
        ]);
    }
}
