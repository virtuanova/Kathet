<?php

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'resources')]
class Resource extends Activity
{
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $externalUrl = null;

    public function getActivityType(): string
    {
        return 'resource';
    }
}