<?php

declare(strict_types=1);

namespace Src\Catalog\Promotions\Application\UseCases;

use Src\Catalog\Promotions\Domain\Contracts\PromotionRepository;
use Src\Catalog\Promotions\Domain\Exceptions\PromotionNotFoundException;
use Src\Catalog\Promotions\Domain\ValueObjects\PromotionId;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class DeletePromotion
{
    /** Receive the promotion repository port. */
    public function __construct(private PromotionRepository $promotions) {}

    /** Remove a happy hour of the soda. */
    public function execute(string $promotionId, string $sodaId): void
    {
        $promotion = $this->promotions->find(new PromotionId($promotionId), new SodaId($sodaId))
            ?? throw PromotionNotFoundException::create();

        $this->promotions->delete($promotion);
    }
}
