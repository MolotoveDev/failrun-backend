<?php

namespace App\Entity;

use App\Repository\MarkRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MarkRepository::class)]
class Mark
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?float $mark_rate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $mark_date = null;

    #[ORM\ManyToOne(inversedBy: 'marks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Clips $clip_id = null;

    #[ORM\ManyToOne(inversedBy: 'marks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?MarkType $id_mark_type = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMarkRate(): ?float
    {
        return $this->mark_rate;
    }

    public function setMarkRate(float $mark_rate): static
    {
        $this->mark_rate = $mark_rate;

        return $this;
    }

    public function getMarkDate(): ?\DateTime
    {
        return $this->mark_date;
    }

    public function setMarkDate(\DateTime $mark_date): static
    {
        $this->mark_date = $mark_date;

        return $this;
    }

    public function getClipId(): ?Clips
    {
        return $this->clip_id;
    }

    public function setClipId(?Clips $clip_id): static
    {
        $this->clip_id = $clip_id;

        return $this;
    }

    public function getIdMarkType(): ?MarkType
    {
        return $this->id_mark_type;
    }

    public function setIdMarkType(?MarkType $id_mark_type): static
    {
        $this->id_mark_type = $id_mark_type;

        return $this;
    }
}
