<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Users\Domain;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Identity\Users\Domain\ValueObjects\Email;
use Src\Identity\Users\Domain\ValueObjects\UserName;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final class ValueObjectsTest extends TestCase
{
    /** The email is trimmed and lower-cased, so one address has one spelling. */
    public function test_email_is_normalised(): void
    {
        $this->assertSame('ana.mora@sodaya.test', new Email('  Ana.Mora@SodaYa.TEST ')->value);
    }

    /** The longest address a mail server delivers fits in the column. */
    public function test_email_accepts_the_longest_deliverable_address(): void
    {
        $address = str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 61);

        $this->assertSame(254, mb_strlen(new Email($address)->value));
    }

    /** A malformed or oversized email is rejected with a translatable error. */
    #[DataProvider('invalidEmails')]
    public function test_rejects_invalid_emails(string $email): void
    {
        try {
            new Email($email);
            $this->fail('An invalid email was accepted.');
        } catch (InvalidValueException $exception) {
            $this->assertSame('identity.email_invalid', $exception->translationKey());
        }
    }

    /**
     * Emails that break the invariant of the value object.
     *
     * @return array<string, array{string}>
     */
    public static function invalidEmails(): array
    {
        return [
            'blank' => ['   '],
            'without at sign' => ['ana.sodaya.test'],
            'without domain' => ['ana@'],
            'too long' => [str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 63)],
        ];
    }

    /** The name is trimmed and rejects blank or oversized values. */
    public function test_name_is_trimmed_and_limited(): void
    {
        $this->assertSame('Ana Mora', new UserName('  Ana Mora ')->value);
        $this->assertSame(120, mb_strlen(new UserName(str_repeat('ñ', 120))->value));

        foreach (['   ', str_repeat('a', 121)] as $name) {
            try {
                new UserName($name);
                $this->fail('An invalid name was accepted.');
            } catch (InvalidValueException $exception) {
                $this->assertSame('identity.name_invalid', $exception->translationKey());
            }
        }
    }
}
