<?php

declare(strict_types=1);

namespace Tests\Unit\Sodas\Profile\Application;

use PHPUnit\Framework\TestCase;
use Src\Sodas\Profile\Application\DTOs\ChangeSodaLogoCommand;
use Src\Sodas\Profile\Application\UseCases\ChangeSodaLogo;
use Src\Sodas\Profile\Domain\Entities\Soda;
use Src\Sodas\Profile\Domain\Exceptions\SodaNotFoundException;
use Src\Sodas\Profile\Domain\ValueObjects\SodaName;
use Tests\Support\Shared\FakeImageStorage;
use Tests\Support\Sodas\InMemorySodaRepository;

final class ChangeSodaLogoTest extends TestCase
{
    private InMemorySodaRepository $sodas;

    private FakeImageStorage $images;

    private ChangeSodaLogo $changeSodaLogo;

    private Soda $soda;

    /** Wire the use case to in-memory adapters and store a soda. */
    protected function setUp(): void
    {
        $this->sodas = new InMemorySodaRepository;
        $this->images = new FakeImageStorage;
        $this->changeSodaLogo = new ChangeSodaLogo($this->sodas, $this->images);

        $this->soda = Soda::create($this->sodas->nextId(), new SodaName('Soda La Esquina'), null);
        $this->sodas->save($this->soda);
    }

    /** The logo is stored in the folder of the soda and replaces the previous one. */
    public function test_stores_the_logo_and_deletes_the_previous_one(): void
    {
        $first = $this->changeSodaLogo->execute($this->command('png'))->logoPath;
        $second = $this->changeSodaLogo->execute($this->command('webp'))->logoPath;

        $this->assertSame("sodas/{$this->soda->id->value}/logo/imagen-1.png", $first);
        $this->assertSame([$first], $this->images->deleted);
        $this->assertSame($second, $this->sodas->find($this->soda->id)?->logoPath);
    }

    /** An unknown soda is not found and nothing is stored. */
    public function test_unknown_soda_is_not_found(): void
    {
        try {
            $this->changeSodaLogo->execute(new ChangeSodaLogoCommand('0192f0c4-0000-7000-8000-000000000999', '/tmp/logo', 'png'));
            $this->fail('An unknown soda was found.');
        } catch (SodaNotFoundException) {
            $this->assertSame([], $this->images->stored);
        }
    }

    /** Build the command for the stored soda. */
    private function command(string $extension): ChangeSodaLogoCommand
    {
        return new ChangeSodaLogoCommand($this->soda->id->value, '/tmp/logo', $extension);
    }
}
