<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Installer;

use Kommandhub\FlutterwaveSW\Checkout\Payment\FlutterwavePaymentHandler;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;

readonly class PaymentMethodInstaller
{
    /**
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     */
    public function __construct(
        private EntityRepository $paymentMethodRepository,
        private PluginIdProvider $pluginIdProvider
    ) {
    }

    /**
     * Installs the Flutterwave payment method if it doesn't exist.
     *
     * @param string $pluginClass The base class of the plugin
     * @param Context $context The Shopware context
     */
    public function install(string $pluginClass, Context $context): void
    {
        $paymentId = $this->getPaymentMethodId($context);

        if ($paymentId !== null) {
            $this->paymentMethodRepository->update([
                [
                    'id' => $paymentId,
                    'handlerIdentifier' => FlutterwavePaymentHandler::class,
                    'active' => true,
                ],
            ], $context);

            return;
        }

        $pluginId = $this->pluginIdProvider->getPluginIdByBaseClass($pluginClass, $context);

        $this->paymentMethodRepository->create([
            [
                'handlerIdentifier' => FlutterwavePaymentHandler::class,
                'name' => 'Pay with Flutterwave',
                'description' => 'Securely pay with card, bank transfer or mobile money via Flutterwave.',
                'pluginId' => $pluginId,
                'technicalName' => 'kommandhub_flutterwave_payment',
                'afterOrderEnabled' => true,
                'active' => true,
            ],
        ], $context);
    }

    /**
     * Activates the Flutterwave payment method.
     *
     * @param Context $context The Shopware context
     */
    public function activate(Context $context): void
    {
        $this->setPaymentMethodActive(true, $context);
    }

    /**
     * Deactivates the Flutterwave payment method.
     *
     * @param Context $context The Shopware context
     */
    public function deactivate(Context $context): void
    {
        $this->setPaymentMethodActive(false, $context);
    }

    /**
     * Sets the active state of the Flutterwave payment method.
     *
     * @param bool $active Whether the payment method should be active
     * @param Context $context The Shopware context
     */
    private function setPaymentMethodActive(bool $active, Context $context): void
    {
        $paymentId = $this->getPaymentMethodId($context);

        if ($paymentId === null) {
            return;
        }

        $this->paymentMethodRepository->update([
            [
                'id' => $paymentId,
                'active' => $active,
            ],
        ], $context);
    }

    /**
     * Retrieves the ID of the Flutterwave payment method if it exists.
     *
     * @param Context $context The Shopware context
     *
     * @return string|null The payment method ID or null if not found
     */
    private function getPaymentMethodId(Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('technicalName', 'kommandhub_flutterwave_standard')
        );

        $paymentId = $this->paymentMethodRepository
            ->searchIds($criteria, $context)
            ->firstId();

        if ($paymentId !== null) {
            return $paymentId;
        }

        $criteria = (new Criteria())->addFilter(
            new EqualsFilter('handlerIdentifier', FlutterwavePaymentHandler::class)
        );

        return $this->paymentMethodRepository
            ->searchIds($criteria, $context)
            ->firstId();
    }
}
