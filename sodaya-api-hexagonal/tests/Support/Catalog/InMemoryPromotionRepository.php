<?php

declare(strict_types=1);

namespace Tests\Support\Catalog;

use Src\Catalog\Promotions\Domain\Contracts\PromotionRepository;
use Src\Catalog\Promotions\Domain\Entities\Promotion;
use Src\Catalog\Promotions\Domain\Exceptions\PromotionOverlapsException;
use Src\Catalog\Promotions\Domain\ValueObjects\PromotionId;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;
use Src\Shared\Domain\ValueObjects\SodaId;
use Tests\Support\Shared\Pages;

final class InMemoryPromotionRepository implements PromotionRepository
{
    /** @var array<string, Promotion> */
    private array $promotions = [];

    private int $sequence = 0;

    /** Generate a predictable identity. */
    public function nextId(): PromotionId
    {
        return new PromotionId(sprintf('0192f0c4-0000-7000-8000-%012d', ++$this->sequence));
    }

    /** Store the happy hour, refusing an overlap as the exclusion constraint of the table would. */
    public function save(Promotion $promotion): void
    {
        foreach ($this->promotions as $stored) {
            if (! $stored->id->equals($promotion->id) && $stored->overlaps($promotion)) {
                throw PromotionOverlapsException::create();
            }
        }

        $this->promotions[$promotion->id->value] = $promotion;
    }

    /** Find the happy hour scoped to its soda. */
    public function find(PromotionId $id, SodaId $sodaId): ?Promotion
    {
        $promotion = $this->promotions[$id->value] ?? null;

        return $promotion?->sodaId->equals($sodaId) === true ? $promotion : null;
    }

    /**
     * List one page of the happy hours of the soda ordered by day and start time.
     *
     * @return Page<Promotion>
     */
    public function pageOf(SodaId $sodaId, PageRequest $page): Page
    {
        $promotions = array_values(array_filter(
            $this->promotions,
            fn (Promotion $promotion): bool => $promotion->sodaId->equals($sodaId),
        ));

        usort($promotions, fn (Promotion $a, Promotion $b): int => [$a->weekday->value, $a->window->startsAt->value]
            <=> [$b->weekday->value, $b->window->startsAt->value]);

        return Pages::slice($promotions, $page);
    }

    /** Forget the happy hour. */
    public function delete(Promotion $promotion): void
    {
        unset($this->promotions[$promotion->id->value]);
    }
}
