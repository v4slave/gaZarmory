<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Throwable;

class CharacterBackgroundRemover
{
    public function remove(UploadedFile $image): string
    {
        $directory = storage_path('app/private/character-render-previews');
        File::ensureDirectoryExists($directory);
        $output = tempnam($directory, 'render-');
        $python = (string) config('services.rembg.python_binary');

        try {
            if (!is_file($python)) {
                throw ValidationException::withMessages(['character_screenshot' => 'Обработчик изображений ещё не настроен на сервере.']);
            }

            $process = new Process([
                $python,
                base_path('scripts/remove-character-background.py'),
                $image->getRealPath(),
                $output,
                (string) config('services.rembg.model'),
            ], null, [
                'U2NET_HOME' => (string) config('services.rembg.model_dir'),
                'OMP_NUM_THREADS' => '2',
            ]);
            $process->setTimeout((int) config('services.rembg.timeout', 90));
            $process->mustRun();

            $contents = file_get_contents($output);
            if ($contents === false || $contents === '') {
                throw new \RuntimeException('Background remover returned an empty image.');
            }

            return $contents;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['character_screenshot' => 'Не удалось вырезать персонажа. Попробуйте другой скриншот.']);
        } finally {
            @unlink($output);
        }
    }
}
