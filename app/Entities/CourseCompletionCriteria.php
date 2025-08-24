<?php

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'course_completion_criteria')]
class CourseCompletionCriteria
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Course::class, inversedBy: 'completionCriteria')]
    private Course $course;

    #[ORM\Column(type: 'string', length: 50)]
    private string $criteriaType;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $criteriaData = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}