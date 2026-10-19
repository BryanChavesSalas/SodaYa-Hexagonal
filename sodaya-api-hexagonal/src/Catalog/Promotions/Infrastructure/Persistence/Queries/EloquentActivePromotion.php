<?php

declare(strict_types=1);

namespace Src\Catalog\Promotions\Infrastructure\Persistence\Queries;

use DateTimeImmutable;
use DateTimeZone;
use Src\Catalog\Promotions\Application\Contracts\ActivePromotion;
use Src\Catalog\Promotions\Infrastructure\Persistence\Models\PromotionModel;
use Src\Shared\Domain\ValueObjects\SodaId;

/**
 * Scoped to the request: a menu of twenty dishes asks twenty times and queries once per soda and minute.
 */
final class EloquentActivePromotion implements ActivePromotion
{
    private const string TIME_ZONE = 'America/Costa_Rica';

    /** @var array<string, int> percentage by soda and minute */
    private array $remembered = [];

    /** Read the happy hour that covers the moment on the clock of Costa Rica; the end minute is already out. */
    public function percentageFor(SodaId $sodaId, DateTimeImmutable $moment): int
    {
        $local = $moment->setTimezone(new DateTimeZone(self::TIME_ZONE));
        $key = "{$sodaId->value}@{$local->format('Y-m-d H:i')}";

        return $this->remembered[$key] ??= (int) PromotionModel::query()
            ->where('soda_id', $sodaId->value)
            ->where('weekday', (int) $local->format('N'))
            ->where('starts_at', '<=', $local->format('H:i:s'))
            ->where('ends_at', '>', $local->format('H:i:s'))
            ->value('percentage');
    }
}
