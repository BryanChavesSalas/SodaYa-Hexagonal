<?php

declare(strict_types=1);

namespace Src\Catalog\Promotions\Application\UseCases;

use Src\Catalog\Promotions\Domain\Contracts\PromotionRepository;
use Src\Catalog\Promotions\Domain\Entities\Promotion;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class ListPromotions
{
    /** Receive the promotion repository port. */
    public function __construct(private PromotionRepository $promotions) {}

    /**
     * List one page of the happy hours of the soda ordered by day and start time.
     *
     * @return Page<Promotion>
     */
    public function execute(string $sodaId, PageRequest $page): Page
    {
        return $this->promotions->pageOf(new SodaId($sodaId), $page);
    }
}
