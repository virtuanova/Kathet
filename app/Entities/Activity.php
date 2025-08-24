<?php

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use LaravelDoctrine\Extensions\Timestamps\Timestamps;

#[ORM\Entity]
#[ORM\Table(name: 'activities')]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'activity_type', type: 'string')]
#[ORM\DiscriminatorMap([
    'assignment' => Assignment::class,
    'quiz' => Quiz::class,
    'forum' => Forum::class,
    'resource' => Resource::class,
    'lesson' => Lesson::class,
    'scorm' => ScormPackage::class
])]
abstract class Activity
{
    use Timestamps;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    protected string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    protected ?string $description = null;

    #[ORM\Column(type: 'text', nullable: true)]
    protected ?string $instructions = null;

    #[ORM\Column(type: 'integer')]
    protected int $position = 0;

    #[ORM\Column(type: 'boolean')]
    protected bool $isVisible = true;

    #[ORM\Column(type: 'datetime', nullable: true)]
    protected ?\DateTime $availableFrom = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    protected ?\DateTime $availableUntil = null;

    #[ORM\Column(type: 'boolean')]
    protected bool $completionTracking = false;

    #[ORM\Column(type: 'boolean')]
    protected bool $graded = false;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    protected ?float $maxGrade = null;

    #[ORM\ManyToOne(targetEntity: Course::class, inversedBy: 'activities')]
    #[ORM\JoinColumn(nullable: false)]
    protected Course $course;

    #[ORM\ManyToOne(targetEntity: CourseSection::class, inversedBy: 'activities')]
    #[ORM\JoinColumn(nullable: false)]
    protected CourseSection $section;

    #[ORM\OneToMany(targetEntity: Submission::class, mappedBy: 'activity')]
    protected Collection $submissions;

    #[ORM\OneToMany(targetEntity: Grade::class, mappedBy: 'activity')]
    protected Collection $grades;

    public function __construct()
    {
        $this->submissions = new ArrayCollection();
        $this->grades = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getCourse(): Course
    {
        return $this->course;
    }

    public function setCourse(Course $course): self
    {
        $this->course = $course;
        return $this;
    }

    public function getSection(): CourseSection
    {
        return $this->section;
    }

    public function setSection(CourseSection $section): self
    {
        $this->section = $section;
        return $this;
    }

    public function isGraded(): bool
    {
        return $this->graded;
    }

    public function setGraded(bool $graded): self
    {
        $this->graded = $graded;
        return $this;
    }

    public function getMaxGrade(): ?float
    {
        return $this->maxGrade;
    }

    public function setMaxGrade(?float $maxGrade): self
    {
        $this->maxGrade = $maxGrade;
        return $this;
    }

    public function getSubmissions(): Collection
    {
        return $this->submissions;
    }

    public function getGrades(): Collection
    {
        return $this->grades;
    }

    abstract public function getActivityType(): string;
}