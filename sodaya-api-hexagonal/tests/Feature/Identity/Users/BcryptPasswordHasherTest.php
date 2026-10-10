<?php

declare(strict_types=1);

namespace Tests\Feature\Identity\Users;

use Src\Identity\Users\Infrastructure\Security\BcryptPasswordHasher;
use Tests\TestCase;

final class BcryptPasswordHasherTest extends TestCase
{
    /** The hash is bcrypt with the rounds of the environment and a fresh salt. */
    public function test_hash_is_adaptive_and_salted(): void
    {
        $hasher = new BcryptPasswordHasher;

        $hash = $hasher->hash('secreto123');

        $this->assertSame('bcrypt', password_get_info($hash)['algoName']);
        $this->assertSame((int) config('hashing.bcrypt.rounds'), password_get_info($hash)['options']['cost']);
        $this->assertNotSame($hash, $hasher->hash('secreto123'));
    }

    /** Only the original password matches its hash. */
    public function test_check_accepts_only_the_original_password(): void
    {
        $hasher = new BcryptPasswordHasher;
        $hash = $hasher->hash('secreto123');

        $this->assertTrue($hasher->check('secreto123', $hash));
        $this->assertFalse($hasher->check('Secreto123', $hash));
    }

    /** Without a stored hash the check spends the same work and fails. */
    public function test_check_without_a_hash_fails(): void
    {
        $this->assertFalse(new BcryptPasswordHasher()->check('secreto123', null));
    }
}
