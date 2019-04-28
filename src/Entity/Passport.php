<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PassportRepository")
 */
class Passport
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="string", length=4)
     */
    private $series;

    /**
     * @ORM\Id()
     * @ORM\Column(type="string", length=6)
     */
    private $number;

    public function getSeries(): string
    {
        return $this->series;
    }

    public function setSeries(string $series): self
    {
        $this->series = $series;

        return $this;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): self
    {
        $this->number = $number;
        return $this;
    }
}
