<?php

namespace Tests;

use App\Models\Media;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // حافظه‌ی درون‌درخواستیِ forRecord() استاتیک است؛ در تست‌ها که همه در یک پروسه‌اند بین
        // تست‌ها نشت می‌کند (RefreshDatabase فقط DB را ریست می‌کند نه state استاتیک PHP را)
        Media::flushRecordCache();
    }
}
