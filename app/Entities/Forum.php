<?php

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'forums')]
class Forum extends Activity
{
    #[ORM\Column(type: 'boolean')]
    private bool $allowDiscussions = true;

    #[ORM\ManyToOne(targetEntity: Course::class, inversedBy: 'forums')]
    private Course $course;

    public function getActivityType(): string
    {
        return 'forum';
    }
}