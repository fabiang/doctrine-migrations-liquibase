<?php

declare(strict_types=1);

namespace Fabiang\Doctrine\Migrations\Liquibase\Output;

class LiquibaseOutputOptions
{
    private bool $usePlatformTypes  = false;
    private bool $changeSetUniqueId = true;
    private string $changeSetAuthor = 'doctrine-migrations-liquibase';

    public function isUsePlatformTypes(): bool
    {
        return $this->usePlatformTypes;
    }

    /**
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function setUsePlatformTypes(bool $usePlatformTypes): self
    {
        $this->usePlatformTypes = $usePlatformTypes;
        return $this;
    }

    public function isChangeSetUniqueId(): bool
    {
        return $this->changeSetUniqueId;
    }

    /**
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function setChangeSetUniqueId(bool $changeSetUniqueId): self
    {
        $this->changeSetUniqueId = $changeSetUniqueId;
        return $this;
    }

    public function getChangeSetAuthor(): string
    {
        return $this->changeSetAuthor;
    }

    /**
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function setChangeSetAuthor(string $changeSetAuthor): self
    {
        $this->changeSetAuthor = $changeSetAuthor;
        return $this;
    }
}
