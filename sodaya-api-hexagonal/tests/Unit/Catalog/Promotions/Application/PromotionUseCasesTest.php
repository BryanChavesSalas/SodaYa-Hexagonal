<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Promotions\Application;

use PHPUnit\Framework\TestCase;
use Src\Catalog\Promotions\Application\DTOs\DefinePromotionCommand;
use Src\Catalog\Promotions\Application\UseCases\DefinePromotion;
use Src\Catalog\Promotions\Application\UseCases\DeletePromotion;
use Src\Catalog\Promotions\Application\UseCases\ListPromotions;
use Src\Catalog\Promotions\Domain\Entities\Promotion;
use Src\Catalog\Promotions\Domain\Exceptions\PromotionNotFoundException;
use Src\Catalog\Promotions\Domain\Exceptions\PromotionOverlapsException;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\PageRequest;
use Tests\Support\Catalog\InMemoryPromotionRepository;

final class PromotionUseCasesTest extends TestCase
{
    private const string SODA_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22';

    private const string OTHER_SODA_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d23';

    private InMemoryPromotionRepository $promotions;

    /** Start every scenario with an empty in-memory repository. */
    protected function setUp(): void
    {
        $this->promotions = new InMemoryPromotionRepository;
    }

    /** The owner defines happy hours and lists them by day and start time, without those of another soda. */
    public function test_defines_happy_hours_listed_in_order(): void
    {
        $this->define(4, '14:00', '15:00', 10);
        $this->define(3, '16:00', '17:00', 15);
        $this->define(3, '14:00', '15:00', 20);
        $this->define(3, '14:00', '15:00', 20, self::OTHER_SODA_ID);

        $this->assertSame(['3 14:00-15:00 20%', '3 16:00-17:00 15%', '4 14:00-15:00 10%'], $this->listed());
    }

    /** The store refuses a happy hour that shares a minute with another of the same day; the use case passes it on. */
    public function test_overlapping_happy_hour_is_refused_by_the_store(): void
    {
        $this->define(3, '14:00', '15:00', 20);

        try {
            $this->define(3, '14:30', '16:00', 10);
            $this->fail('An overlapping happy hour was accepted.');
        } catch (PromotionOverlapsException $exception) {
            $this->assertSame('promocion-traslapada', $exception->rule());
            $this->assertCount(1, $this->listed());
        }
    }

    /** A happy hour that starts when another ends, on another day or in another soda, is accepted. */
    public function test_touching_and_unrelated_happy_hours_are_accepted(): void
    {
        $this->define(3, '14:00', '15:00', 20);

        $this->define(3, '15:00', '16:00', 10);
        $this->define(4, '14:00', '15:00', 10);
        $this->define(3, '14:00', '15:00', 10, self::OTHER_SODA_ID);

        $this->assertCount(3, $this->listed());
    }

    /** A discount outside 1 to 90 is rejected before anything is stored. */
    public function test_rejects_a_discount_out_of_range(): void
    {
        try {
            $this->define(3, '14:00', '15:00', 95);
            $this->fail('A discount of 95 % was accepted.');
        } catch (InvalidValueException $exception) {
            $this->assertSame('catalog.discount_out_of_range', $exception->translationKey());
            $this->assertSame([], $this->listed());
        }
    }

    /** Deleting removes the happy hour; one of another soda answers as missing and stays. */
    public function test_deletes_only_a_happy_hour_of_the_soda(): void
    {
        $own = $this->define(3, '14:00', '15:00', 20);
        $foreign = $this->define(3, '14:00', '15:00', 20, self::OTHER_SODA_ID);

        new DeletePromotion($this->promotions)->execute($own->id->value, self::SODA_ID);
        $this->assertSame([], $this->listed());

        try {
            new DeletePromotion($this->promotions)->execute($foreign->id->value, self::SODA_ID);
            $this->fail('A happy hour of another soda was deleted.');
        } catch (PromotionNotFoundException) {
            $this->assertCount(1, $this->listed(self::OTHER_SODA_ID));
        }
    }

    /** Define a happy hour through the use case. */
    private function define(int $weekday, string $startsAt, string $endsAt, int $percentage, string $sodaId = self::SODA_ID): Promotion
    {
        return new DefinePromotion($this->promotions)
            ->execute(new DefinePromotionCommand($sodaId, $weekday, $startsAt, $endsAt, $percentage));
    }

    /**
     * Summarize the happy hours of a soda as "day start-end percentage" strings.
     *
     * @return list<string>
     */
    private function listed(string $sodaId = self::SODA_ID): array
    {
        return array_map(
            fn (Promotion $promotion): string => "{$promotion->weekday->value} {$promotion->window->startsAt->value}-{$promotion->window->endsAt->value} {$promotion->percentage->value}%",
            new ListPromotions($this->promotions)->execute($sodaId, new PageRequest)->items,
        );
    }
}
