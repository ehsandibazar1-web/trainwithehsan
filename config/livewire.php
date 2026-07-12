<?php

return [

    /*
    |--------------------------------------------------------------------------
    | آپلود موقت فایل‌ها (Livewire)
    |--------------------------------------------------------------------------
    |
    | پیش‌فرض Livewire حداکثر ۱۲ مگابایت برای هر آپلود موقت است که برای
    | ویدیو کافی نیست. اینجا سقف را به ۱۲۸ مگابایت (131072 کیلوبایت)
    | افزایش داده‌ایم و زمان مجاز آپلود را هم بیشتر کرده‌ایم.
    |
    | توجه: محدودیت‌های PHP هاست (upload_max_filesize و post_max_size)
    | هم باید حداقل همین مقدار باشند — از MultiPHP INI Editor تنظیم شود.
    |
    */

    'temporary_file_upload' => [
        'disk' => null,
        'rules' => ['required', 'file', 'max:131072'],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 10,
        'cleanup' => true,
    ],

];
