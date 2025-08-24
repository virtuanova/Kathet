<?php

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'assignments')]
class Assignment extends Activity
{
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $dueDate = null;

    #[ORM\Column(type: 'boolean')]
    private bool $allowLateSubmission = true;

    #[ORM\Column(type: 'integer')]
    private int $maxAttempts = 1;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $submissionType = 'file';

    #[ORM\Column(type: 'integer')]
    private int $maxFileSize = 10485760;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $allowedFileTypes = null;

    public function getDueDate(): ?\DateTime
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTime $dueDate): self
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getAllowLateSubmission(): bool
    {
        return $this->allowLateSubmission;
    }

    public function setAllowLateSubmission(bool $allowLateSubmission): self
    {
        $this->allowLateSubmission = $allowLateSubmission;
        return $this;
    }

    public function getActivityType(): string
    {
        return 'assignment';
    }
}