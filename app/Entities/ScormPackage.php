<?php

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'scorm_packages')]
class ScormPackage extends Activity
{
    #[ORM\Column(type: 'string', length: 255)]
    private string $packagePath;

    #[ORM\Column(type: 'string', length: 100)]
    private string $version = '1.2';

    public function getActivityType(): string
    {
        return 'scorm';
    }
}