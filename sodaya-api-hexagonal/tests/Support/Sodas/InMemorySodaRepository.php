<?php

declare(strict_types=1);

namespace Tests\Support\Sodas;

use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Profile\Domain\Contracts\SodaRepository;
use Src\Sodas\Profile\Domain\Entities\Soda;

final class InMemorySodaRepository implements SodaRepository
{
    /** @var array<string, Soda> */
    private array $sodas = [];

    private int $sequence = 0;

    /** Generate a predictable identity. */
    public function nextId(): SodaId
    {
        return new SodaId(sprintf('0192f0c4-0000-7000-8000-%012d', ++$this->sequence));
    }

    /** Store the soda. */
    public function save(Soda $soda): void
    {
        $this->sodas[$soda->id->value] = $soda;
    }

    /**
     * List the stored sodas.
     *
     * @return list<Soda>
     */
    public function all(): array
    {
        return array_values($this->sodas);
    }
}
