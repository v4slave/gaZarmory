<?php

namespace Tests\Unit;

use App\Enums\PlayerClass;
use App\Services\ParticipantScreenshotRecognizer;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class ParticipantScreenshotRecognizerTest extends TestCase
{
    public function test_it_matches_exact_misspelled_and_truncated_nicknames(): void
    {
        $players = new Collection([
            $this->player(1, 'Razrivnoipomame'),
            $this->player(2, 'Кошкомальчик'),
            $this->player(3, 'BunnySlash'),
            $this->player(4, 'Отсутствует'),
        ]);

        $result = (new ParticipantScreenshotRecognizer())->match(
            "Razrivnoi      Кошкомальчнк | BunnySlash\nПосторонний текст",
            $players,
        );

        self::assertSame([3, 1, 2], array_column($result['matches'], 'player_id'));
        self::assertSame(100, $result['matches'][0]['confidence']);
        self::assertSame(85, $result['matches'][1]['confidence']);
        self::assertGreaterThanOrEqual(72, $result['matches'][2]['confidence']);
    }

    private function player(int $id, string $nickname): object
    {
        return (object) ['id' => $id, 'nickname' => $nickname, 'class' => PlayerClass::Melee];
    }
}
