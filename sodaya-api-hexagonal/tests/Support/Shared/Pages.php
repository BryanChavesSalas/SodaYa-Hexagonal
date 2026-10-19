<?php

declare(strict_types=1);

namespace Tests\Support\Shared;

use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;

final class Pages
{
    /**
     * Cut a page out of a whole, already ordered listing.
     *
     * @template T
     *
     * @param  array<T>  $items
     * @return Page<T>
     */
    public static function slice(array $items, PageRequest $request): Page
    {
        return new Page(array_values(array_slice(array_values($items), $request->offset(), $request->size)), count($items), $request);
    }
}
