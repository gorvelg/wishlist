<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        Security $security,
        EntityManagerInterface $entityManager,
        AuthenticationUtils $authenticationUtils,
    ): Response {
        $user = new User();

        $form = $this->createForm(
            RegistrationFormType::class,
            $user,
            [
                'action' => $this->generateUrl('app_register'),
            ]
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $entityManager->persist($user);
            $entityManager->flush();

            /*
             * Connexion automatique.
             *
             * Comme le target_path est dans la session,
             * Symfony redirigera vers la page d'origine.
             */
            return $security->login(
                $user,
                'form_login',
                'main'
            );
        }

        return $this->render('security/login.html.twig', [
            'registrationForm' => $form,
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => null,
            'active_tab' => 'register',
        ]);
    }
}
