<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog\Promotions;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Catalog\Promotions\Application\Contracts\ActivePromotion;
use Src\Catalog\Promotions\Infrastructure\Persistence\Models\PromotionModel;
use Src\Catalog\Promotions\Infrastructure\Persistence\Queries\EloquentActivePromotion;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class EloquentActivePromotionTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    private SodaId $sodaId;

    private EloquentActivePromotion $promotion;

    /** A soda with 20 % off on Wednesdays from 14:00 to 15:00, and another soda with the same happy hour. */
    protected function setUp(): void
    {
        parent::setUp();

        $soda = SodaModel::factory()->create();
        PromotionModel::factory()->create(['soda_id' => $soda->id, 'weekday' => 3, 'starts_at' => '14:00', 'ends_at' => '15:00', 'percentage' => 20]);
        PromotionModel::factory()->create(['weekday' => 3, 'starts_at' => '14:00', 'ends_at' => '15:00', 'percentage' => 50]);

        $this->sodaId = new SodaId($soda->id);
        $this->promotion = new EloquentActivePromotion;
        $this->scopeQueriesTo($soda->id);
    }

    /** The percentage follows the window on the clock of Costa Rica: the start minute is in, the end minute is out. */
    #[DataProvider('moments')]
    public function test_percentage_follows_the_window(string $moment, int $percentage): void
    {
        $this->assertSame($percentage, $this->promotion->percentageFor($this->sodaId, $this->costaRica($moment)));
    }

    /**
     * Moments of Wednesday 2026-10-21 in Costa Rica and the percentage that applies.
     *
     * @return array<string, array{string, int}>
     */
    public static function moments(): array
    {
        return [
            'minute before the start' => ['2026-10-21 13:59:59', 0],
            'start minute' => ['2026-10-21 14:00:00', 20],
            'last minute' => ['2026-10-21 14:59:59', 20],
            'end minute' => ['2026-10-21 15:00:00', 0],
            'same time on thursday' => ['2026-10-22 14:30:00', 0],
        ];
    }

    /** A moment given in UTC is converted to Costa Rica before comparing: 20:30 UTC is 14:30 in the soda. */
    public function test_moment_is_read_in_costa_rica_time(): void
    {
        $utc = new DateTimeImmutable('2026-10-21 20:30:00', new DateTimeZone('UTC'));

        $this->assertSame(20, $this->promotion->percentageFor($this->sodaId, $utc));
    }

    /** Asked twenty times in the same minute, the reader queries the table once; another minute is another query. */
    public function test_queries_once_per_soda_and_minute(): void
    {
        DB::enableQueryLog();

        for ($dish = 0; $dish < 20; $dish++) {
            $this->promotion->percentageFor($this->sodaId, $this->costaRica('2026-10-21 14:30:'.sprintf('%02d', $dish)));
        }
        $this->assertCount(1, DB::getQueryLog());

        $this->promotion->percentageFor($this->sodaId, $this->costaRica('2026-10-21 14:31:00'));
        $this->assertCount(2, DB::getQueryLog());
    }

    /** The happy hour of another soda never applies: the query filters by soda and the transaction of a soda hides the rest. */
    public function test_another_soda_is_not_seen(): void
    {
        $other = new SodaId(SodaModel::factory()->create()->id);

        $this->assertSame(0, $this->promotion->percentageFor($other, $this->costaRica('2026-10-21 14:30:00')));

        $this->scopeQueriesTo($other->value);
        $this->assertSame(0, new EloquentActivePromotion()->percentageFor($this->sodaId, $this->costaRica('2026-10-21 14:30:00')));
    }

    /** The container hands out one reader per request, remembered between the dishes of a menu. */
    public function test_reader_is_scoped_to_the_request(): void
    {
        $this->assertSame($this->app->make(ActivePromotion::class), $this->app->make(ActivePromotion::class));
    }

    /** A moment of Costa Rica. */
    private function costaRica(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('America/Costa_Rica'));
    }
}
