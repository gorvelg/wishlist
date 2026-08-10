<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class SecurityController extends AbstractController
{
    use TargetPathTrait;

    #[Route(path: '/login', name: 'app_login')]
    public function login(
        Request $request,
        AuthenticationUtils $authenticationUtils,
    ): Response {
        /*
         * Si on arrive depuis une page de l'application,
         * on mémorise cette page comme destination.
         */
        $redirect = $request->query->get('redirect');

        if ($this->isSafeRedirect($redirect)) {
            $this->saveTargetPath(
                $request->getSession(),
                'main',
                $redirect
            );
        }

        $registrationForm = $this->createForm(
            RegistrationFormType::class,
            new User(),
            [
                'action' => $this->generateUrl('app_register'),
            ]
        );

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'registrationForm' => $registrationForm,

            'active_tab' => $request->query->get('tab') === 'register'
                ? 'register'
                : 'login',
        ]);
    }

    private function isSafeRedirect(?string $redirect): bool
    {
        if (!$redirect) {
            return false;
        }

        /*
         * On accepte uniquement les URLs internes :
         *
         * /
         * /wishlist/xxx
         * /profile
         *
         * mais jamais :
         *
         * https://site-pirate.com
         * //site-pirate.com
         */
        return str_starts_with($redirect, '/')
            && !str_starts_with($redirect, '//')
            && !str_contains($redirect, '\\');
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException(
            'This method can be blank - it will be intercepted by the logout key on your firewall.'
        );
    }
}
