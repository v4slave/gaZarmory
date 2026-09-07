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
        $prepared = $this->prepareImages($image);

        try {
            $outputs = [];
            $passes = [[$image->getRealPath(), 11]];
            foreach ($prepared as $index => $inputPath) $passes[] = [$inputPath, $index >= 2 ? 6 : 11];
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
            foreach ($prepared as $path) @unlink($path);
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

            return $best >= 0.58 ? [
                'player_id' => $player->id,
                'nickname' => $player->nickname,
                'class' => $player->class->value,
                'confidence' => (int) round($best * 100),
            ] : null;
        })->filter()->sortBy('nickname', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();

        return ['matches' => $matches, 'recognized_lines' => $lines->all()];
    }

    private function prepareImages(UploadedFile $image): array
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagescale')) return [];
        $contents = file_get_contents($image->getRealPath());
        $source = $contents === false ? false : @imagecreatefromstring($contents);
        if ($source === false) return [];

        $width = imagesx($source);
        $height = imagesy($source);
        $cellSheetPath = $this->prepareCellSheet($source);
        $groupSheetPath = $this->prepareGroupSheet($source);
        $scale = max(2, min(4, (int) ceil(1600 / max($width, $height))));
        $prepared = imagescale($source, $width * $scale, $height * $scale, IMG_BICUBIC_FIXED);
        imagedestroy($source);
        if ($prepared === false) return [];

        imagefilter($prepared, IMG_FILTER_GRAYSCALE);
        imagefilter($prepared, IMG_FILTER_CONTRAST, -35);
        imageconvolution($prepared, [[-1,-1,-1],[-1,9,-1],[-1,-1,-1]], 1, 0);
        $normalPath = $this->writeTemporaryPng($prepared);
        imagefilter($prepared, IMG_FILTER_NEGATE);
        imagefilter($prepared, IMG_FILTER_CONTRAST, -20);
        $invertedPath = $this->writeTemporaryPng($prepared);
        imagedestroy($prepared);

        return array_values(array_filter([$normalPath, $invertedPath, $cellSheetPath, $groupSheetPath]));
    }

    private function prepareCellSheet(\GdImage $source): ?string
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $rowProjection = array_fill(0, $height, 0);
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($this->isRaidFramePixel(imagecolorat($source, $x, $y))) $rowProjection[$y]++;
            }
        }
        $rows = $this->projectionBands($rowProjection, max(25, (int) ($width * .12)), 12, 60);
        $cells = [];
        foreach ($rows as [$top, $bottom]) {
            $columnProjection = array_fill(0, $width, 0);
            for ($x = 0; $x < $width; $x++) {
                for ($y = $top; $y <= $bottom; $y++) {
                    if ($this->isRaidFramePixel(imagecolorat($source, $x, $y))) $columnProjection[$x]++;
                }
            }
            $columns = $this->projectionBands($columnProjection, max(5, (int) (($bottom - $top + 1) * .35)), 20, 140);
            foreach ($columns as [$left, $right]) {
                $cells[] = [$left, $top, $right - $left + 1, $bottom - $top + 1];
            }
        }
        if (count($cells) < 10) $cells = $this->detectLightBackgroundCells($source);
        if (count($cells) < 10 || count($cells) > 150) return null;

        $sheetWidth = 420;
        $rowHeight = 110;
        $sheet = imagecreatetruecolor($sheetWidth, count($cells) * $rowHeight);
        imagefill($sheet, 0, 0, imagecolorallocate($sheet, 255, 255, 255));
        foreach ($cells as $index => [$left, $top, $cellWidth, $cellHeight]) {
            $crop = imagecrop($source, ['x'=>$left,'y'=>$top,'width'=>$cellWidth,'height'=>max(8, (int) ($cellHeight * .82))]);
            if ($crop === false) continue;
            $scaled = imagescale($crop, min(400, $cellWidth * 6), min(100, $cellHeight * 5), IMG_BICUBIC_FIXED);
            imagedestroy($crop);
            if ($scaled === false) continue;
            imagefilter($scaled, IMG_FILTER_GRAYSCALE);
            imagefilter($scaled, IMG_FILTER_CONTRAST, -35);
            imagefilter($scaled, IMG_FILTER_NEGATE);
            imagecopy($sheet, $scaled, 8, $index * $rowHeight + 5, 0, 0, imagesx($scaled), imagesy($scaled));
            imagedestroy($scaled);
        }
        $path = $this->writeTemporaryPng($sheet);
        imagedestroy($sheet);

        return $path;
    }

    private function prepareGroupSheet(\GdImage $source): ?string
    {
        $width = imagesx($source);
        $height = imagesy($source);
        if ($width / max(1, $height) < 1.2) return null;

        $sheetWidth = 900;
        $rowHeight = 240;
        $sheet = imagecreatetruecolor($sheetWidth, $rowHeight * 10);
        imagefill($sheet, 0, 0, imagecolorallocate($sheet, 255, 255, 255));
        $top = (int) round($height * .18);
        $middle = (int) round($height * .56);
        $bottom = (int) round($height * .88);
        $groupWidth = (int) floor($width / 5);
        foreach (range(0, 9) as $index) {
            $cropTop = $index < 5 ? $top : $middle;
            $cropBottom = $index < 5 ? $middle - 2 : $bottom;
            $column = $index % 5;
            $crop = imagecrop($source, ['x'=>$column * $groupWidth, 'y'=>$cropTop, 'width'=>min($groupWidth, $width - $column * $groupWidth), 'height'=>max(8, $cropBottom - $cropTop)]);
            if ($crop === false) continue;
            $scaled = imagescale($crop, min(880, imagesx($crop) * 5), min(225, imagesy($crop) * 3), IMG_BICUBIC_FIXED);
            imagedestroy($crop);
            if ($scaled === false) continue;
            imagefilter($scaled, IMG_FILTER_GRAYSCALE);
            imagefilter($scaled, IMG_FILTER_CONTRAST, -25);
            imagefilter($scaled, IMG_FILTER_NEGATE);
            imagecopy($sheet, $scaled, 10, $index * $rowHeight + 5, 0, 0, imagesx($scaled), imagesy($scaled));
            imagedestroy($scaled);
        }
        $path = $this->writeTemporaryPng($sheet);
        imagedestroy($sheet);

        return $path;
    }

    private function detectLightBackgroundCells(\GdImage $source): array
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $projection = array_fill(0, $height, 0);
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($this->isDarkRaidFramePixel(imagecolorat($source, $x, $y))) $projection[$y]++;
            }
        }
        $anchors = $this->projectionBands($projection, max(50, (int) ($width * .55)), 2, 12);
        $columnProjection = array_fill(0, $width, 0);
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                if ($this->isDarkRaidFramePixel(imagecolorat($source, $x, $y))) $columnProjection[$x]++;
            }
        }
        $columns = $this->projectionBands($columnProjection, max(20, (int) ($height * .1)), 20, 140);
        if (count($columns) < 3 || count($columns) > 10) return [];
        $cells = [];
        foreach ($anchors as [$top]) {
            $bottom = min($height - 1, $top + 30);
            foreach ($columns as [$left, $right]) $cells[] = [$left, $top, $right - $left + 1, $bottom - $top + 1];
        }

        return $cells;
    }

    private function isRaidFramePixel(int $color): bool
    {
        $red = ($color >> 16) & 0xff;
        $green = ($color >> 8) & 0xff;
        $blue = $color & 0xff;
        $maximum = max($red, $green, $blue);
        $minimum = min($red, $green, $blue);

        return $blue > 38 && $maximum < 250 && $minimum < 175 && ($maximum - $minimum) > 18
            && ($blue > $red * .45 || $red > $green * 1.25);
    }

    private function isDarkRaidFramePixel(int $color): bool
    {
        $red = ($color >> 16) & 0xff;
        $green = ($color >> 8) & 0xff;
        $blue = $color & 0xff;

        return $blue > 38 && max($red, $green, $blue) < 180
            && (max($red, $green, $blue) - min($red, $green, $blue)) > 18
            && ($blue > $red * .45 || $red > $green * 1.25);
    }

    private function projectionBands(array $projection, int $threshold, int $minimumSize, int $maximumSize): array
    {
        $bands = [];
        $start = null;
        foreach ($projection as $position => $count) {
            if ($count >= $threshold && $start === null) $start = $position;
            if ($count >= $threshold || $start === null) continue;
            $size = $position - $start;
            if ($size >= $minimumSize && $size <= $maximumSize) $bands[] = [$start, $position - 1];
            $start = null;
        }
        if ($start !== null) {
            $size = count($projection) - $start;
            if ($size >= $minimumSize && $size <= $maximumSize) $bands[] = [$start, count($projection) - 1];
        }

        return $bands;
    }

    private function writeTemporaryPng(\GdImage $image): ?string
    {
        $path = tempnam(sys_get_temp_dir(), 'participant-ocr-');
        if ($path === false || !imagepng($image, $path)) {
            if ($path !== false) @unlink($path);
            return null;
        }

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
