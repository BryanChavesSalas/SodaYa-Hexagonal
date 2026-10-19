<?php

declare(strict_types=1);

namespace Src\Sodas\Profile\Application\UseCases;

use Src\Shared\Domain\Contracts\ImageStorage;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Profile\Application\DTOs\ChangeSodaLogoCommand;
use Src\Sodas\Profile\Domain\Contracts\SodaRepository;
use Src\Sodas\Profile\Domain\Entities\Soda;
use Src\Sodas\Profile\Domain\Exceptions\SodaNotFoundException;
use Throwable;

final readonly class ChangeSodaLogo
{
    /** Receive the soda repository and the image storage ports. */
    public function __construct(
        private SodaRepository $sodas,
        private ImageStorage $images,
    ) {}

    /** Store the new logo of the soda and delete the one it replaces. */
    public function execute(ChangeSodaLogoCommand $command): Soda
    {
        $soda = $this->sodas->find(new SodaId($command->sodaId)) ?? throw SodaNotFoundException::create();

        $path = $this->images->store("sodas/{$command->sodaId}/logo", $command->imagePath, $command->extension);

        try {
            $changed = $soda->withLogo($path);
            $this->sodas->save($changed);
        } catch (Throwable $exception) {
            $this->images->delete($path);

            throw $exception;
        }

        if ($soda->logoPath !== null) {
            $this->images->delete($soda->logoPath);
        }

        return $changed;
    }
}
