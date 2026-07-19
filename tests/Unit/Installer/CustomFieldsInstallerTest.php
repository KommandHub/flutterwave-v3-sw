<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Installer;

use Kommandhub\FlutterwaveV3SW\Installer\CustomFieldsInstaller;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

#[CoversClass(CustomFieldsInstaller::class)]
class CustomFieldsInstallerTest extends TestCase
{
    private EntityRepository&MockObject $customFieldSetRepository;
    private EntityRepository&MockObject $customFieldSetRelationRepository;
    private CustomFieldsInstaller $installer;
    private Context $context;

    protected function setUp(): void
    {
        $this->customFieldSetRepository = $this->createMock(EntityRepository::class);
        $this->customFieldSetRelationRepository = $this->createMock(EntityRepository::class);
        $this->installer = new CustomFieldsInstaller(
            $this->customFieldSetRepository,
            $this->customFieldSetRelationRepository
        );
        $this->context = Context::createDefaultContext();
    }

    private function idResult(string ...$ids): IdSearchResult
    {
        $result = $this->createMock(IdSearchResult::class);
        $result->method('getIds')->willReturn($ids);
        $result->method('getTotal')->willReturn(count($ids));

        return $result;
    }

    public function testInstallSkippedWhenAlreadyExists(): void
    {
        $this->customFieldSetRepository->method('searchIds')->willReturn($this->idResult('existing-id'));
        $this->customFieldSetRepository->expects(static::never())->method('upsert');

        $this->installer->install($this->context);
    }

    public function testInstallUpsertsWhenMissing(): void
    {
        $this->customFieldSetRepository->method('searchIds')->willReturn($this->idResult());
        $this->customFieldSetRepository->expects(static::once())->method('upsert');

        $this->installer->install($this->context);
    }

    public function testAddRelationsCreatesCustomerRelation(): void
    {
        $this->customFieldSetRepository->method('searchIds')->willReturn($this->idResult('fieldset-id'));
        $this->customFieldSetRelationRepository->method('searchIds')->willReturn($this->idResult());

        $this->customFieldSetRelationRepository->expects(static::once())
            ->method('upsert')
            ->with([['customFieldSetId' => 'fieldset-id', 'entityName' => 'customer']], $this->context);

        $this->installer->addRelations($this->context);
    }

    public function testAddRelationsSkippedWhenRelationExists(): void
    {
        $this->customFieldSetRepository->method('searchIds')->willReturn($this->idResult('fieldset-id'));
        $this->customFieldSetRelationRepository->method('searchIds')->willReturn($this->idResult('relation-id'));

        $this->customFieldSetRelationRepository->expects(static::never())->method('upsert');

        $this->installer->addRelations($this->context);
    }

    public function testUninstallDeletesFieldset(): void
    {
        $this->customFieldSetRepository->method('searchIds')->willReturn($this->idResult('fieldset-id'));

        $this->customFieldSetRepository->expects(static::once())
            ->method('delete')
            ->with([['id' => 'fieldset-id']], $this->context);

        $this->installer->uninstall($this->context);
    }

    public function testUninstallNoopWhenNothingToDelete(): void
    {
        $this->customFieldSetRepository->method('searchIds')->willReturn($this->idResult());
        $this->customFieldSetRepository->expects(static::never())->method('delete');

        $this->installer->uninstall($this->context);
    }
}
