<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Catalog;

use DateTimeImmutable;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Catalog\Promotions\Application\Contracts\ActivePromotion;
use Src\Ordering\Orders\Application\Contracts\OrderableDishes;
use Src\Ordering\Orders\Application\DTOs\OrderableDish;
use Src\Shared\Domain\ValueObjects\Money;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class EloquentOrderableDishes implements OrderableDishes
{
    /** Receive the port of the catalog that knows the happy hour of a soda. */
    public function __construct(private ActivePromotion $promotion) {}

    /**
     * Read the requested dishes of the soda in one query, with the happy hour of the moment already taken off
     * their price, so the line freezes what the visitor saw on the menu; the ones of other sodas never come back.
     *
     * @param  list<string>  $dishIds
     * @return array<string, OrderableDish>
     */
    public function ofSoda(SodaId $sodaId, array $dishIds, DateTimeImmutable $now): array
    {
        $dishes = [];
        $percentage = $this->promotion->percentageFor($sodaId, $now);

        $models = DishModel::query()
            ->where('soda_id', $sodaId->value)
            ->whereIn('id', $dishIds)
            ->get();

        foreach ($models as $model) {
            $dishes[$model->id] = new OrderableDish(
                $model->id,
                $model->name,
                Money::colones($model->price)->withDiscount($percentage)->colones,
                $model->preparation_minutes,
                $model->is_active,
            );
        }

        return $dishes;
    }
}
