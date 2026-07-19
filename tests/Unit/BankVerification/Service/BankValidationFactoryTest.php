<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\BankVerification\Service;

use Kommandhub\FlutterwaveV3SW\BankVerification\Service\BankValidationFactory;
use Kommandhub\FlutterwaveV3SW\Setting\Service\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

#[CoversClass(BankValidationFactory::class)]
class BankValidationFactoryTest extends TestCase
{
    private function factoryWith(bool $showBvn, bool $requireBvn): BankValidationFactory
    {
        $config = $this->createMock(Config::class);
        $config->method('getBool')->willReturnCallback(
            static fn (string $key): bool => match ($key) {
                'showBvnField' => $showBvn,
                'requireBvn' => $requireBvn,
                default => false,
            }
        );

        return new BankValidationFactory($config);
    }

    private function context(): SalesChannelContext
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');

        return $context;
    }

    public function testAlwaysValidatesTheCoreBankFields(): void
    {
        $definition = $this->factoryWith(showBvn: false, requireBvn: false)->create($this->context());

        static::assertSame('flutterwave.bank.save', $definition->getName());

        foreach (['bankName', 'bankCode', 'accountNumber', 'accountName'] as $field) {
            static::assertArrayHasKey($field, $definition->getProperties());
        }
    }

    /**
     * Account number carries a digits-only Regex on top of the exact length, so
     * a 10-character non-numeric string cannot pass — unlike the Paystack rule.
     */
    public function testAccountNumberIsExactLengthAndDigitsOnly(): void
    {
        $definition = $this->factoryWith(showBvn: false, requireBvn: false)->create($this->context());

        $constraints = $definition->getProperties()['accountNumber'];
        static::assertInstanceOf(NotBlank::class, $constraints[0]);
        static::assertInstanceOf(Length::class, $constraints[1]);
        static::assertSame(10, $constraints[1]->min);
        static::assertSame(10, $constraints[1]->max);
        static::assertInstanceOf(Regex::class, $constraints[2]);
    }

    public function testBvnHiddenAddsNoBvnRule(): void
    {
        $definition = $this->factoryWith(showBvn: false, requireBvn: false)->create($this->context());

        static::assertArrayNotHasKey('bvn', $definition->getProperties());
    }

    public function testBvnShownButOptionalValidatesFormatOnly(): void
    {
        $definition = $this->factoryWith(showBvn: true, requireBvn: false)->create($this->context());

        $constraints = $definition->getProperties()['bvn'];
        static::assertCount(2, $constraints);
        static::assertInstanceOf(Length::class, $constraints[0]);
        static::assertInstanceOf(Regex::class, $constraints[1]);
    }

    public function testBvnRequiredAlsoAddsNotBlank(): void
    {
        $definition = $this->factoryWith(showBvn: true, requireBvn: true)->create($this->context());

        $constraints = $definition->getProperties()['bvn'];
        static::assertContainsOnlyInstancesOf(
            \Symfony\Component\Validator\Constraint::class,
            $constraints
        );
        $types = array_map(static fn ($c): string => $c::class, $constraints);
        static::assertContains(NotBlank::class, $types);
        static::assertContains(Length::class, $types);
    }

    public function testUpdateMirrorsCreate(): void
    {
        $definition = $this->factoryWith(showBvn: true, requireBvn: false)->update($this->context());

        static::assertSame('flutterwave.bank.save', $definition->getName());
        static::assertArrayHasKey('bvn', $definition->getProperties());
    }
}
