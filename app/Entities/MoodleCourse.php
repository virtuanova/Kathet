<?php

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Moodle-compatible Course entity (mdl_course table)
 */
#[ORM\Entity]
#[ORM\Table(name: 'mdl_course')]
#[ORM\Index(columns: ['category', 'sortorder'], name: 'mdl_cour_cat_sor_ix')]
#[ORM\Index(columns: ['visible'], name: 'mdl_course_visible_ix')]
#[ORM\Index(columns: ['timemodified'], name: 'mdl_course_timemodified_ix')]
class MoodleCourse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\Column(type: 'bigint')]
    private int $category = 0;

    #[ORM\Column(type: 'bigint')]
    private int $sortorder = 0;

    #[ORM\Column(type: 'string', length: 254)]
    private string $fullname;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $shortname;

    #[ORM\Column(type: 'string', length: 100)]
    private string $idnumber = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(type: 'smallint')]
    private int $summaryformat = 0;

    #[ORM\Column(type: 'string', length: 21)]
    private string $format = 'topics';

    #[ORM\Column(type: 'smallint')]
    private int $showgrades = 1;

    #[ORM\Column(type: 'smallint')]
    private int $newsitems = 5;

    #[ORM\Column(type: 'bigint')]
    private int $startdate = 0;

    #[ORM\Column(type: 'bigint')]
    private int $enddate = 0;

    #[ORM\Column(type: 'bigint')]
    private int $marker = 0;

    #[ORM\Column(type: 'bigint')]
    private int $maxbytes = 0;

    #[ORM\Column(type: 'smallint')]
    private int $legacyfiles = 0;

    #[ORM\Column(type: 'smallint')]
    private int $showreports = 0;

    #[ORM\Column(type: 'smallint')]
    private int $visible = 1;

    #[ORM\Column(type: 'smallint')]
    private int $visibleold = 1;

    #[ORM\Column(type: 'smallint')]
    private int $groupmode = 0;

    #[ORM\Column(type: 'smallint')]
    private int $groupmodeforce = 0;

    #[ORM\Column(type: 'bigint')]
    private int $defaultgroupingid = 0;

    #[ORM\Column(type: 'string', length: 30)]
    private string $lang = '';

    #[ORM\Column(type: 'string', length: 50)]
    private string $theme = '';

    #[ORM\Column(type: 'bigint')]
    private int $timecreated = 0;

    #[ORM\Column(type: 'bigint')]
    private int $timemodified = 0;

    #[ORM\Column(type: 'boolean')]
    private bool $requested = false;

    #[ORM\Column(type: 'boolean')]
    private bool $enablecompletion = false;

    #[ORM\Column(type: 'boolean')]
    private bool $completionnotify = false;

    #[ORM\Column(type: 'bigint')]
    private int $cacherev = 0;

    #[ORM\Column(type: 'string', length: 30)]
    private string $calendartype = '';

    #[ORM\Column(type: 'boolean')]
    private bool $showactivitydates = false;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $showcompletionconditions = null;

    // Relationships (using Moodle table names)
    #[ORM\OneToMany(targetEntity: MoodleCourseSection::class, mappedBy: 'course')]
    private Collection $sections;

    #[ORM\OneToMany(targetEntity: MoodleEnrol::class, mappedBy: 'courseid')]
    private Collection $enrolments;

    #[ORM\OneToMany(targetEntity: MoodleCourseModule::class, mappedBy: 'course')]
    private Collection $modules;

    #[ORM\OneToMany(targetEntity: MoodleGradeItem::class, mappedBy: 'courseid')]
    private Collection $gradeItems;

    public function __construct()
    {
        $this->sections = new ArrayCollection();
        $this->enrolments = new ArrayCollection();
        $this->modules = new ArrayCollection();
        $this->gradeItems = new ArrayCollection();
        $this->timecreated = time();
        $this->timemodified = time();
    }

    // Getters and setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFullname(): string
    {
        return $this->fullname;
    }

    public function setFullname(string $fullname): self
    {
        $this->fullname = $fullname;
        $this->timemodified = time();
        return $this;
    }

    public function getShortname(): string
    {
        return $this->shortname;
    }

    public function setShortname(string $shortname): self
    {
        $this->shortname = $shortname;
        $this->timemodified = time();
        return $this;
    }

    public function getIdnumber(): string
    {
        return $this->idnumber;
    }

    public function setIdnumber(string $idnumber): self
    {
        $this->idnumber = $idnumber;
        $this->timemodified = time();
        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): self
    {
        $this->summary = $summary;
        $this->timemodified = time();
        return $this;
    }

    public function getCategory(): int
    {
        return $this->category;
    }

    public function setCategory(int $category): self
    {
        $this->category = $category;
        $this->timemodified = time();
        return $this;
    }

    public function getStartdate(): int
    {
        return $this->startdate;
    }

    public function setStartdate(int $startdate): self
    {
        $this->startdate = $startdate;
        $this->timemodified = time();
        return $this;
    }

    public function getEnddate(): int
    {
        return $this->enddate;
    }

    public function setEnddate(int $enddate): self
    {
        $this->enddate = $enddate;
        $this->timemodified = time();
        return $this;
    }

    public function isVisible(): bool
    {
        return $this->visible === 1;
    }

    public function setVisible(bool $visible): self
    {
        $this->visible = $visible ? 1 : 0;
        $this->timemodified = time();
        return $this;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function setFormat(string $format): self
    {
        $this->format = $format;
        $this->timemodified = time();
        return $this;
    }

    public function getSections(): Collection
    {
        return $this->sections;
    }

    public function getEnrolments(): Collection
    {
        return $this->enrolments;
    }

    public function getModules(): Collection
    {
        return $this->modules;
    }

    public function getGradeItems(): Collection
    {
        return $this->gradeItems;
    }

    public function getTimecreated(): int
    {
        return $this->timecreated;
    }

    public function getTimemodified(): int
    {
        return $this->timemodified;
    }

    public function isCompletionEnabled(): bool
    {
        return $this->enablecompletion;
    }

    public function setCompletionEnabled(bool $enabled): self
    {
        $this->enablecompletion = $enabled;
        $this->timemodified = time();
        return $this;
    }
}