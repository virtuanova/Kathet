<?php

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use LaravelDoctrine\Extensions\Timestamps\Timestamps;
use LaravelDoctrine\Extensions\SoftDeletes\SoftDeletes;
use LaravelDoctrine\Extensions\Sluggable\Sluggable;

#[ORM\Entity]
#[ORM\Table(name: 'courses')]
#[ORM\Index(columns: ['code'], name: 'idx_course_code')]
#[ORM\Index(columns: ['category_id'], name: 'idx_course_category')]
class Course
{
    use Timestamps, SoftDeletes, Sluggable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $fullName;

    #[ORM\Column(type: 'string', length: 100)]
    private string $shortName;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private string $code;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $format = 'topics';

    #[ORM\Column(type: 'integer')]
    private int $sectionCount = 10;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $startDate;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $endDate = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isVisible = true;

    #[ORM\Column(type: 'boolean')]
    private bool $enrollmentEnabled = true;

    #[ORM\Column(type: 'string', length: 50)]
    private string $enrollmentKey = '';

    #[ORM\Column(type: 'boolean')]
    private bool $guestAccess = false;

    #[ORM\Column(type: 'integer')]
    private int $maxStudents = 0;

    #[ORM\Column(type: 'boolean')]
    private bool $completionTracking = true;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $thumbnailImage = null;

    #[ORM\Column(type: 'string', length: 10)]
    private string $language = 'en';

    #[ORM\Column(type: 'integer')]
    private int $credits = 0;

    #[ORM\ManyToOne(targetEntity: CourseCategory::class, inversedBy: 'courses')]
    #[ORM\JoinColumn(nullable: false)]
    private CourseCategory $category;

    #[ORM\OneToMany(targetEntity: CourseSection::class, mappedBy: 'course', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $sections;

    #[ORM\OneToMany(targetEntity: Enrollment::class, mappedBy: 'course')]
    private Collection $enrollments;

    #[ORM\OneToMany(targetEntity: CourseTeacher::class, mappedBy: 'course')]
    private Collection $teachers;

    #[ORM\OneToMany(targetEntity: Activity::class, mappedBy: 'course')]
    private Collection $activities;

    #[ORM\OneToMany(targetEntity: Grade::class, mappedBy: 'course')]
    private Collection $grades;

    #[ORM\OneToMany(targetEntity: Forum::class, mappedBy: 'course')]
    private Collection $forums;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'courses')]
    #[ORM\JoinTable(name: 'course_tags')]
    private Collection $tags;

    #[ORM\OneToMany(targetEntity: CourseCompletionCriteria::class, mappedBy: 'course')]
    private Collection $completionCriteria;

    #[ORM\OneToMany(targetEntity: Badge::class, mappedBy: 'course')]
    private Collection $badges;

    public function __construct()
    {
        $this->sections = new ArrayCollection();
        $this->enrollments = new ArrayCollection();
        $this->teachers = new ArrayCollection();
        $this->activities = new ArrayCollection();
        $this->grades = new ArrayCollection();
        $this->forums = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->completionCriteria = new ArrayCollection();
        $this->badges = new ArrayCollection();
        $this->startDate = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): self
    {
        $this->fullName = $fullName;
        return $this;
    }

    public function getShortName(): string
    {
        return $this->shortName;
    }

    public function setShortName(string $shortName): self
    {
        $this->shortName = $shortName;
        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getCategory(): CourseCategory
    {
        return $this->category;
    }

    public function setCategory(CourseCategory $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getSections(): Collection
    {
        return $this->sections;
    }

    public function addSection(CourseSection $section): self
    {
        if (!$this->sections->contains($section)) {
            $this->sections->add($section);
            $section->setCourse($this);
        }
        return $this;
    }

    public function getEnrollments(): Collection
    {
        return $this->enrollments;
    }

    public function getActiveEnrollmentCount(): int
    {
        return $this->enrollments->filter(function(Enrollment $enrollment) {
            return $enrollment->isActive();
        })->count();
    }

    public function getTeachers(): Collection
    {
        return $this->teachers;
    }

    public function addTeacher(User $user, string $role = 'teacher'): self
    {
        $courseTeacher = new CourseTeacher();
        $courseTeacher->setCourse($this);
        $courseTeacher->setTeacher($user);
        $courseTeacher->setRole($role);
        
        if (!$this->teachers->contains($courseTeacher)) {
            $this->teachers->add($courseTeacher);
        }
        
        return $this;
    }

    public function isUserTeacher(User $user): bool
    {
        foreach ($this->teachers as $teacher) {
            if ($teacher->getTeacher() === $user) {
                return true;
            }
        }
        return false;
    }

    public function getActivities(): Collection
    {
        return $this->activities;
    }

    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): self
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }
        return $this;
    }

    public function getSlugField(): string
    {
        return 'code';
    }
}