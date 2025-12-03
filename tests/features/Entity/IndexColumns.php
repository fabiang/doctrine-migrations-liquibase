<?php

declare(strict_types=1);

namespace Fabiang\Doctrine\Migrations\Liquibase\Entity;

use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(
 *   name="indexcolumns",
 *   uniqueConstraints={@ORM\UniqueConstraint(name="user_unique",columns={"commentaire"})},
 *   indexes={
 *     @ORM\Index(name="user_idx", columns={"date"})
 *     @ORM\Index(name="user_idx", columns={"libelle"})
 *   }
 * )
 */
#[ORM\Entity()]
#[ORM\Table(name: 'indexcolumns')]
#[ORM\Index(columns: ['date'])]
#[ORM\Index(fields: ['libelle'])]
#[ORM\UniqueConstraint(fields: ['commentaire'])]
class IndexColumns
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    #[ORM\Id()]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private ?int $id;

    /** @ORM\Column(type="date", nullable=true) */
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTime $date;

    /** @ORM\Column(type="date", nullable=true) */
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $libelle;

    /** @ORM\Column(type="date", nullable=true, length=500) */
    #[ORM\Column(type: Types::STRING, nullable: true, length: 500)]
    private ?string $commentaire;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getDate(): ?DateTime
    {
        return $this->date;
    }

    public function setDate(?DateTime $date): void
    {
        $this->date = $date;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): void
    {
        $this->libelle = $libelle;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): void
    {
        $this->commentaire = $commentaire;
    }
}
