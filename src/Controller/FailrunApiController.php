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
use App\Entity\UserRequest; //Importamos la entidad UserRequest
use App\Entity\Games; //Importamos la entidad Game
use App\Entity\Clips; //Importamos la entidad Clip

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
        return $this->json(['message' => 'Hello, World!!!!!']); //Simple test endpoint to verify API is working. Can also be used as a health check.
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

        // Return user info in the response.
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
        //Stablish user and data objects
        $user = $security->getUser();
        $data = json_decode($request->getContent(), true);

        $userRequest = new UserRequest();
        $userRequest->setUserId($user);
        
        //Exception managment: If no title or description is provided, return an error response. Both fields are required to create a user request.
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

        //Apply the data to the user request entity and flush it to the database.
        $userRequest->setDateRequest(new \DateTime());
        $userRequest->setStatusRequest(0); //0 = pending, 1 = accepted, 2 = rejected
        $em->persist($userRequest);
        $em->flush();

        return new JsonResponse(['status' => 'success', 'message' => 'Request submitted successfully'], 201);
    }

    #[Route('/failrun/api/get-user-requests', name: 'app_failrun_api_get_user_requests', methods: ['GET'])]
    public function getUserRequests(Security $security, EntityManagerInterface $em): JsonResponse
    {
        $user = $security->getUser();
        $requests = $em->getRepository(UserRequest::class)->findBy(['user_id' => $user]);

        // For every request, we create an array with the relevant data to return it in the response. We also include the isActive field to indicate if the request is still active (pending) or if it has been accepted/rejected and should no longer be shown in the user's request list.
        $data = [];
        foreach ($requests as $request) {
            $data[] = [
                'id' => $request->getId(),
                'title' => $request->getTitleRequest(),
                'description' => $request->getDescriptionRequest(),
                'date' => $request->getDateRequest()->format('Y-m-d H:i:s'),
                'status' => $request->getStatusRequest(),
                'isActive' => $request->getIsActive(), // This field reference if the request has been filed by the user so the frontend can decide if show it or not in the user requests list. It will be set to false (0) when the request is accepted and the user can no longer see it in their list, but it will be still stored in the database for future reference.
            ];
        }

        return new JsonResponse(['status' => 'success', 'data' => $data], 200);
    }

    #[Route('/failrun/api/get-games', name: 'app_failrun_api_get_games', methods: ['GET'])]
    public function getGames(EntityManagerInterface $em): JsonResponse
    {
        $games = $em->getRepository(Games::class)->findAll();

        // For every game, we create an array with the relevant data to return it in the response.
        $data = [];
        foreach ($games as $game) {
            $data[] = [
                'id' => $game->getId(),
                'game_name' => $game->getGameName(),
                'game_description' => $game->getGameDescription(),
                'cover_img' => $game->getCoverImg(),
            ];
        }

        return new JsonResponse(['status' => 'success', 'data' => $data], 200);
    }

    #[Route('/failrun/api/get-game-clips/{gameId}', name: 'app_failrun_api_get_game_clips', methods: ['GET'])]
    public function getGameClips(int $gameId, EntityManagerInterface $em): JsonResponse
    {
        $clips = $em->getRepository(Clips::class)->findBy(['game_id' => $gameId, 'clip_status' => 1]);

        // For every clip, we create an array with the relevant data to return it in the response. We also include the username of the user that uploaded the clip and format the date to a more readable format.
        $data = [];
        foreach ($clips as $clip) {
            $data[] = [
                'id' => $clip->getId(),
                'user_id' => $clip->getUserId()->getUsername(),
                'game_id' => $clip->getGameId()->getId(),
                'clip_title' => $clip->getClipTitle(),
                'clip_link' => $clip->getClipLink(),
                'clip_description' => $clip->getClipDescription(),
                'clip_date' => $clip->getClipDate()->format('Y-m-d H:i:s'),
                'clip_status' => $clip->getClipStatus(),
            ];
        }

        return new JsonResponse(['status' => 'success', 'data' => $data], 200);
    }

    #[Route('/failrun/api/get-clip-info/{clipId}', name: 'app_failrun_api_get_clip_info', methods: ['GET'])]
    public function getClipInfo(int $clipId, EntityManagerInterface $em): JsonResponse
    {
        $clip = $em->getRepository(Clips::class)->find($clipId);
        $conn = $em->getConnection();

        // If ther's no clip before executing the query, we return an error response indicating that the clip was not found. 
        // This is to avoid executing the query with an invalid clipId and to provide a clear error message to the frontend.
        if (!$clip) {
            return new JsonResponse(['status' => 'error', 'message' => 'Clip not found'], 404);
        }

        // When we go to the clip view, we need to fetch not only the clip info, but also the ratings and comments from the users that have rated the clip. 
        // To do this, we execute a custom SQL query that joins the clips, user_rate and user tables to get all the relevant information in a single query. 
        // We order the results by rating in descending order so the highest rated comments appear first in the clip view.
        $sql = "SELECT 
            c.*,
            u.username,
            ur.rate,
            ur.user_comment,
            ur.rate_date
        FROM clips c
        LEFT JOIN user_rate ur ON c.id = ur.clip_id_id
        LEFT JOIN user u ON ur.user_id_id = u.id
        WHERE c.id = :clipId
        ORDER BY ur.rate DESC;";
        
        
        // We execute the query with the provided clipId and fetch all the results as an associative array. 
        // This will give us an array of comments and ratings for the clip, along with the clip info and the username of the users that rated it.
        $result = $conn->executeQuery($sql, ['clipId' => $clipId])->fetchAllAssociative();

        return new JsonResponse(['status' => 'success', 'data' => $result], 200);
    }

    #[Route('/failrun/api/rate-clip/{clipId}', name: 'app_failrun_api_rate_clip', methods: ['PUT'])]
    public function rateClip(int $clipId, Request $request, Security $security, EntityManagerInterface $em): JsonResponse
    {
        // Validate user
        $user = $security->getUser();
        if (!$user) {
            return new JsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        // Validate payload
        $data = json_decode($request->getContent(), true);
        if (!isset($data['rate'], $data['comment'])) {
            return new JsonResponse(['status' => 'error', 'message' => 'Missing required fields: rate, comment'], 400);
        }

        if (!is_numeric($data['rate']) || $data['rate'] < 1 || $data['rate'] > 5) {
            return new JsonResponse(['status' => 'error', 'message' => 'Rate must be a number between 1 and 5'], 400);
        }

        // Validate clip
        $clip = $em->getRepository(Clips::class)->find($clipId);
        if (!$clip) {
            return new JsonResponse(['status' => 'error', 'message' => 'Clip not found'], 404);
        }

        // Update or create rating
        $rating = $em->getRepository(UserRate::class)->findOneBy(['user_id' => $user, 'clip_id' => $clip])
            ?? new UserRate();

        $rating->setUserId($user);
        $rating->setClipId($clip);
        $rating->setRate($data['rate']);
        $rating->setUserComment($data['comment']);
        $rating->setRateDate(new \DateTimeImmutable());

        $em->persist($rating);
        $em->flush();

        return new JsonResponse(['status' => 'success', 'message' => 'Clip rated successfully'], 200);
    }
}
