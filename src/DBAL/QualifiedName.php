<?php

declare(strict_types=1);

namespace Fabiang\Doctrine\Migrations\Liquibase\DBAL;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;

use function count;
use function explode;

/**
 * @psalm-suppress InternalMethod The whole class `Doctrine\DBAL\Schema\AbstractAsset` is marked as internal,
 *                                which is meant to be not extended, but it triggers when methods are called
 */
class QualifiedName
{
    private ?string $namespaceName = null;
    private string $name           = '';

    public function __construct(string $name, ?string $namespaceName = null)
    {
        $this->name = $name;

        $this->namespaceName = $namespaceName;
    }

    public function getNamespaceName(): ?string
    {
        return $this->namespaceName;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public static function fromQualifiedName(string $qualifiedName): self
    {
        $tableNameArray = explode('.', $qualifiedName, 2);

        $namespaceName = null;

        if (count($tableNameArray) > 1) {
            $namespaceName = $tableNameArray[0];
            $name          = $tableNameArray[1];
        } else {
            $name = $qualifiedName;
        }

        return new self($name, $namespaceName);
    }

    public static function fromAsset(ForeignKeyConstraint|Column|Index|Table|Sequence $asset): self
    {
        $namespaceName = $asset->getNamespaceName();
        if ($namespaceName) {
            $name = $asset->getShortestName($namespaceName);
        } else {
            $name = $asset->getName();
        }

        return new QualifiedName($name, $namespaceName);
    }
}
