<?php

namespace App\Services;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class CaptchaService
{
    private const SESSION_KEY = 'captcha_code';
    private const CODE_LENGTH = 6;
    private const IMAGE_WIDTH = 200;
    private const IMAGE_HEIGHT = 60;

    public function generate(): string
    {
        $code = $this->generateCode();
        Session::put(self::SESSION_KEY, strtolower($code));

        return $this->renderImage($code);
    }

    public function verify(string $input): bool
    {
        $stored = Session::pull(self::SESSION_KEY);

        if ($stored === null) {
            return false;
        }

        return strtolower(trim($input)) === $stored;
    }

    private function generateCode(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';

        return Str::substr(str_shuffle($characters), 0, self::CODE_LENGTH);
    }

    private function renderImage(string $code): string
    {
        $image = imagecreatetruecolor(self::IMAGE_WIDTH, self::IMAGE_HEIGHT);

        $bgColor = imagecolorallocate($image, 245, 245, 245);
        imagefill($image, 0, 0, $bgColor);

        // Add noise lines
        for ($i = 0; $i < 8; $i++) {
            $lineColor = imagecolorallocate(
                $image,
                random_int(150, 220),
                random_int(150, 220),
                random_int(150, 220)
            );
            imageline(
                $image,
                random_int(0, self::IMAGE_WIDTH),
                random_int(0, self::IMAGE_HEIGHT),
                random_int(0, self::IMAGE_WIDTH),
                random_int(0, self::IMAGE_HEIGHT),
                $lineColor
            );
        }

        // Add noise dots
        for ($i = 0; $i < 100; $i++) {
            $dotColor = imagecolorallocate(
                $image,
                random_int(100, 200),
                random_int(100, 200),
                random_int(100, 200)
            );
            imagesetpixel(
                $image,
                random_int(0, self::IMAGE_WIDTH),
                random_int(0, self::IMAGE_HEIGHT),
                $dotColor
            );
        }

        // Draw each character with slight rotation
        $fontSize = 5; // GD built-in font size (1-5)
        $charWidth = imagefontwidth($fontSize);
        $charHeight = imagefontheight($fontSize);
        $startX = (self::IMAGE_WIDTH - (strlen($code) * ($charWidth + 8))) / 2;
        $startY = (self::IMAGE_HEIGHT - $charHeight) / 2;

        for ($i = 0; $i < strlen($code); $i++) {
            $textColor = imagecolorallocate(
                $image,
                random_int(10, 100),
                random_int(10, 100),
                random_int(10, 100)
            );
            $x = (int) ($startX + $i * ($charWidth + 8));
            $y = (int) ($startY + random_int(-5, 5));
            imagestring($image, $fontSize, $x, $y, $code[$i], $textColor);
        }

        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,' . base64_encode($imageData);
    }
}
