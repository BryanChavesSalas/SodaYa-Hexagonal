<?php

declare(strict_types=1);

namespace Src\Catalog\Promotions\Infrastructure\Persistence\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Catalog\Promotions\Domain\Contracts\PromotionRepository;
use Src\Catalog\Promotions\Domain\Entities\Promotion;
use Src\Catalog\Promotions\Domain\Exceptions\PromotionOverlapsException;
use Src\Catalog\Promotions\Domain\ValueObjects\DiscountPercentage;
use Src\Catalog\Promotions\Domain\ValueObjects\PromotionId;
use Src\Catalog\Promotions\Domain\ValueObjects\TimeOfDay;
use Src\Catalog\Promotions\Domain\ValueObjects\TimeWindow;
use Src\Catalog\Promotions\Domain\ValueObjects\Weekday;
use Src\Catalog\Promotions\Infrastructure\Persistence\Models\PromotionModel;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Shared\Infrastructure\Persistence\EloquentPages;

final readonly class EloquentPromotionRepository implements PromotionRepository
{
    private const string EXCLUSION_VIOLATION = '23P01';

    /** Generate a time-ordered UUID for a new happy hour. */
    public function nextId(): PromotionId
    {
        return new PromotionId((string) Str::uuid7());
    }

    /** Insert the happy hour inside a savepoint and translate the exclusion violation of the table into the domain error. */
    public function save(Promotion $promotion): void
    {
        try {
            DB::transaction(fn () => PromotionModel::query()->updateOrCreate(
                ['id' => $promotion->id->value],
                [
                    'soda_id' => $promotion->sodaId->value,
                    'weekday' => $promotion->weekday->value,
                    'starts_at' => $promotion->window->startsAt->value,
                    'ends_at' => $promotion->window->endsAt->value,
                    'percentage' => $promotion->percentage->value,
                ],
            ));
        } catch (QueryException $exception) {
            throw $exception->getCode() === self::EXCLUSION_VIOLATION
                ? PromotionOverlapsException::create()
                : $exception;
        }
    }

    /** Find the happy hour scoped to its soda. */
    public function find(PromotionId $id, SodaId $sodaId): ?Promotion
    {
        $model = PromotionModel::query()
            ->where('soda_id', $sodaId->value)
            ->find($id->value);

        return $model === null ? null : $this->toDomain($model);
    }

    /**
     * List one page of the happy hours of the soda ordered by day and start time.
     *
     * @return Page<Promotion>
     */
    public function pageOf(SodaId $sodaId, PageRequest $page): Page
    {
        return EloquentPages::of($this->orderedPromotionsOf($sodaId), $page, $this->toDomain(...));
    }

    /** Delete the row through its model, so that the observers of the model see it go. */
    public function delete(Promotion $promotion): void
    {
        PromotionModel::query()
            ->where('soda_id', $promotion->sodaId->value)
            ->whereKey($promotion->id->value)
            ->first()
            ?->delete();
    }

    /**
     * Scope the query to a soda in week order.
     *
     * @return Builder<PromotionModel>
     */
    private function orderedPromotionsOf(SodaId $sodaId): Builder
    {
        return PromotionModel::query()
            ->where('soda_id', $sodaId->value)
            ->orderBy('weekday')
            ->orderBy('starts_at');
    }

    /** Rebuild the happy hour from its stored state, dropping the seconds PostgreSQL returns. */
    private function toDomain(PromotionModel $model): Promotion
    {
        return Promotion::reconstitute(
            new PromotionId($model->id),
            new SodaId($model->soda_id),
            new Weekday($model->weekday),
            new TimeWindow(new TimeOfDay(substr($model->starts_at, 0, 5)), new TimeOfDay(substr($model->ends_at, 0, 5))),
            new DiscountPercentage($model->percentage),
        );
    }
}
