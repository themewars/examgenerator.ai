<?php

namespace App\Services;

class OgImageService
{
    /**
     * Generate a simple 1200x630 PNG with random background and centered title text.
     * Uses PHP GD which is commonly available.
     * Returns relative path under public/images/og.
     */
    public static function generateForQuiz(string $uniqueCode, string $title): string
    {
        $safeCode = preg_replace('/[^A-Z0-9_-]/i', '', strtoupper($uniqueCode));
        $publicDir = public_path('images/og');
        if (!is_dir($publicDir)) @mkdir($publicDir, 0775, true);

        $width = 1200; $height = 630;
        $im = imagecreatetruecolor($width, $height);

        // Random pleasant background
        $palettes = [
            [26, 26, 26],    // dark
            [36, 99, 235],   // blue
            [16, 185, 129],  // green
            [217, 119, 6],   // orange
            [139, 92, 246],  // violet
            [220, 38, 38],   // red
        ];
        $bgc = $palettes[array_rand($palettes)];
        $bg = imagecolorallocate($im, $bgc[0], $bgc[1], $bgc[2]);
        imagefilledrectangle($im, 0, 0, $width, $height, $bg);

        // Title text settings
        $white = imagecolorallocate($im, 255, 255, 255);
        $title = trim($title) !== '' ? $title : 'Exam';
        $title = mb_substr($title, 0, 90);

        // Try to use a TTF font if available
        $fontPath = base_path('resources/fonts/NotoSansDevanagari-Bold.ttf');
        $hasFont = file_exists($fontPath) && function_exists('imagettftext');

        if ($hasFont) {
            $fontSize = 48; // reasonable size
            // Auto-wrap long titles
            $wrapped = self::wrapTtfText($title, $fontPath, $fontSize, $width - 160);
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $wrapped);
            $textW = abs($bbox[4] - $bbox[0]);
            $textH = abs($bbox[5] - $bbox[1]);
            $x = intval(($width - $textW) / 2);
            $y = intval(($height + $textH) / 2);
            imagettftext($im, $fontSize, 0, $x, $y, $white, $fontPath, $wrapped);
        } else {
            // Fallback to GD built-in font
            $font = 5; // largest built-in
            $wrapped = wordwrap($title, 28, "\n");
            $lines = explode("\n", $wrapped);
            $lineH = imagefontheight($font) + 6;
            $totalH = count($lines) * $lineH;
            $y = intval(($height - $totalH) / 2);
            foreach ($lines as $line) {
                $textW = imagefontwidth($font) * strlen($line);
                $x = intval(($width - $textW) / 2);
                imagestring($im, $font, $x, $y, $line, $white);
                $y += $lineH;
            }
        }

        $file = $publicDir . DIRECTORY_SEPARATOR . $safeCode . '.png';
        imagepng($im, $file, 6);
        imagedestroy($im);
        return 'images/og/' . $safeCode . '.png';
    }

    private static function wrapTtfText(string $text, string $font, int $size, int $maxWidth): string
    {
        $words = preg_split('/\s+/u', $text);
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            $test = trim($line . ' ' . $word);
            $box = imagettfbbox($size, 0, $font, $test);
            $w = abs($box[4] - $box[0]);
            if ($w > $maxWidth && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $test;
            }
        }
        if ($line !== '') $lines[] = $line;
        return implode("\n", $lines);
    }
}


