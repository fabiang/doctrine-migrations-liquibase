<?php

declare(strict_types=1);

namespace Fabiang\Doctrine\Migrations\Liquibase\Output;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

#[CoversClass(LiquibaseOutputOptions::class)]
final class LiquibaseOutputOptionsTest extends TestCase
{
    use ProphecyTrait;

    private LiquibaseOutputOptions $object;

    protected function setUp(): void
    {
        $this->object = new LiquibaseOutputOptions();
    }

    public function testSetterAndGetter(): void
    {
        $this->assertFalse($this->object->isUsePlatformTypes());
        $this->object->setUsePlatformTypes(true);
        $this->assertTrue($this->object->isUsePlatformTypes());

        $this->assertTrue($this->object->isChangeSetUniqueId());
        $this->object->setChangeSetUniqueId(false);
        $this->assertFalse($this->object->isChangeSetUniqueId());

        $this->assertSame('doctrine-migrations-liquibase', $this->object->getChangeSetAuthor());
        $this->object->setChangeSetAuthor('user');
        $this->assertSame('user', $this->object->getChangeSetAuthor());
    }
}
