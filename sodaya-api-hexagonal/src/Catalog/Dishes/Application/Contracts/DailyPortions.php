<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Application\Contracts;

interface DailyPortions
{
    /** Put the available portions of every dish of every soda back to its base portions. */
    public function resetAll(): void;
}
