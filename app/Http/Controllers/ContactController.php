<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageMail;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContactController extends Controller
{
    // فرم تماس صفحه‌ی Contact (AJAX) — همان الگوی ضد اسپم فرم خبرنامه (هانی‌پات + سد زمانی)
    public function submit(Request $request)
    {
        $locale = in_array($request->input('locale'), ['en', 'tr'], true) ? $request->input('locale') : 'en';
        app()->setLocale($locale);

        // هانی‌پات: فیلد مخفی «website» برای انسان‌ها نامرئی است؛ پرشدنش یعنی بات.
        // به بات پاسخ موفق الکی می‌دهیم تا روش را عوض نکند — ولی هیچ ایمیلی ارسال نمی‌شود.
        if ($request->filled('website')) {
            $this->logSuspicious($request, 'honeypot_filled');

            return response()->json(['ok' => true, 'message' => __('contact.sent')]);
        }

        // سد زمانی: فرم کمتر از ۳ ثانیه بعد از رندر صفحه ارسال شده = بات
        if (! $this->passesTimeGate($request)) {
            $this->logSuspicious($request, 'time_gate_failed');

            return response()->json(['ok' => true, 'message' => __('contact.sent')]);
        }

        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:150'],
                'email' => ['required', 'string', 'email:rfc', 'max:255'],
                'message' => ['required', 'string', 'max:5000'],
            ]);
        } catch (ValidationException) {
            return response()->json(['ok' => false, 'message' => __('contact.invalid')], 422);
        }

        $recipient = SiteSetting::where('key', 'footer.en.contact_email')->value('value');

        if (! $recipient) {
            return response()->json(['ok' => false, 'message' => __('contact.not_configured')], 422);
        }

        try {
            Mail::to($recipient)->send(new ContactMessageMail(
                name: trim($validated['name']),
                senderEmail: Str::lower(trim($validated['email'])),
                messageBody: trim($validated['message']),
                senderLocale: $locale,
            ));
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'message' => __('contact.error')], 500);
        }

        return response()->json(['ok' => true, 'message' => __('contact.sent')]);
    }

    // سد زمانی: مُهر زمانی رمزشده هنگام رندر صفحه در فرم گذاشته می‌شود؛
    // دست‌کاری‌شده یا زودتر از ۳ ثانیه = بات
    private function passesTimeGate(Request $request): bool
    {
        try {
            $renderedAt = (int) Crypt::decryptString((string) $request->input('_ct_ts'));
        } catch (\Throwable) {
            return false;
        }

        return (now()->timestamp - $renderedAt) >= 3;
    }

    private function logSuspicious(Request $request, string $reason): void
    {
        Log::warning('contact.suspicious_request', [
            'reason' => $reason,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 300, ''),
            'email_input' => Str::limit((string) $request->input('email'), 100, ''),
        ]);
    }
}
