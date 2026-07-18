<?php

namespace App\Services\Media;

/**
 * مدتِ زمانِ یک ویدیوی خودمیزبان را بدونِ هیچ وابستگی (نه ffprobe، نه getID3) از خودِ فایل می‌خواند —
 * ساختارِ اتمی ISO-BMFF (mp4/m4v/mov): اتمِ moov ← mvhd که timescale و duration را دارد. همان سبکِ
 * «ابزارِ دست‌سازِ سبک به‌جای وابستگیِ سنگین» که ChunkingService/DiffService دارند.
 *
 * عمداً فقط mp4/mov (فرمتِ غالبِ ویدیوهای خودمیزبان) — هر فایلِ دیگر یا خرابی → null (duration در
 * VideoObject «توصیه‌شده» است نه «الزامی»، پس nullِ درست بهتر از حدسِ اشتباه است). هرگز throw نمی‌کند.
 */
class VideoMetadataService
{
    // سقفِ پیمایشِ اتم‌ها — سپرِ فایلِ خراب/مخرب تا در حلقه گیر نکنیم
    private const MAX_ATOMS = 256;

    /** مدتِ زمان به ثانیه، یا null اگر خوانده نشود. */
    public function durationSeconds(string $absolutePath): ?int
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return null;
        }

        $fileSize = @filesize($absolutePath);
        if ($fileSize === false || $fileSize < 16) {
            return null;
        }

        $fh = @fopen($absolutePath, 'rb');
        if ($fh === false) {
            return null;
        }

        try {
            $moov = $this->findAtom($fh, 'moov', 0, $fileSize);
            if ($moov === null) {
                return null;
            }

            // mvhd فرزندِ مستقیمِ moov است (بعد از هدرِ ۸ بایتیِ moov)
            $mvhd = $this->findAtom($fh, 'mvhd', $moov[0] + 8, $moov[0] + $moov[1]);
            if ($mvhd === null) {
                return null;
            }

            return $this->readDurationFromMvhd($fh, $mvhd[0]);
        } catch (\Throwable) {
            return null;
        } finally {
            fclose($fh);
        }
    }

    /**
     * ثانیه → مدتِ ISO-8601 (PT#H#M#S) برای VideoObject.duration. صفر/منفی → null.
     */
    public static function toIso8601(?int $seconds): ?string
    {
        if ($seconds === null || $seconds <= 0) {
            return null;
        }

        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        $out = 'PT';
        if ($h > 0) {
            $out .= $h.'H';
        }
        if ($m > 0) {
            $out .= $m.'M';
        }
        if ($s > 0 || ($h === 0 && $m === 0)) {
            $out .= $s.'S';
        }

        return $out;
    }

    /**
     * مدتِ ISO-8601 (فقط شکلِ PT#H#M#S که خودمان می‌سازیم) → ثانیه — برای <video:duration> در سایت‌مپ.
     */
    public static function iso8601ToSeconds(?string $iso): ?int
    {
        if (! $iso || ! preg_match('~^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$~', $iso, $m)) {
            return null;
        }

        $seconds = ((int) ($m[1] ?? 0)) * 3600 + ((int) ($m[2] ?? 0)) * 60 + ((int) ($m[3] ?? 0));

        return $seconds > 0 ? $seconds : null;
    }

    /**
     * اولین اتمِ هم‌ترازِ $type را در بازه‌ی [$start, $end) پیدا می‌کند.
     *
     * @param  resource  $fh
     * @return array{0: int, 1: int}|null [offsetِ شروعِ اتم، اندازه‌ی کلِ اتم]
     */
    private function findAtom($fh, string $type, int $start, int $end): ?array
    {
        $offset = $start;
        $count = 0;

        while ($offset + 8 <= $end && $count < self::MAX_ATOMS) {
            $count++;
            fseek($fh, $offset);
            $header = fread($fh, 8);
            if ($header === false || strlen($header) < 8) {
                return null;
            }

            $size = $this->uint32(substr($header, 0, 4));
            $atomType = substr($header, 4, 4);
            $headerSize = 8;

            if ($size === 1) {
                // اندازه‌ی ۶۴ بیتیِ توسعه‌یافته
                $ext = fread($fh, 8);
                if ($ext === false || strlen($ext) < 8) {
                    return null;
                }
                $size = $this->uint64($ext);
                $headerSize = 16;
            } elseif ($size === 0) {
                // تا انتهای بازه
                $size = $end - $offset;
            }

            if ($size < $headerSize) {
                return null; // اندازه‌ی نامعتبر → توقف
            }

            if ($atomType === $type) {
                return [$offset, $size];
            }

            $offset += $size;
        }

        return null;
    }

    /**
     * @param  resource  $fh
     */
    private function readDurationFromMvhd($fh, int $mvhdStart): ?int
    {
        fseek($fh, $mvhdStart + 8); // ردکردنِ size(4)+type(4)
        $version = ord(fread($fh, 1) ?: "\0");
        fseek($fh, 3, SEEK_CUR); // flags

        if ($version === 1) {
            fseek($fh, 16, SEEK_CUR); // creation(8)+modification(8)
            $timescale = $this->uint32(fread($fh, 4) ?: '');
            $duration = $this->uint64(fread($fh, 8) ?: '');
        } else {
            fseek($fh, 8, SEEK_CUR); // creation(4)+modification(4)
            $timescale = $this->uint32(fread($fh, 4) ?: '');
            $duration = $this->uint32(fread($fh, 4) ?: '');
        }

        if ($timescale <= 0 || $duration <= 0) {
            return null;
        }

        $seconds = (int) round($duration / $timescale);

        return $seconds > 0 ? $seconds : null;
    }

    private function uint32(string $bytes): int
    {
        if (strlen($bytes) < 4) {
            return 0;
        }

        return (int) (unpack('N', $bytes)[1] ?? 0);
    }

    private function uint64(string $bytes): int
    {
        if (strlen($bytes) < 8) {
            return 0;
        }

        // J = unsigned 64-bit big-endian؛ intِ PHP روی این پلتفرم ۶۴ بیتی است
        return (int) (unpack('J', $bytes)[1] ?? 0);
    }
}
