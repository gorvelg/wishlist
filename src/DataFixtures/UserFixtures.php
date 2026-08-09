<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;


class UserFixtures extends Fixture
{
    private UserPasswordHasherInterface $hasher;

    public function __construct(UserPasswordHasherInterface $hasher)
    {

        $this->hasher = $hasher;
    }

    public function load(ObjectManager $manager): void {

        $users = [
            [
                'firstname' => 'Guillaume',
                'lastname' => 'Gorvel',
                'email' => 'ggorvel@gmail.com',
                'password' => 'qwertyuiop'
            ],
            [
                'firstname' => 'Léa',
                'lastname' => 'Leclerc',
                'email' => 'lealeclerc77@gmail.com',
                'password' => 'qwertyuiop'
            ],
            [
                'firstname' => 'Becky',
                'lastname' => 'Le chien',
                'email' => 'beckylechien@gmail.com',
                'password' => 'qwertyuiop'
            ]
        ];

        foreach ($users as $userData) {
            $user = new User();
            $user->setFirstname($userData['firstname']);
            $user->setLastname($userData['lastname']);
            $user->setEmail($userData['email']);
            $user->setPassword($this->hasher->hashPassword($user, $userData['password']));
            $manager->persist($user);
        }
        $manager->flush();



    }
}
