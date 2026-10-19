<?php

declare(strict_types=1);

namespace Src\Catalog\Promotions\Domain\Contracts;

use Src\Catalog\Promotions\Domain\Entities\Promotion;
use Src\Catalog\Promotions\Domain\Exceptions\PromotionOverlapsException;
use Src\Catalog\Promotions\Domain\ValueObjects\PromotionId;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;
use Src\Shared\Domain\ValueObjects\SodaId;

interface PromotionRepository
{
    /** Generate the identity for a new happy hour. */
    public function nextId(): PromotionId;

    /**
     * Persist a new happy hour; the store refuses one that overlaps another of the same soda and day.
     *
     * @throws PromotionOverlapsException
     */
    public function save(Promotion $promotion): void;

    /** Find a happy hour that belongs to the given soda. */
    public function find(PromotionId $id, SodaId $sodaId): ?Promotion;

    /**
     * List one page of the happy hours of a soda ordered by day and start time.
     *
     * @return Page<Promotion>
     */
    public function pageOf(SodaId $sodaId, PageRequest $page): Page;

    /** Remove a happy hour. */
    public function delete(Promotion $promotion): void;
}
