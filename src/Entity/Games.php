<?php

namespace App\Entity;

use App\Repository\GamesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GamesRepository::class)]
class Games
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $game_name = null;

    #[ORM\Column(length: 2555, nullable: true)]
    private ?string $game_description = null;

    #[ORM\Column(length: 2555, nullable: true)]
    private ?string $cover_img = null;

    /**
     * @var Collection<int, Clips>
     */
    #[ORM\OneToMany(targetEntity: Clips::class, mappedBy: 'game_id')]
    private Collection $clips;

    public function __construct()
    {
        $this->clips = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGameName(): ?string
    {
        return $this->game_name;
    }

    public function setGameName(string $game_name): static
    {
        $this->game_name = $game_name;

        return $this;
    }

    public function getGameDescription(): ?string
    {
        return $this->game_description;
    }

    public function setGameDescription(?string $game_description): static
    {
        $this->game_description = $game_description;

        return $this;
    }

    public function getCoverImg(): ?string
    {
        return $this->cover_img;
    }

    public function setCoverImg(?string $cover_img): static
    {
        $this->cover_img = $cover_img;

        return $this;
    }

    /**
     * @return Collection<int, Clips>
     */
    public function getClips(): Collection
    {
        return $this->clips;
    }

    public function addClip(Clips $clip): static
    {
        if (!$this->clips->contains($clip)) {
            $this->clips->add($clip);
            $clip->setGameId($this);
        }

        return $this;
    }

    public function removeClip(Clips $clip): static
    {
        if ($this->clips->removeElement($clip)) {
            // set the owning side to null (unless already changed)
            if ($clip->getGameId() === $this) {
                $clip->setGameId(null);
            }
        }

        return $this;
    }
}
