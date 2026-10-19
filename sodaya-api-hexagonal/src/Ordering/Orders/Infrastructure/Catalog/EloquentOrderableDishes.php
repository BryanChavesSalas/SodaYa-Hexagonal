<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Catalog;

use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Ordering\Orders\Application\Contracts\OrderableDishes;
use Src\Ordering\Orders\Application\DTOs\OrderableDish;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class EloquentOrderableDishes implements OrderableDishes
{
    /**
     * Read the requested dishes of the soda in one query; the ones of other sodas never come back.
     *
     * @param  list<string>  $dishIds
     * @return array<string, OrderableDish>
     */
    public function ofSoda(SodaId $sodaId, array $dishIds): array
    {
        $dishes = [];

        $models = DishModel::query()
            ->where('soda_id', $sodaId->value)
            ->whereIn('id', $dishIds)
            ->get();

        foreach ($models as $model) {
            $dishes[$model->id] = new OrderableDish($model->id, $model->name, $model->price, $model->is_active);
        }

        return $dishes;
    }
}
