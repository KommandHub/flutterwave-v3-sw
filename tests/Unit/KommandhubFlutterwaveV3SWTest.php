<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit;

use Kommandhub\FlutterwaveV3SW\Checkout\Payment\FlutterwaveTransactionHandler;
use Kommandhub\FlutterwaveV3SW\KommandhubFlutterwaveV3SW;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;

class KommandhubFlutterwaveV3SWTest extends TestCase
{
    private KommandhubFlutterwaveV3SW $plugin;
    private ContainerInterface $container;
    private EntityRepository $paymentRepository;
    private Context $context;

    protected function setUp(): void
    {
        $this->plugin = new KommandhubFlutterwaveV3SW(true, '');
        $this->container = $this->createMock(ContainerInterface::class);
        $this->plugin->setContainer($this->container);
        $this->paymentRepository = $this->createMock(EntityRepository::class);
        $this->context = Context::createDefaultContext();
    }

    public function testExecuteComposerCommands(): void
    {
        $this->assertTrue($this->plugin->executeComposerCommands());
    }

    public function testInstallAddsPaymentMethod(): void
    {
        $installContext = $this->createMock(InstallContext::class);
        $installContext->method('getContext')->willReturn($this->context);

        $this->container->method('get')
            ->willReturnMap([
                ['payment_method.repository', $this->paymentRepository],
                [PluginIdProvider::class, $this->createMock(PluginIdProvider::class)],
            ]);

        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn(null);
        $this->paymentRepository->method('searchIds')->willReturn($idSearchResult);

        $this->paymentRepository->expects($this->once())->method('create');

        $this->plugin->install($installContext);
    }

    public function testActivate(): void
    {
        $activateContext = $this->createMock(ActivateContext::class);
        $activateContext->method('getContext')->willReturn($this->context);

        $this->container->method('get')->with('payment_method.repository')->willReturn($this->paymentRepository);

        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn('payment-id');
        $this->paymentRepository->method('searchIds')->willReturn($idSearchResult);

        $this->paymentRepository->expects($this->once())
            ->method('update')
            ->with([['id' => 'payment-id', 'active' => true]], $this->context);

        $this->plugin->activate($activateContext);
    }

    public function testDeactivate(): void
    {
        $deactivateContext = $this->createMock(DeactivateContext::class);
        $deactivateContext->method('getContext')->willReturn($this->context);

        $this->container->method('get')->with('payment_method.repository')->willReturn($this->paymentRepository);

        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn('payment-id');
        $this->paymentRepository->method('searchIds')->willReturn($idSearchResult);

        $this->paymentRepository->expects($this->once())
            ->method('update')
            ->with([['id' => 'payment-id', 'active' => false]], $this->context);

        $this->plugin->deactivate($deactivateContext);
    }

    public function testUninstallDeactivatesPaymentMethod(): void
    {
        $uninstallContext = $this->createMock(UninstallContext::class);
        $uninstallContext->method('getContext')->willReturn($this->context);
        $uninstallContext->method('keepUserData')->willReturn(true);

        $this->container->method('get')->with('payment_method.repository')->willReturn($this->paymentRepository);

        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn('payment-id');
        $this->paymentRepository->method('searchIds')->willReturn($idSearchResult);

        $this->paymentRepository->expects($this->once())
            ->method('update')
            ->with([['id' => 'payment-id', 'active' => false]], $this->context);

        $this->plugin->uninstall($uninstallContext);
    }

    public function testAddPaymentMethodSkippedIfNoContainer(): void
    {
        $plugin = new KommandhubFlutterwaveV3SW(true, '');
        $installContext = $this->createMock(InstallContext::class);
        $installContext->method('getContext')->willReturn($this->context);
        
        // No container set, should just return
        $plugin->install($installContext);
        $this->assertTrue(true); // Just to confirm no errors
    }

    public function testAddPaymentMethodSkippedIfAlreadyExists(): void
    {
        $installContext = $this->createMock(InstallContext::class);
        $installContext->method('getContext')->willReturn($this->context);

        $this->container->method('get')->with('payment_method.repository')->willReturn($this->paymentRepository);

        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn('existing-id');
        $this->paymentRepository->method('searchIds')->willReturn($idSearchResult);

        $this->paymentRepository->expects($this->never())->method('create');

        $this->plugin->install($installContext);
    }

    public function testSetPaymentMethodIsActiveSkippedIfNoContainer(): void
    {
        $plugin = new KommandhubFlutterwaveV3SW(true, '');
        $activateContext = $this->createMock(ActivateContext::class);
        $activateContext->method('getContext')->willReturn($this->context);
        
        $plugin->activate($activateContext);
        $this->assertTrue(true);
    }

    public function testSetPaymentMethodIsActiveSkippedIfNotFound(): void
    {
        $activateContext = $this->createMock(ActivateContext::class);
        $activateContext->method('getContext')->willReturn($this->context);

        $this->container->method('get')->with('payment_method.repository')->willReturn($this->paymentRepository);

        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn(null);
        $this->paymentRepository->method('searchIds')->willReturn($idSearchResult);

        $this->paymentRepository->expects($this->never())->method('update');

        $this->plugin->activate($activateContext);
    }

    public function testUninstallSkippedIfNoContainer(): void
    {
        $plugin = new KommandhubFlutterwaveV3SW(true, '');
        $uninstallContext = $this->createMock(UninstallContext::class);
        $uninstallContext->method('getContext')->willReturn($this->context);
        
        $plugin->uninstall($uninstallContext);
        $this->assertTrue(true);
    }
}
