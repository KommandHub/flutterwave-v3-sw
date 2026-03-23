<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW;

use Kommandhub\FlutterwaveV3SW\Checkout\Payment\FlutterwaveTransactionHandler;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;

class KommandhubFlutterwaveV3SW extends Plugin
{
    public function executeComposerCommands(): bool
    {
        return true;
    }

    public function install(InstallContext $installContext): void
    {
        $this->addPaymentMethod($installContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        // Only set the payment method to inactive when uninstalling. Removing the payment method would
        // cause data consistency issues, since the payment method might have been used in several orders
        $this->setPaymentMethodIsActive(false, $uninstallContext->getContext());

        if ($uninstallContext->keepUserData()) {
            return;
        }

        // Remove or deactivate the data created by the plugin
    }

    public function activate(ActivateContext $activateContext): void
    {
        $this->setPaymentMethodIsActive(true, $activateContext->getContext());
        parent::activate($activateContext);
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        $this->setPaymentMethodIsActive(false, $deactivateContext->getContext());
        parent::deactivate($deactivateContext);
    }

    private function addPaymentMethod(Context $context): void
    {
        try {
            if ($this->container === null) {
                return;
            }
        } catch (\Throwable) { // @phpstan-ignore-line
            return;
        }

        $paymentMethodExists = $this->getPaymentMethodId();

        // Payment method exists already, no need to continue here
        if ($paymentMethodExists) {
            return;
        }

        /** @var PluginIdProvider $pluginIdProvider */
        $pluginIdProvider = $this->container->get(PluginIdProvider::class);
        $pluginId = $pluginIdProvider->getPluginIdByBaseClass(get_class($this), $context);

        $paymentData = [
            [
                // payment handler will be selected by the identifier
                'handlerIdentifier' => FlutterwaveTransactionHandler::class,
                'name' => 'Flutterwave payment - Standard',
                'description' => 'Pay securely using Flutterwave.',
                'pluginId' => $pluginId,
                'afterOrderEnabled' => true,
                'technicalName' => 'kommandhub_flutterwave_standard',
            ],
        ];

        /** @var EntityRepository<PaymentMethodCollection> $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');
        $paymentRepository->create($paymentData, $context);
    }

    private function setPaymentMethodIsActive(bool $active, Context $context): void
    {
        try {
            if ($this->container === null) {
                return;
            }
        } catch (\Throwable) { // @phpstan-ignore-line
            return;
        }

        /** @var EntityRepository<PaymentMethodCollection> $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');

        $paymentMethodId = $this->getPaymentMethodId();

        // Payment does not even exist, so nothing to (de-)activate here
        if (!$paymentMethodId) {
            return;
        }

        $paymentMethod = [
            'id' => $paymentMethodId,
            'active' => $active,
        ];

        $paymentRepository->update([$paymentMethod], $context);
    }

    private function getPaymentMethodId(): ?string
    {
        try {
            if ($this->container === null) {
                return null;
            }
        } catch (\Throwable) { // @phpstan-ignore-line
            return null;
        }

        /** @var EntityRepository $paymentMethodRepository */
        $paymentMethodRepository = $this->container->get('payment_method.repository');

        // Fetch ID for update
        $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', FlutterwaveTransactionHandler::class));

        return $paymentMethodRepository->searchIds($paymentCriteria, Context::createDefaultContext())->firstId();
    }
}
