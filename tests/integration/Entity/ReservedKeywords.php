<?php

declare(strict_types=1);

namespace Tests\Fabiang\Doctrine\Migrations\Liquibase\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * Foo
 *
 * @ORM\Entity
 * @ORM\Table
 */
class ReservedKeywords
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="IDENTITY")
     *
     * @var int|null
     */
    private $id;

    /**
     * @ORM\Column(type="date")
     *
     * @var DateTime|null
     */
    private $from;

    /**
     * @ORM\Column(type="datetime")
     *
     * @var DateTime|null
     */
    private $to;

    /**
     * @return int|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int|null $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return DateTime|null
     */
    public function getFrom()
    {
        return $this->from;
    }

    /**
     * @param DateTime|null $from
     */
    public function setFrom($from)
    {
        $this->from = $from;
    }

    /**
     * @return DateTime|null
     */
    public function getTo()
    {
        return $this->to;
    }

    /**
     * @param DateTime|null $to
     */
    public function setTo($to)
    {
        $this->to = $to;
    }
}
