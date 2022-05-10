<?php

declare(strict_types=1);

namespace Tests\Fabiang\Doctrine\Migrations\Liquibase\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Foo
 *
 * @ORM\Entity
 * @ORM\Table
 */
class Bar
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="string")
     *
     * @var string|null
     */
    private $id;

    /**
     * @return null|string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param null|string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }
}
