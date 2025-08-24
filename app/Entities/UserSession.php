<?php

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_sessions')]
class UserSession
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 100)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'sessions')]
    private User $user;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $lastActivity;

    public function getId(): string
    {
        return $this->id;
    }
}