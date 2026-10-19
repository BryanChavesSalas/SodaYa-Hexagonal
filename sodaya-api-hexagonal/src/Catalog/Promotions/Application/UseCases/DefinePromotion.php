<?php

declare(strict_types=1);

namespace Src\Catalog\Promotions\Application\UseCases;

use Src\Catalog\Promotions\Application\DTOs\DefinePromotionCommand;
use Src\Catalog\Promotions\Domain\Contracts\PromotionRepository;
use Src\Catalog\Promotions\Domain\Entities\Promotion;
use Src\Catalog\Promotions\Domain\ValueObjects\DiscountPercentage;
use Src\Catalog\Promotions\Domain\ValueObjects\TimeOfDay;
use Src\Catalog\Promotions\Domain\ValueObjects\TimeWindow;
use Src\Catalog\Promotions\Domain\ValueObjects\Weekday;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class DefinePromotion
{
    /** Receive the promotion repository port. */
    public function __construct(private PromotionRepository $promotions) {}

    /**
     * Define a happy hour for the whole soda. The overlap with another of the same day is not checked here:
     * two requests at the same instant would both pass any check in PHP, so only the store decides it.
     */
    public function execute(DefinePromotionCommand $command): Promotion
    {
        $promotion = Promotion::create(
            $this->promotions->nextId(),
            new SodaId($command->sodaId),
            new Weekday($command->weekday),
            new TimeWindow(new TimeOfDay($command->startsAt), new TimeOfDay($command->endsAt)),
            new DiscountPercentage($command->percentage),
        );

        $this->promotions->save($promotion);

        return $promotion;
    }
}
