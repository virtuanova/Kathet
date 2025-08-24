<?php

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Illuminate\Contracts\Auth\Authenticatable;
use LaravelDoctrine\ORM\Auth\Authenticatable as AuthenticatableTrait;

/**
 * Moodle-compatible User entity (mdl_user table)
 */
#[ORM\Entity]
#[ORM\Table(name: 'mdl_user')]
#[ORM\Index(columns: ['email', 'mnethostid'], name: 'mdl_user_ema_mnethostid_ix')]
#[ORM\Index(columns: ['firstname'], name: 'mdl_user_firstname_ix')]
#[ORM\Index(columns: ['lastname'], name: 'mdl_user_lastname_ix')]
#[ORM\Index(columns: ['username'], name: 'mdl_user_username_ix')]
class User implements Authenticatable
{
    use AuthenticatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    // Authentication fields
    #[ORM\Column(type: 'string', length: 30)]
    private string $auth = 'manual';
    
    #[ORM\Column(type: 'boolean')]
    private bool $confirmed = false;
    
    #[ORM\Column(type: 'boolean')]
    private bool $deleted = false;
    
    #[ORM\Column(type: 'boolean')]
    private bool $suspended = false;
    
    #[ORM\Column(type: 'string', length: 9)]
    private string $mnethostid = '1';

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    private string $username;

    #[ORM\Column(type: 'string', length: 255)]
    private string $password;

    #[ORM\Column(type: 'string', length: 255)]
    private string $idnumber = '';

    #[ORM\Column(type: 'string', length: 100)]
    private string $email;

    #[ORM\Column(type: 'string', length: 100)]
    private string $firstname;

    #[ORM\Column(type: 'string', length: 100)]
    private string $lastname;

    // Contact information
    #[ORM\Column(type: 'string', length: 20)]
    private string $phone1 = '';

    #[ORM\Column(type: 'string', length: 20)]
    private string $phone2 = '';

    #[ORM\Column(type: 'string', length: 255)]
    private string $institution = '';

    #[ORM\Column(type: 'string', length: 255)]
    private string $department = '';

    #[ORM\Column(type: 'string', length: 255)]
    private string $address = '';

    #[ORM\Column(type: 'string', length: 120)]
    private string $city = '';

    #[ORM\Column(type: 'string', length: 2)]
    private string $country = '';

    // Profile and preferences
    #[ORM\Column(type: 'string', length: 30)]
    private string $lang = 'en';

    #[ORM\Column(type: 'string', length: 100)]
    private string $timezone = '99';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'smallint')]
    private int $descriptionformat = 1;

    // Timestamps (Moodle uses Unix timestamps as bigint)
    #[ORM\Column(type: 'bigint')]
    private int $firstaccess = 0;

    #[ORM\Column(type: 'bigint')]
    private int $lastaccess = 0;

    #[ORM\Column(type: 'bigint')]
    private int $lastlogin = 0;

    #[ORM\Column(type: 'bigint')]
    private int $currentlogin = 0;

    #[ORM\Column(type: 'string', length: 45)]
    private string $lastip = '';

    #[ORM\Column(type: 'bigint')]
    private int $timecreated = 0;

    #[ORM\Column(type: 'bigint')]
    private int $timemodified = 0;

    // Email settings
    #[ORM\Column(type: 'boolean')]
    private bool $emailstop = false;

    #[ORM\Column(type: 'smallint')]
    private int $mailformat = 1;

    #[ORM\Column(type: 'smallint')]
    private int $maildigest = 0;

    #[ORM\Column(type: 'smallint')]
    private int $maildisplay = 2;

    #[ORM\ManyToMany(targetEntity: Role::class, inversedBy: 'users')]
    #[ORM\JoinTable(name: 'user_roles')]
    private Collection $roles;

    #[ORM\OneToMany(targetEntity: Enrollment::class, mappedBy: 'user')]
    private Collection $enrollments;

    #[ORM\OneToMany(targetEntity: CourseTeacher::class, mappedBy: 'teacher')]
    private Collection $teachingCourses;

    #[ORM\OneToMany(targetEntity: Submission::class, mappedBy: 'user')]
    private Collection $submissions;

    #[ORM\OneToMany(targetEntity: ForumPost::class, mappedBy: 'author')]
    private Collection $forumPosts;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'sender')]
    private Collection $sentMessages;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'recipient')]
    private Collection $receivedMessages;

    #[ORM\OneToMany(targetEntity: Grade::class, mappedBy: 'student')]
    private Collection $grades;

    #[ORM\OneToMany(targetEntity: UserSession::class, mappedBy: 'user')]
    private Collection $sessions;

    #[ORM\OneToMany(targetEntity: ActivityLog::class, mappedBy: 'user')]
    private Collection $activityLogs;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
        $this->enrollments = new ArrayCollection();
        $this->teachingCourses = new ArrayCollection();
        $this->submissions = new ArrayCollection();
        $this->forumPosts = new ArrayCollection();
        $this->sentMessages = new ArrayCollection();
        $this->receivedMessages = new ArrayCollection();
        $this->grades = new ArrayCollection();
        $this->sessions = new ArrayCollection();
        $this->activityLogs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function getRoles(): Collection
    {
        return $this->roles;
    }

    public function addRole(Role $role): self
    {
        if (!$this->roles->contains($role)) {
            $this->roles->add($role);
        }
        return $this;
    }

    public function removeRole(Role $role): self
    {
        $this->roles->removeElement($role);
        return $this;
    }

    public function hasRole(string $roleName): bool
    {
        foreach ($this->roles as $role) {
            if ($role->getName() === $roleName) {
                return true;
            }
        }
        return false;
    }

    public function getEnrollments(): Collection
    {
        return $this->enrollments;
    }

    public function isEnrolledIn(Course $course): bool
    {
        foreach ($this->enrollments as $enrollment) {
            if ($enrollment->getCourse() === $course && $enrollment->isActive()) {
                return true;
            }
        }
        return false;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier()
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getRememberToken()
    {
        return $this->rememberToken;
    }

    public function setRememberToken($value)
    {
        $this->rememberToken = $value;
    }

    public function getRememberTokenName()
    {
        return 'rememberToken';
    }
}