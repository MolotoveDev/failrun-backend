<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class FailrunAdminPanelController extends AbstractController
{
    #[Route('/failrun/admin/login', name: 'app_failrun_admin_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_failrun_admin_panel');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('failrun_admin_panel/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/failrun/admin/panel', name: 'app_failrun_admin_panel')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        return $this->render('failrun_admin_panel/index.html.twig');
    }

    #[Route('/failrun/admin/logout', name: 'app_failrun_admin_logout')]
    public function logout(): void {}
}