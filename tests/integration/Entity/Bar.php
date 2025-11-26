<?php

declare(strict_types=1);

namespace Tests\Fabiang\Doctrine\Migrations\Liquibase\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
#[ORM\Table(schema: 'testdb', name: 'Bar')]
class Bar
{
    #[ORM\Id()]
    #[ORM\Column(type: Types::STRING)]
    private ?string $id;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }
}
