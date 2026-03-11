<?php

namespace App\Entity;

use App\Repository\UserRateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRateRepository::class)]
class UserRate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $rate = null;

    #[ORM\Column(length: 2555, nullable: true)]
    private ?string $user_comment = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $rate_date = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user_id = null;

    #[ORM\ManyToOne(inversedBy: 'userRates')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Clips $clip_id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRate(): ?int
    {
        return $this->rate;
    }

    public function setRate(int $rate): static
    {
        $this->rate = $rate;

        return $this;
    }

    public function getUserComment(): ?string
    {
        return $this->user_comment;
    }

    public function setUserComment(?string $user_comment): static
    {
        $this->user_comment = $user_comment;

        return $this;
    }

    public function getRateDate(): ?\DateTime
    {
        return $this->rate_date;
    }

    public function setRateDate(\DateTime $rate_date): static
    {
        $this->rate_date = $rate_date;

        return $this;
    }

    public function getUserId(): ?User
    {
        return $this->user_id;
    }

    public function setUserId(?User $user_id): static
    {
        $this->user_id = $user_id;

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
}
