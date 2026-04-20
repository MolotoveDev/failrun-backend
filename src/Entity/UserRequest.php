<?php

namespace App\Entity;

use App\Repository\UserRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRequestRepository::class)]
class UserRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title_request = null;

    #[ORM\Column(length: 2555)]
    private ?string $description_request = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $date_request = null;

    #[ORM\Column]
    private ?int $status_request = null;

    #[ORM\ManyToOne(inversedBy: 'userRequests')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user_id = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitleRequest(): ?string
    {
        return $this->title_request;
    }

    public function setTitleRequest(string $title_request): static
    {
        $this->title_request = $title_request;

        return $this;
    }

    public function getDescriptionRequest(): ?string
    {
        return $this->description_request;
    }

    public function setDescriptionRequest(string $description_request): static
    {
        $this->description_request = $description_request;

        return $this;
    }

    public function getDateRequest(): ?\DateTime
    {
        return $this->date_request;
    }

    public function setDateRequest(\DateTime $date_request): static
    {
        $this->date_request = $date_request;

        return $this;
    }

    public function getStatusRequest(): ?int
    {
        return $this->status_request;
    }

    public function setStatusRequest(int $status_request): static
    {
        $this->status_request = $status_request;

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

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }
}
