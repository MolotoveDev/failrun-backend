<?php

namespace App\Entity;

use App\Repository\MarkTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MarkTypeRepository::class)]
class MarkType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $mark_name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mark_description = null;

    #[ORM\Column(length: 2555, nullable: true)]
    private ?string $mark_logo_url = null;

    /**
     * @var Collection<int, Mark>
     */
    #[ORM\OneToMany(targetEntity: Mark::class, mappedBy: 'id_mark_type')]
    private Collection $marks;

    public function __construct()
    {
        $this->marks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMarkName(): ?string
    {
        return $this->mark_name;
    }

    public function setMarkName(string $mark_name): static
    {
        $this->mark_name = $mark_name;

        return $this;
    }

    public function getMarkDescription(): ?string
    {
        return $this->mark_description;
    }

    public function setMarkDescription(?string $mark_description): static
    {
        $this->mark_description = $mark_description;

        return $this;
    }

    public function getMarkLogoUrl(): ?string
    {
        return $this->mark_logo_url;
    }

    public function setMarkLogoUrl(?string $mark_logo_url): static
    {
        $this->mark_logo_url = $mark_logo_url;

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
            $mark->setIdMarkType($this);
        }

        return $this;
    }

    public function removeMark(Mark $mark): static
    {
        if ($this->marks->removeElement($mark)) {
            // set the owning side to null (unless already changed)
            if ($mark->getIdMarkType() === $this) {
                $mark->setIdMarkType(null);
            }
        }

        return $this;
    }
}
