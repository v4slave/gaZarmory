<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Throwable;

class ParticipantScreenshotRecognizer
{
    public function recognize(UploadedFile $image, Collection $players): array
    {
        $binary = (string) config('services.tesseract.binary', 'tesseract');
        $prepared = $this->prepareImage($image);

        try {
            $outputs = [];
            $passes = [[$image->getRealPath(), 11], [$prepared, 6]];
            if ($prepared !== $image->getRealPath()) $passes[] = [$prepared, 11];
            foreach ($passes as [$inputPath, $pageSegmentationMode]) {
                $process = new Process([
                    $binary,
                    $inputPath,
                    'stdout',
                    '-l',
                    (string) config('services.tesseract.languages', 'rus+eng'),
                    '--psm',
                    (string) $pageSegmentationMode,
                    '-c',
                    'preserve_interword_spaces=1',
                ]);
                $process->setTimeout((int) config('services.tesseract.timeout', 45));
                $process->mustRun();
                $outputs[] = $process->getOutput();
            }
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'screenshot' => 'Не удалось распознать скриншот. Проверьте настройку Tesseract OCR или загрузите другое изображение.',
            ]);
        } finally {
            if ($prepared !== $image->getRealPath()) @unlink($prepared);
        }

        return $this->match(implode("\n", $outputs), $players);
    }

    public function match(string $text, Collection $players): array
    {
        $lines = collect(preg_split('/\R+/u', $text))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->unique()
            ->values();
        $candidates = $lines->flatMap(fn (string $line) => array_merge(
            [$line],
            preg_split('/[\s|]+/u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [],
        ))->map(fn (string $value) => $this->normalize($value))->filter()->unique()->values();

        $matches = $players->map(function ($player) use ($candidates) {
            $nickname = $this->normalize($player->nickname);
            $best = $candidates->map(fn (string $candidate) => $this->similarity($nickname, $candidate))->max() ?? 0;

            return $best >= 0.72 ? [
                'player_id' => $player->id,
                'nickname' => $player->nickname,
                'class' => $player->class->value,
                'confidence' => (int) round($best * 100),
            ] : null;
        })->filter()->sortBy([
            ['confidence', 'desc'],
            ['nickname', 'asc'],
        ])->values()->all();

        return ['matches' => $matches, 'recognized_lines' => $lines->all()];
    }

    private function prepareImage(UploadedFile $image): string
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagescale')) return $image->getRealPath();
        $contents = file_get_contents($image->getRealPath());
        $source = $contents === false ? false : @imagecreatefromstring($contents);
        if ($source === false) return $image->getRealPath();

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = max(2, min(4, (int) ceil(1600 / max($width, $height))));
        $prepared = imagescale($source, $width * $scale, $height * $scale, IMG_BICUBIC_FIXED);
        imagedestroy($source);
        if ($prepared === false) return $image->getRealPath();

        imagefilter($prepared, IMG_FILTER_GRAYSCALE);
        imagefilter($prepared, IMG_FILTER_CONTRAST, -35);
        imageconvolution($prepared, [[-1,-1,-1],[-1,9,-1],[-1,-1,-1]], 1, 0);
        $path = tempnam(sys_get_temp_dir(), 'participant-ocr-');
        if ($path === false || !imagepng($prepared, $path)) {
            imagedestroy($prepared);
            if ($path !== false) @unlink($path);
            return $image->getRealPath();
        }
        imagedestroy($prepared);

        return $path;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '');
    }

    private function similarity(string $nickname, string $line): float
    {
        if ($nickname === '' || $line === '') return 0;
        if (str_contains($line, $nickname)) return 1;
        if (mb_strlen($line) >= 5 && str_starts_with($nickname, $line)) return 0.85;

        $left = preg_split('//u', $nickname, -1, PREG_SPLIT_NO_EMPTY);
        $right = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);
        $previous = range(0, count($right));
        foreach ($left as $i => $leftChar) {
            $current = [$i + 1];
            foreach ($right as $j => $rightChar) {
                $current[] = min($current[$j] + 1, $previous[$j + 1] + 1, $previous[$j] + ($leftChar === $rightChar ? 0 : 1));
            }
            $previous = $current;
        }

        return 1 - ($previous[count($right)] / max(count($left), count($right)));
    }
}
