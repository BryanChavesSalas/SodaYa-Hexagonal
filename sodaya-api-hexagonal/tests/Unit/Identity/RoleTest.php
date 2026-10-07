<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Identity\Domain\Enums\Ability;
use Src\Identity\Domain\Enums\Role;

final class RoleTest extends TestCase
{
    /** @return iterable<string, array{Role, list<Ability>}> */
    public static function abilitiesByRole(): iterable
    {
        yield 'cliente recibe pedidos' => [Role::Customer, [Ability::PlaceOrders]];
        yield 'cocina opera la cocina' => [Role::Kitchen, [Ability::OperateKitchen]];
        yield 'dueño opera la cocina y administra' => [Role::Owner, [Ability::OperateKitchen, Ability::Administer]];
    }

    /** @param list<Ability> $expected */
    #[Test]
    #[DataProvider('abilitiesByRole')]
    public function each_role_has_exactly_its_abilities(Role $role, array $expected): void
    {
        $this->assertSame($expected, $role->abilities());
    }

    #[Test]
    public function token_abilities_are_the_plain_ability_values(): void
    {
        $this->assertSame(['cocina:operar', 'negocio:administrar'], Role::Owner->tokenAbilities());
    }

    #[Test]
    public function no_role_receives_the_wildcard_ability(): void
    {
        foreach (Role::cases() as $role) {
            $this->assertNotContains('*', $role->tokenAbilities());
        }
    }

    #[Test]
    public function only_the_owner_can_administer(): void
    {
        $this->assertNotContains(Ability::Administer, Role::Customer->abilities());
        $this->assertNotContains(Ability::Administer, Role::Kitchen->abilities());
        $this->assertContains(Ability::Administer, Role::Owner->abilities());
    }

    #[Test]
    public function only_customers_are_outside_a_soda(): void
    {
        $this->assertFalse(Role::Customer->belongsToSoda());
        $this->assertTrue(Role::Kitchen->belongsToSoda());
        $this->assertTrue(Role::Owner->belongsToSoda());
    }
}
