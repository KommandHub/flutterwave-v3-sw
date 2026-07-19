<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\BankVerification\Service;

use Kommandhub\FlutterwaveSW\Setting\Service\Config;
use Kommandhub\FlutterwaveSW\Util\FlutterwaveConstants;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Builds the server-side validation for the customer bank-profile form.
 *
 * BVN rules are only added when the merchant has enabled the field, and made
 * mandatory only when configured — mirroring the storefront's conditional
 * rendering so the two never disagree.
 */
class BankValidationFactory implements DataValidationFactoryInterface
{
    public function __construct(private readonly Config $config)
    {
    }

    public function create(SalesChannelContext $context): DataValidationDefinition
    {
        return $this->buildCommonValidation($context);
    }

    public function update(SalesChannelContext $context): DataValidationDefinition
    {
        return $this->buildCommonValidation($context);
    }

    private function buildCommonValidation(SalesChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('flutterwave.bank.save');

        $definition
            ->add('bankName', new NotBlank())
            ->add('bankCode', new NotBlank())
            ->add(
                'accountNumber',
                new NotBlank(),
                new Length(exactly: FlutterwaveConstants::ACCOUNT_NUMBER_LENGTH),
                new Regex(pattern: '/^\d+$/', message: 'The account number must contain only digits.')
            )
            ->add('accountName', new NotBlank());

        if ($this->config->getBool('showBvnField', $context->getSalesChannelId())) {
            $definition->add(
                'bvn',
                new Length(exactly: FlutterwaveConstants::BVN_LENGTH),
                new Regex(pattern: '/^\d+$/', message: 'The BVN must contain only digits.')
            );

            if ($this->config->getBool('requireBvn', $context->getSalesChannelId())) {
                $definition->add('bvn', new NotBlank());
            }
        }

        return $definition;
    }
}
