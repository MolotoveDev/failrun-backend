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
        return $this->json(['message' => 'Hello, World!']); //Simple test endpoint to verify API is working. Can also be used as a health check.
    }

    #[Route('/failrun/api/register', name: 'app_failrun_api_register')]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        //Create user entity and set data from request. We also set the registration date and default role for the user.
        $user = new User(); 
        $user->setUsername($data['username']);
        $user->setEmail($data['email']);
        $user->setRegisterDate(new \DateTime());
        $user->setRoles(['ROLE_USER']);
        $user->setProfilePic($data['profilePic'] ?? null); //Set profile picture if provided

        //Hash password
        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        //Persist user to database. Security checks must be done in frontend.
        $em->persist($user);
        $em->flush();

        return new JsonResponse(['status'=>'success', 'message'=>'User registered successfully'], 201);
    }

    #[Route('/failrun/api/login', name: 'app_failrun_api_login', methods: ['POST'])]
    public function login(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em, JWTTokenManagerInterface $jwtManager): JsonResponse
    {
        //Get login data through JSON, decode it and get user from email.
        $data = json_decode($request->getContent(), true); 

        $user = $em->getRepository(User::class)->findOneBy(['email' => $data['email']]);

        //Security check: if user doesn't exist or password is invalid, return error response.
        if (!$user || !$passwordHasher->isPasswordValid($user, $data['password'])) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid credentials'], 401);
        }

        //If everything checks, create JWT token for the user and return it in the response.
        $token = $jwtManager->create($user);

        return new JsonResponse([
            'status' => 'success',
            'token' => $token
        ], 200);
    }

    #[Route('/failrun/api/get-user-info', name: 'app_failrun_api_get_user_info', methods: ['GET'])]
    public function getUserInfo(Security $security): JsonResponse
    {
        $user = $security->getUser(); //Fetch user info from token

        return new JsonResponse([
            'status' => 'success',
            'data' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'register_date' => $user->getRegisterDate()->format('Y-m-d H:i:s'),
                'profilePic' => $user->getProfilePic(),
            ]
        ], 200);
    }

    #[Route('/failrun/api/send-user-request', name: 'app_failrun_api_send_user_request', methods: ['POST'])]
    public function sendUserRequest(Request $request, Security $security, EntityManagerInterface $em): JsonResponse
    {
        $user = $security->getUser();
        $data = json_decode($request->getContent(), true);

        $userRequest = new UserRequest();
        $userRequest->setUser($user);
        
        if (!empty($data['title'])) {
            $userRequest->setTitleRequest($data['title']);
        } else {
            return new JsonResponse(['status' => 'error', 'message' => 'Title is required'], 400);
        }

        if (!empty($data['description'])) {
            $userRequest->setDescriptionRequest($data['description']);
        } else {
            return new JsonResponse(['status' => 'error', 'message' => 'Description is required'], 400);
        }

        $userRequest->setCreatedAt(new \DateTime());
        $userRequest->setStatus(0); //0 = pending, 1 = accepted, 2 = rejected
        $em->persist($userRequest);
        $em->flush();

        return new JsonResponse(['status' => 'success', 'message' => 'Request submitted successfully'], 201);
    }
}
