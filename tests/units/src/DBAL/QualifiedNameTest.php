<?php

declare(strict_types=1);

namespace Fabiang\Doctrine\Migrations\Liquibase\DBAL;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @coversDefaultClass Fabiang\Doctrine\Migrations\Liquibase\QualifiedName
 */
final class QualifiedNameTest extends TestCase
{
    use ProphecyTrait;

    private QualifiedName $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->object = new QualifiedName('name', 'namespace');
    }

    /**
     * @covers ::fromQualifiedName
     */
    public function testFromQualifiedName(): void
    {
        $object = QualifiedName::fromQualifiedName('namespace.name');
        $this->assertSame('name', $object->getName());
        $this->assertSame('namespace', $object->getNamespaceName());
    }

    /**
     * @covers ::fromQualifiedName
     */
    public function testFromQualifiedNameWithoutNamespace(): void
    {
        $object = QualifiedName::fromQualifiedName('name');
        $this->assertSame('name', $object->getName());
        $this->assertNull($object->getNamespaceName());
    }

    /**
     * @covers ::fromAsset
     */
    public function testFromAsset(): void
    {
        $asset = $this->prophesize(ForeignKeyConstraint::class);
        $asset->getNamespaceName()->shouldBeCalled()->willReturn('namespace');
        $asset->getShortestName('namespace')->shouldBeCalled()->willReturn('name');

        $object = QualifiedName::fromAsset($asset->reveal());
        $this->assertSame('name', $object->getName());
        $this->assertSame('namespace', $object->getNamespaceName());
    }

    /**
     * @covers ::fromAsset
     */
    public function testFromAssetEmptyNamespace(): void
    {
        $asset = $this->prophesize(Column::class);
        $asset->getNamespaceName()->shouldBeCalled()->willReturn('');
        $asset->getName()->shouldBeCalled()->willReturn('name');

        $object = QualifiedName::fromAsset($asset->reveal());
        $this->assertSame('name', $object->getName());
        $this->assertSame('', $object->getNamespaceName());
    }

    /**
     * @covers ::__construct
     * @covers ::getNamespaceName
     * @covers ::getName
     */
    public function testConstructorAndGetters(): void
    {
        $this->assertSame('name', $this->object->getName());
        $this->assertSame('namespace', $this->object->getNamespaceName());
    }
}
