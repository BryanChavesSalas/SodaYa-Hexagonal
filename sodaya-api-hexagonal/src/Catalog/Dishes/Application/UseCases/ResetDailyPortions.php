<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Application\UseCases;

use Src\Catalog\Dishes\Application\Contracts\DailyPortions;

final readonly class ResetDailyPortions
{
    /** Receive the port that resets the portions of every soda. */
    public function __construct(private DailyPortions $portions) {}

    /** Start the day: every dish of every soda gets back its base portions. */
    public function execute(): void
    {
        $this->portions->resetAll();
    }
}
