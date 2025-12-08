<?php

declare(strict_types=1);

namespace Fabiang\Doctrine\Migrations\Liquibase\DBAL;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

#[CoversClass(QualifiedName::class)]
final class QualifiedNameTest extends TestCase
{
    use ProphecyTrait;

    private QualifiedName $object;

    protected function setUp(): void
    {
        $this->object = new QualifiedName('name', 'namespace');
    }

    public function testFromQualifiedName(): void
    {
        $object = QualifiedName::fromQualifiedName('namespace.name');
        $this->assertSame('name', $object->getName());
        $this->assertSame('namespace', $object->getNamespaceName());
    }

    public function testFromQualifiedNameWithoutNamespace(): void
    {
        $object = QualifiedName::fromQualifiedName('name');
        $this->assertSame('name', $object->getName());
        $this->assertNull($object->getNamespaceName());
    }

    public function testFromAsset(): void
    {
        $asset = $this->prophesize(ForeignKeyConstraint::class);
        $asset->getNamespaceName()->shouldBeCalled()->willReturn('namespace');
        $asset->getShortestName('namespace')->shouldBeCalled()->willReturn('name');

        $object = QualifiedName::fromAsset($asset->reveal());
        $this->assertSame('name', $object->getName());
        $this->assertSame('namespace', $object->getNamespaceName());
    }

    public function testFromAssetEmptyNamespace(): void
    {
        $asset = $this->prophesize(Column::class);
        $asset->getNamespaceName()->shouldBeCalled()->willReturn('');
        $asset->getName()->shouldBeCalled()->willReturn('name');

        $object = QualifiedName::fromAsset($asset->reveal());
        $this->assertSame('name', $object->getName());
        $this->assertSame('', $object->getNamespaceName());
    }

    public function testConstructorAndGetters(): void
    {
        $this->assertSame('name', $this->object->getName());
        $this->assertSame('namespace', $this->object->getNamespaceName());
    }
}
