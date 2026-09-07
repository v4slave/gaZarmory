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
        $process = new Process([
            $binary,
            $image->getRealPath(),
            'stdout',
            '-l',
            (string) config('services.tesseract.languages', 'rus+eng'),
            '--psm',
            '11',
        ]);
        $process->setTimeout((int) config('services.tesseract.timeout', 45));

        try {
            $process->mustRun();
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'screenshot' => 'Не удалось распознать скриншот. Проверьте настройку Tesseract OCR или загрузите другое изображение.',
            ]);
        }

        return $this->match($process->getOutput(), $players);
    }

    public function match(string $text, Collection $players): array
    {
        $lines = collect(preg_split('/\R+/u', $text))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->unique()
            ->values();

        $matches = $players->map(function ($player) use ($lines) {
            $nickname = $this->normalize($player->nickname);
            $best = $lines->map(fn (string $line) => $this->similarity($nickname, $this->normalize($line)))->max() ?? 0;

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
