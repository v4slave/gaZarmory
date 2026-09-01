<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

final class ArchaGearImporter
{
    private const SLOT_NAMES = [
        'Костюм', 'Голова', 'Нагрудник', 'Пояс', 'Наручи', 'Перчатки', 'Плащ', 'Поножи', 'Обувь',
        'Бельё', 'Ожерелье', 'Серьга 1', 'Серьга 2', 'Кольцо 1', 'Кольцо 2', 'Основное оружие',
        'Левая рука', 'Лук', 'Музыкальный инструмент',
    ];

    public function import(string $url): array
    {
        $url = $this->validatedUrl($url);
        $request = Http::accept('text/html')->timeout(15)->retry(2, 250);
        if (app()->environment('local')) $request->withoutVerifying();
        $response = $request->get($url);
        if (! $response->successful()) {
            throw ValidationException::withMessages(['archa_gear_url' => 'Не удалось загрузить билд с archa.ge.']);
        }

        $items = $this->parse($response->body());
        if ($items === []) $items = $this->importWithBrowser($url);
        if ($items === []) {
            throw ValidationException::withMessages(['archa_gear_url' => 'В билде не найдена экипировка. Проверьте ссылку и доступность билда.']);
        }

        return ['url' => $url, 'items' => $items];
    }

    private function importWithBrowser(string $url): array
    {
        $process = new Process([
            (string) config('services.archa.node_binary', 'node'),
            base_path('scripts/import-archa-gear.mjs'),
            $url,
        ]);
        $process->setTimeout(45);
        $process->run();

        if (! $process->isSuccessful()) {
            report(new \RuntimeException('Archa browser import failed: '.trim($process->getErrorOutput())));
            return [];
        }

        $items = json_decode($process->getOutput(), true);
        if (! is_array($items)) return [];

        return array_values(array_map(fn (array $item): array => [
            'slot' => $item['slot'],
            'name' => $item['name'],
            'quality' => $item['quality'] ?? '',
            'grade' => $item['grade'] ?? '',
            'image_url' => '/api/archa-gear/items/'.$item['item_id'],
            'stats' => $item['stats'] ?? [],
            'rune' => isset($item['rune']) ? [
                'text' => $item['rune']['text'],
                'grade' => $item['rune']['grade'] ?? '',
                'image_url' => '/api/archa-gear/assets/runes/'.$item['rune']['id'],
            ] : null,
            'gems' => array_map(fn (array $gem): array => [
                'text' => $gem['text'],
                'grade' => $gem['grade'] ?? '',
                'image_url' => '/api/archa-gear/assets/gems/'.$gem['id'],
            ], $item['gems'] ?? []),
            'synthesis' => $item['synthesis'] ?? [],
        ], $items));
    }

    public function parse(string $html): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) return [];

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' aa-itemslot ')]");
        $items = [];
        foreach ($nodes ?: [] as $index => $node) {
            if (! $node instanceof DOMElement || $index >= count(self::SLOT_NAMES)) continue;
            $image = $xpath->query('./img[not(@class)][1]', $node)?->item(0);
            $grade = $xpath->query("./img[contains(concat(' ', normalize-space(@class), ' '), ' aa-gradecorner ')][1]", $node)?->item(0);
            $src = $image instanceof DOMElement ? $image->getAttribute('src') : '';
            $popover = $grade instanceof DOMElement ? html_entity_decode($grade->getAttribute('data-bs-content'), ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
            if ($src === '' || str_contains($src, '/slots/')) continue;

            [$quality, $name] = $this->itemHeading($popover);
            $items[] = [
                'slot' => self::SLOT_NAMES[$index],
                'name' => $name ?: 'Предмет',
                'quality' => $quality,
                'image_url' => $this->absoluteAssetUrl($src),
                'grade' => $this->gradeName($grade instanceof DOMElement ? $grade->getAttribute('src') : ''),
            ];
        }

        return $items;
    }

    private function validatedUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        parse_str($parts['query'] ?? '', $query);
        if (($parts['scheme'] ?? '') !== 'https' || strtolower($parts['host'] ?? '') !== 'archa.ge'
            || ! ctype_digit((string) ($query['u'] ?? '')) || ! ctype_digit((string) ($query['bid'] ?? ''))) {
            throw ValidationException::withMessages(['archa_gear_url' => 'Нужна ссылка вида https://archa.ge/?u=…&bid=…']);
        }
        return 'https://archa.ge/?u='.$query['u'].'&bid='.$query['bid'];
    }

    private function itemHeading(string $popover): array
    {
        if (! preg_match("~<div class=['\"]col-9[^'\"]*['\"]>\s*(.*?)\s*<br\s*/?>\s*(.*?)\s*</div>~si", $popover, $match)) return ['', ''];
        return [trim(strip_tags($match[1])), trim(strip_tags($match[2]))];
    }

    private function absoluteAssetUrl(string $src): string
    {
        return preg_match('~/items/(\d+)\.jpg~', $src, $match) ? '/api/archa-gear/items/'.$match[1] : '';
    }

    private function gradeName(string $src): string
    {
        return preg_match('/item_grade_([A-Za-z]+)\.png/', $src, $match) ? strtolower($match[1]) : '';
    }
}
