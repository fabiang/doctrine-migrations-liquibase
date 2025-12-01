<?php

declare(strict_types=1);

namespace Fabiang\Doctrine\Migrations\Liquibase\Entity;

use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
#[ORM\Table(schema: 'testdb', name: 'ReservedKeywords')]
class ReservedKeywords
{
    #[ORM\Id()]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private ?int $id;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: false)]
    private ?DateTime $from;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    private ?DateTime $to;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getFrom(): ?DateTime
    {
        return $this->from;
    }

    public function setFrom(?DateTime $from): void
    {
        $this->from = $from;
    }

    public function getTo(): ?DateTime
    {
        return $this->to;
    }

    public function setTo(?DateTime $to): void
    {
        $this->to = $to;
    }
}
