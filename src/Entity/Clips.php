<?php

namespace App\Entity;

use App\Repository\ClipsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClipsRepository::class)]
class Clips
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $clip_title = null;

    #[ORM\Column(length: 2555)]
    private ?string $clip_link = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $clip_description = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $clip_date = null;

    #[ORM\Column]
    private ?int $clip_status = null;

    #[ORM\ManyToOne(inversedBy: 'clips')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user_id = null;

    #[ORM\ManyToOne(inversedBy: 'clips')]
    private ?Games $game_id = null;

    /**
     * @var Collection<int, UserRate>
     */
    #[ORM\OneToMany(targetEntity: UserRate::class, mappedBy: 'clip_id')]
    private Collection $userRates;

    /**
     * @var Collection<int, Mark>
     */
    #[ORM\OneToMany(targetEntity: Mark::class, mappedBy: 'clip_id')]
    private Collection $marks;

    public function __construct()
    {
        $this->userRates = new ArrayCollection();
        $this->marks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClipTitle(): ?string
    {
        return $this->clip_title;
    }

    public function setClipTitle(string $clip_title): static
    {
        $this->clip_title = $clip_title;

        return $this;
    }

    public function getClipLink(): ?string
    {
        return $this->clip_link;
    }

    public function setClipLink(string $clip_link): static
    {
        $this->clip_link = $clip_link;

        return $this;
    }

    public function getClipDescription(): ?string
    {
        return $this->clip_description;
    }

    public function setClipDescription(?string $clip_description): static
    {
        $this->clip_description = $clip_description;

        return $this;
    }

    public function getClipDate(): ?\DateTime
    {
        return $this->clip_date;
    }

    public function setClipDate(\DateTime $clip_date): static
    {
        $this->clip_date = $clip_date;

        return $this;
    }

    public function getClipStatus(): ?int
    {
        return $this->clip_status;
    }

    public function setClipStatus(int $clip_status): static
    {
        $this->clip_status = $clip_status;

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

    public function getGameId(): ?Games
    {
        return $this->game_id;
    }

    public function setGameId(?Games $game_id): static
    {
        $this->game_id = $game_id;

        return $this;
    }

    /**
     * @return Collection<int, UserRate>
     */
    public function getUserRates(): Collection
    {
        return $this->userRates;
    }

    public function addUserRate(UserRate $userRate): static
    {
        if (!$this->userRates->contains($userRate)) {
            $this->userRates->add($userRate);
            $userRate->setClipId($this);
        }

        return $this;
    }

    public function removeUserRate(UserRate $userRate): static
    {
        if ($this->userRates->removeElement($userRate)) {
            // set the owning side to null (unless already changed)
            if ($userRate->getClipId() === $this) {
                $userRate->setClipId(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Mark>
     */
    public function getMarks(): Collection
    {
        return $this->marks;
    }

    public function addMark(Mark $mark): static
    {
        if (!$this->marks->contains($mark)) {
            $this->marks->add($mark);
            $mark->setClipId($this);
        }

        return $this;
    }

    public function removeMark(Mark $mark): static
    {
        if ($this->marks->removeElement($mark)) {
            // set the owning side to null (unless already changed)
            if ($mark->getClipId() === $this) {
                $mark->setClipId(null);
            }
        }

        return $this;
    }
}
