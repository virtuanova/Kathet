<?php

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'quizzes')]
class Quiz extends Activity
{
    #[ORM\Column(type: 'integer')]
    private int $timeLimit = 0;

    #[ORM\Column(type: 'integer')]
    private int $attemptsAllowed = 1;

    #[ORM\Column(type: 'boolean')]
    private bool $shuffleQuestions = false;

    public function getActivityType(): string
    {
        return 'quiz';
    }
}