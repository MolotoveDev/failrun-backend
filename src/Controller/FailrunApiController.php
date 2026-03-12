<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\User; //Importamos la entidad User

final class FailrunApiController extends AbstractController
{
    //Default controller route - DO NOT USE!
    /*#[Route('/failrun/api', name: 'app_failrun_api')]
    public function index(): Response
    {
        return $this->render('failrun_api/index.html.twig', [
            'controller_name' => 'FailrunApiController',
        ]);
    }*/

    #[Route('/failrun/api/test', name: 'app_failrun_api_test')]
    public function test(): Response
    {
        return $this->json(['message' => 'Hello, World!']);
    }

    #[Route('/failrun/api/register', name: 'app_failrun_api_register')]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $user = new User(); //Create user entity and set data
        $user->setUsername($data['username']);
        $user->setEmail($data['email']);
        $user->setRegisterDate(new \DateTime());
        $user->setRoles(['ROLE_USER']);

        //Hash password
        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        $em->persist($user);
        $em->flush();

        return new JSONResponse(['status'=>'success', 'message'=>'User registered successfully'], 201);
    }

    #[Route('/failrun/api/login', name: 'app_failrun_api_login', methods: ['POST'])]
    public function login(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em, JWTTokenManagerInterface $jwtManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $user = $em->getRepository(User::class)->findOneBy(['email' => $data['email']]);

        if (!$user || !$passwordHasher->isPasswordValid($user, $data['password'])) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid credentials'], 401);
        }

        $token = $jwtManager->create($user);

        return new JsonResponse([
            'status' => 'success',
            'token' => $token
        ], 200);
    }

    #[Route('/failrun/api/get-user-info/{id}', name: 'app_failrun_api_get_user_info', methods: ['GET'])]
    public function getUserInfo($id, EntityManagerInterface $em): JsonResponse
    {
        $user = $em->getRepository(User::class)->find($id);
        if (!$user) {
            return new JSONResponse(['status'=>'error', 'message'=>'User not found'], 404);
        } elseif ($user->getId() !== $id) {
            return new JSONResponse(['status'=>'error', 'message'=>'Unauthorized'], 401);
        } else {
            return new JSONResponse([
                'status'=>'success', 
                'data' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'register_date' => $user->getRegisterDate()->format('Y-m-d H:i:s'),
                ]
            ], 200);
        }
    }
}
