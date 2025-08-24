<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Since we're using Doctrine ORM, we'll create simple SQL seeders
        // For production, you would use Doctrine's EntityManager
        
        $this->createBasicData();
    }
    
    private function createBasicData(): void
    {
        // Create basic users for testing
        DB::table('users')->insert([
            [
                'username' => 'admin',
                'email' => 'admin@lms.com',
                'password' => Hash::make('password123'),
                'firstName' => 'Admin',
                'lastName' => 'User',
                'language' => 'en',
                'timezone' => 'UTC',
                'isActive' => true,
                'emailVerified' => true,
                'createdAt' => now(),
                'updatedAt' => now()
            ],
            [
                'username' => 'teacher',
                'email' => 'teacher@lms.com',
                'password' => Hash::make('password123'),
                'firstName' => 'Teacher',
                'lastName' => 'Demo',
                'language' => 'en',
                'timezone' => 'UTC',
                'isActive' => true,
                'emailVerified' => true,
                'createdAt' => now(),
                'updatedAt' => now()
            ],
            [
                'username' => 'student',
                'email' => 'student@lms.com',
                'password' => Hash::make('password123'),
                'firstName' => 'Student',
                'lastName' => 'Demo',
                'language' => 'en',
                'timezone' => 'UTC',
                'isActive' => true,
                'emailVerified' => true,
                'createdAt' => now(),
                'updatedAt' => now()
            ]
        ]);
        
        // Create course categories
        DB::table('course_categories')->insert([
            [
                'name' => 'Computer Science',
                'description' => 'Programming and software development courses',
                'isVisible' => true,
                'sortOrder' => 1,
                'createdAt' => now(),
                'updatedAt' => now()
            ],
            [
                'name' => 'Mathematics',
                'description' => 'Mathematical concepts and applications',
                'isVisible' => true,
                'sortOrder' => 2,
                'createdAt' => now(),
                'updatedAt' => now()
            ],
            [
                'name' => 'Business',
                'description' => 'Business and management courses',
                'isVisible' => true,
                'sortOrder' => 3,
                'createdAt' => now(),
                'updatedAt' => now()
            ]
        ]);
        
        echo "Basic test data seeded successfully!\n";
        echo "Test accounts:\n";
        echo "- admin@lms.com / password123\n";
        echo "- teacher@lms.com / password123\n";
        echo "- student@lms.com / password123\n";
    }
}
