<?php

namespace App\Controller;

use App\Entity\Clips;
use App\Entity\Games;
use App\Entity\Mark;
use App\Entity\MarkType;
use App\Entity\User;
use App\Entity\UserRate;
use App\Entity\UserRequest;
use Doctrine\ORM\EntityManagerInterface;
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
    public function index(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('failrun_admin_panel/index.html.twig', [
            'users'        => $em->getRepository(User::class)->findAll(),
            'clips'        => $em->getRepository(Clips::class)->findAll(),
            'games'        => $em->getRepository(Games::class)->findAll(),
            'marks'        => $em->getRepository(Mark::class)->findAll(),
            'mark_types'   => $em->getRepository(MarkType::class)->findAll(),
            'user_rates'   => $em->getRepository(UserRate::class)->findAll(),
            'user_requests'=> $em->getRepository(UserRequest::class)->findAll(),
        ]);
    }

    #[Route('/failrun/admin/logout', name: 'app_failrun_admin_logout')]
    public function logout(): void {}
}