<?php

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'lessons')]
class Lesson extends Activity
{
    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(type: 'boolean')]
    private bool $allowComments = true;

    public function getActivityType(): string
    {
        return 'lesson';
    }
}