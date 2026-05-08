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
use App\Entity\User;
use App\Entity\UserRequest;
use App\Entity\UserRate;
use App\Entity\Games;
use App\Entity\Clips;
use OpenApi\Attributes as OA;

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

    #[OA\Get(
        path: '/failrun/api/test',
        summary: 'Health check',
        tags: ['Health'],
        responses: [
            new OA\Response(response: 200, description: 'API is running')
        ]
    )]
    #[Route('/failrun/api/test', name: 'app_failrun_api_test', methods: ['GET'])]
    public function test(): Response
    {
        return $this->json(['message' => 'Hello, World!!!!!']); //Simple test endpoint to verify API is working. Can also be used as a health check.
    }

    #[OA\Get(
        path: '/failrun/api/key-test',
        summary: 'Verify that the provided API key is valid',
        tags: ['Health'],
        security: [['ApiKey' => []]],
        responses: [
            new OA\Response(response: 200, description: 'API key is valid'),
            new OA\Response(response: 401, description: 'Invalid or missing API key')
        ]
    )]
    #[Route('/failrun/api/key-test', name: 'app_failrun_api_key_test', methods: ['GET'])]
    public function keyTest(): JsonResponse
    {
        return $this->json(['message' => 'API key test successful']);
    }

    #[OA\Post(
        path: '/failrun/api/register',
        summary: 'Register a new user',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'player99'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'player@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'secret123'),
                    new OA\Property(property: 'profilePic', type: 'string', nullable: true, example: 'https://example.com/avatar.png'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'User registered successfully'),
        ]
    )]
    #[Route('/failrun/api/register', name: 'app_failrun_api_register', methods: ['POST'])]
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

    #[OA\Post(
        path: '/failrun/api/login',
        summary: 'Login and receive a JWT token',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'player@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'secret123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'JWT token returned'),
            new OA\Response(response: 401, description: 'Invalid credentials'),
        ]
    )]
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

    #[OA\Get(
        path: '/failrun/api/get-user-info',
        summary: 'Get the authenticated user\'s profile',
        tags: ['Users'],
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(response: 200, description: 'User info returned'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
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

    #[OA\Post(
        path: '/failrun/api/send-user-request',
        summary: 'Submit a clip or game request',
        tags: ['Requests'],
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'description'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Add Hollow Knight'),
                    new OA\Property(property: 'description', type: 'string', example: 'Please add Hollow Knight to the games list'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Request submitted'),
            new OA\Response(response: 400, description: 'Missing required fields'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
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

    #[OA\Get(
        path: '/failrun/api/get-user-requests',
        summary: 'Get all requests submitted by the authenticated user',
        tags: ['Requests'],
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(response: 200, description: 'List of user requests'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
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

    #[OA\Get(
        path: '/failrun/api/get-games',
        summary: 'Get all available games',
        tags: ['Games'],
        responses: [
            new OA\Response(response: 200, description: 'List of games'),
        ]
    )]
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

    #[OA\Get(
        path: '/failrun/api/get-game-clips/{gameId}',
        summary: 'Get all approved clips for a given game',
        tags: ['Games'],
        parameters: [
            new OA\Parameter(name: 'gameId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of clips for the game'),
        ]
    )]
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
  
    #[OA\Get(
        path: '/failrun/api/get-clip-info/{clipId}',
        summary: 'Get clip details including ratings and comments',
        tags: ['Clips'],
        parameters: [
            new OA\Parameter(name: 'clipId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Clip info with ratings'),
            new OA\Response(response: 404, description: 'Clip not found'),
        ]
    )]
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
                ur.id AS rate_id,
                u.username,
                ur.rate,
                ur.user_comment,
                ur.rate_date,
                (SELECT AVG(rate) FROM user_rate WHERE clip_id_id = c.id) AS mediaTotal
            FROM clips c
            LEFT JOIN user_rate ur ON c.id = ur.clip_id_id
            LEFT JOIN user u ON ur.user_id_id = u.id
            WHERE c.id = :clipId
            ORDER BY ur.rate DESC;";

        $bufferAverage = "SELECT AVG(rate) AS mediaTotal
                          FROM user_rate ur
                          LEFT JOIN clips c ON c.id = ur.clip_id_id
                          WHERE clip_id_id = :clipId;";
        
        
        // We execute the query with the provided clipId and fetch all the results as an associative array. 
        // This will give us an array of comments and ratings for the clip, along with the clip info and the username of the users that rated it.
        $result  = $conn->executeQuery($sql, ['clipId' => $clipId])->fetchAllAssociative();
        $average = $conn->executeQuery($bufferAverage, ['clipId' => $clipId])->fetchAssociative();

        return new JsonResponse([
            'status'  => 'success',
            'data'    => $result,
            'average' => $average['mediaTotal'],
        ], 200);
    }

    #[OA\Put(
        path: '/failrun/api/rate-clip/{clipId}',
        summary: 'Rate a clip (create or update existing rating)',
        tags: ['Clips'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'clipId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['rate', 'comment'],
                properties: [
                    new OA\Property(property: 'rate', type: 'integer', minimum: 1, maximum: 5, example: 4),
                    new OA\Property(property: 'comment', type: 'string', example: 'Great clip!'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Rating saved'),
            new OA\Response(response: 400, description: 'Invalid rate value or missing fields'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Clip not found'),
        ]
    )]
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
        $rating->setRateDate(new \DateTime());

        $em->persist($rating);
        $em->flush();

        return new JsonResponse(['status' => 'success', 'message' => 'Clip rated successfully'], 200);
    }

    #[OA\Delete(
        path: '/failrun/api/delete-rate/{rateId}',
        summary: 'Delete a rating (owner or moderator only)',
        tags: ['Clips'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'rateId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rating deleted'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Rating not found'),
        ]
    )]
    #[Route('/failrun/api/delete-rate/{rateId}', name: 'app_failrun_api_delete_rate', methods: ['DELETE'])]
    public function deleteRate(int $rateId, Security $security, EntityManagerInterface $em): JsonResponse
    {
        // Validate user
        $user = $security->getUser();
        if (!$user) {
            return new JsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        // Find the rating
        $rating = $em->getRepository(UserRate::class)->find($rateId);
        if (!$rating) {
            return new JsonResponse(['status' => 'error', 'message' => 'Rating not found'], 404);
        }

        // Check if the user is the owner of the rating or has ROLE_ADMIN or ROLE_MODERATOR
        if ($rating->getUserId() !== $user && !$security->isGranted('ROLE_ADMIN') && !$security->isGranted('ROLE_MODERATOR')) {
            return new JsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        // Delete the rating
        $em->remove($rating);
        $em->flush();

        return new JsonResponse(['status' => 'success', 'message' => 'Rating deleted successfully'], 200);
    }

    #[OA\Delete(
        path: '/failrun/api/remove-clip/{clipId}',
        summary: 'Remove a clip (owner or moderator only)',
        tags: ['Clips'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'clipId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Clip removed'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Clip not found'),
        ]
    )]
    #[Route('/failrun/api/remove-clip/{clipId}', name: 'app_failrun_api_remove_clip', methods: ['DELETE'])]
    public function removeClip(int $clipId, Security $security, EntityManagerInterface $em): JsonResponse
    {
        // Validate user
        $user = $security->getUser();
        if (!$user) {
            return new JsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        // Find the clip
        $clip = $em->getRepository(Clips::class)->find($clipId);
        if (!$clip) {
            return new JsonResponse(['status' => 'error', 'message' => 'Clip not found'], 404);
        }

        // Check if the user is the owner of the clip or has ROLE_ADMIN or ROLE_MODERATOR
        if ($clip->getUserId() !== $user && !$security->isGranted('ROLE_ADMIN') && !$security->isGranted('ROLE_MODERATOR')) {
            return new JsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        // Delete the clip
        $em->remove($clip);
        $em->flush();

        return new JsonResponse(['status' => 'success', 'message' => 'Clip removed successfully'], 200);
    }
  
    #[OA\Get(
        path: '/failrun/api/get-user-clips/{userId}',
        summary: 'Get all clips submitted by a specific user',
        tags: ['Clips'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of user clips'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    #[Route('/failrun/api/get-user-clips/{userId}', name: 'app_failrun_api_get_user_clips', methods: ['GET'])]
    public function getUserClips(int $userId, EntityManagerInterface $em): JsonResponse
    {
        $clips = $em->getRepository(Clips::class)->findBy(['user_id' => $userId]);

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

    #[OA\Post(
        path: '/failrun/api/submit-clip',
        summary: 'Submit a new clip for review',
        tags: ['Clips'],
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['gameId', 'clipTitle', 'clipLink'],
                properties: [
                    new OA\Property(property: 'gameId', type: 'integer', example: 3),
                    new OA\Property(property: 'clipTitle', type: 'string', example: 'Epic fail run'),
                    new OA\Property(property: 'clipLink', type: 'string', example: 'https://www.youtube.com/watch?v=abc123'),
                    new OA\Property(property: 'clipDescription', type: 'string', nullable: true, example: 'Almost had it!'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Clip submitted and pending review'),
            new OA\Response(response: 400, description: 'Missing required fields'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Game not found'),
        ]
    )]
    #[Route('/failrun/api/submit-clip', name: 'app_failrun_api_submit_clip', methods: ['POST'])]
    public function submitClip(Request $request, Security $security, EntityManagerInterface $em): JsonResponse
    {
        //We get the userid
        $user = $security->getUser();
        if (!$user) {
            return new JsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        //Treat the request data and validate it
        $data = json_decode($request->getContent(), true);
        if (!isset($data['gameId'])) {
            return new JsonResponse(['status' => 'error', 'message' => 'Missing required field: gameId'], 400);
        }

        if(!isset($data['clipTitle'])) {
            return new JsonResponse(['status' => 'error', 'message' => 'Missing required field: clipTitle'], 400);
        }

        if (!isset($data['clipLink'])) {
            return new JsonResponse(['status' => 'error', 'message' => 'Missing required field: clipLink'], 400);
        }

        if (!isset($data['clipDescription'])) {
            $clipDescription = null; //Clip description is optional, so we set it to null if it's not provided.
        }
        else {
            $clipDescription = $data['clipDescription'];
        }

        $game = $em->getRepository(Games::class)->find($data['gameId']);

        if (!$game) {
            return new JsonResponse(['status' => 'error', 'message' => 'Game not found'], 404);
        }

        $clip = new Clips();
        $clip->setUserId($user);
        $clip->setGameId($game);
        $clip->setClipTitle($data['clipTitle']);
        $clip->setClipLink($data['clipLink']);
        $clip->setClipDescription($clipDescription);
        $clip->setClipDate(new \DateTime());
        $clip->setClipStatus(0); //Set clip status to 0 (pending)

        $em->persist($clip);
        $em->flush();

        return new JsonResponse(['status' => 'success', 'message' => 'Clip submitted successfully'], 200);
    }

    #[OA\Put(
        path: '/failrun/api/modify-clip/{clipId}',
        summary: 'Edit a clip (owner or moderator only)',
        tags: ['Clips'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'clipId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['gameId', 'clipTitle', 'clipLink'],
                properties: [
                    new OA\Property(property: 'gameId', type: 'integer', example: 3),
                    new OA\Property(property: 'clipTitle', type: 'string', example: 'Updated title'),
                    new OA\Property(property: 'clipLink', type: 'string', example: 'https://www.youtube.com/watch?v=xyz'),
                    new OA\Property(property: 'clipDescription', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Clip updated'),
            new OA\Response(response: 400, description: 'Missing required fields'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Clip or game not found'),
        ]
    )]
    #[Route('/failrun/api/modify-clip/{clipId}', name: 'app_failrun_api_modify_clip', methods: ['PUT'])]
    public function modifyClip(int $clipId, Request $request, Security $security, EntityManagerInterface $em): JsonResponse
    {
        $user = $security->getUser();
        if (!$user) {
            return new JsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $clip = $em->getRepository(Clips::class)->find($clipId);
        if (!$clip) {
            return new JsonResponse(['status' => 'error', 'message' => 'Clip not found'], 404);
        }

        // Check if the user is the owner of the clip or has ROLE_ADMIN or ROLE_MODERATOR
        if ($clip->getUserId() !== $user && !$security->isGranted('ROLE_ADMIN') && !$security->isGranted('ROLE_MODERATOR')) {
            return new JsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        // Treat the request data and validate it
        $data = json_decode($request->getContent(), true);
        if (!isset($data['gameId'])) {
            return new JsonResponse(['status' => 'error', 'message' => 'Missing required field: gameId'], 400);
        }

        if(!isset($data['clipTitle'])) {
            return new JsonResponse(['status' => 'error', 'message' => 'Missing required field: clipTitle'], 400);
        }

        if (!isset($data['clipLink'])) {
            return new JsonResponse(['status' => 'error', 'message' => 'Missing required field: clipLink'], 400);
        }

        if (!isset($data['clipDescription'])) {
            $clipDescription = null; //Clip description is optional, so we set it to null if it's not provided.
        }
        else {
            $clipDescription = $data['clipDescription'];
        }

        $game = $em->getRepository(Games::class)->find($data['gameId']);

        if (!$game) {
            return new JsonResponse(['status' => 'error', 'message' => 'Game not found'], 404);
        }

        $clip->setGameId($game);
        $clip->setClipTitle($data['clipTitle']);
        $clip->setClipLink($data['clipLink']);
        $clip->setClipDescription($clipDescription);

        $em->flush();

        return new JsonResponse(['status' => 'success', 'message' => 'Clip modified successfully'], 200);
    }

    #[OA\Put(
        path: '/failrun/api/archive-request/{requestId}',
        summary: 'Archive (hide) a request from the user\'s list',
        tags: ['Requests'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'requestId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Request archived'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Request not found'),
        ]
    )]
    #[Route('/failrun/api/archive-request/{requestId}', name: 'app_failrun_api_archive_request', methods: ['PUT'])]
    public function archiveRequest(int $requestId, Security $security, EntityManagerInterface $em): JsonResponse
    {
        $user = $security->getUser();
        if (!$user) {
            return new JsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $request = $em->getRepository(UserRequest::class)->find($requestId);
        if (!$request) {
            return new JsonResponse(['status' => 'error', 'message' => 'Request not found'], 404);
        }

        // Check if the user is the owner of the request or has ROLE_ADMIN or ROLE_MODERATOR
        if ($request->getUserId() !== $user && !$security->isGranted('ROLE_ADMIN') && !$security->isGranted('ROLE_MODERATOR')) {
            return new JsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $request->setIsActive(0); //Set isActive to false (0) to archive the request and hide it from the user's request list.

        $em->flush();

        return new JsonResponse(['status' => 'success', 'message' => 'Request archived successfully'], 200);
    }

    #[OA\Get(
        path: '/failrun/api/get-top-rated-clips/{totalClips}',
        summary: 'Get the N highest rated clips',
        tags: ['Clips'],
        security: [['ApiKey' => []]],
        parameters: [
            new OA\Parameter(name: 'totalClips', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of top rated clips'),
            new OA\Response(response: 401, description: 'Invalid or missing API key'),
        ]
    )]
    #[Route('/failrun/api/get-top-rated-clips/{totalClips}', name: 'app_failrun_api_get_top_rated_clips', methods: ['GET'])]
    public function getTopRatedClips(int $totalClips, EntityManagerInterface $em): JsonResponse
    {
        $sql = "SELECT c.clip_link, u.username, AVG(ur.rate) AS average_rate
                FROM clips c
                JOIN user_rate ur ON c.id = ur.clip_id_id
                JOIN user u ON c.user_id_id = u.id
                WHERE c.clip_status = 1
                GROUP BY c.id, u.username
                ORDER BY average_rate DESC
                LIMIT :totalClips;";

        $result = $em->getConnection()->executeQuery($sql, ['totalClips' => $totalClips], ['totalClips' => \Doctrine\DBAL\ParameterType::INTEGER])->fetchAllAssociative();
        return new JsonResponse(['status' => 'success', 'data' => $result], 200);
    }

    #[OA\Get(
        path: '/failrun/api/get-random-clips/{totalClips}',
        summary: 'Get N random approved clips',
        tags: ['Clips'],
        security: [['ApiKey' => []]],
        parameters: [
            new OA\Parameter(name: 'totalClips', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of random clips'),
            new OA\Response(response: 401, description: 'Invalid or missing API key'),
        ]
    )]
    #[Route('/failrun/api/get-random-clips/{totalClips}', name: 'app_failrun_api_get_random_clips', methods: ['GET'])]
    public function getRandomClips(int $totalClips, EntityManagerInterface $em): JsonResponse
    {
        $sql = "SELECT c.clip_link, u.username
                FROM clips c
                JOIN user u ON c.user_id_id = u.id
                WHERE c.clip_status = 1
                ORDER BY RAND()
                LIMIT :totalClips;";

        $result = $em->getConnection()->executeQuery($sql, ['totalClips' => $totalClips], ['totalClips' => \Doctrine\DBAL\ParameterType::INTEGER])->fetchAllAssociative();
        return new JsonResponse(['status' => 'success', 'data' => $result], 200);
    }

    #[OA\Delete(
        path: '/failrun/api/delete-user-clip/{clipId}',
        summary: 'Delete a clip permanently (owner or moderator only)',
        tags: ['Clips'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'clipId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Clip deleted'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Clip not found'),
        ]
    )]
    #[Route('/failrun/api/delete-user-clip/{clipId}', name: 'app_failrun_api_delete_user_clip', methods: ['DELETE'])]
    public function deleteUserClip(int $clipId, Security $security, EntityManagerInterface $em): JsonResponse
    {
        $user = $security->getUser();
        if (!$user) {
            return new JsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $clip = $em->getRepository(Clips::class)->find($clipId);
        if (!$clip) {
            return new JsonResponse(['status' => 'error', 'message' => 'Clip not found'], 404);
        }

        // Check if the user is the owner of the clip or has ROLE_ADMIN or ROLE_MODERATOR
        if ($clip->getUserId() !== $user && !$security->isGranted('ROLE_ADMIN') && !$security->isGranted('ROLE_MODERATOR')) {
            return new JsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $em->remove($clip);
        $em->flush();

        return new JsonResponse(['status' => 'success', 'message' => 'Clip deleted successfully'], 200);
    }
    /**
     * PUBLIC ENDPOINTS (API KEY REQUIRED)
     */

    #[OA\Get(
        path: '/failrun/api/get-top-rated-clip/{totalClips}',
        summary: 'Get the N highest rated clips (alias)',
        tags: ['Clips'],
        security: [['ApiKey' => []]],
        parameters: [
            new OA\Parameter(name: 'totalClips', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of top rated clips'),
            new OA\Response(response: 401, description: 'Invalid or missing API key'),
        ]
    )]
    #[Route('/failrun/api/get-top-rated-clip/{totalClips}', name: 'app_failrun_api_get_top_rated_clip', methods: ['GET'])]
    public function getTopRatedClip(int $totalClips, EntityManagerInterface $em): JsonResponse
    {
        $sql = "SELECT c.clip_link, u.username, AVG(ur.rate) AS average_rate
                FROM clips c
                JOIN user_rate ur ON c.id = ur.clip_id_id
                JOIN user u ON c.user_id_id = u.id
                WHERE c.clip_status = 1
                GROUP BY c.id, u.username
                ORDER BY average_rate DESC
                LIMIT :totalClips;";

        $result = $em->getConnection()->executeQuery($sql, ['totalClips' => $totalClips], ['totalClips' => \Doctrine\DBAL\ParameterType::INTEGER])->fetchAllAssociative();
        return new JsonResponse(['status' => 'success', 'data' => $result], 200);
    }

    #[OA\Get(
        path: '/failrun/api/get-random-clip/{totalClips}',
        summary: 'Get N random approved clips (alias)',
        tags: ['Clips'],
        security: [['ApiKey' => []]],
        parameters: [
            new OA\Parameter(name: 'totalClips', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of random clips'),
            new OA\Response(response: 401, description: 'Invalid or missing API key'),
        ]
    )]
    #[Route('/failrun/api/get-random-clip/{totalClips}', name: 'app_failrun_api_get_random_clip', methods: ['GET'])]
    public function getRandomClip(int $totalClips, EntityManagerInterface $em): JsonResponse
    {
        $sql = "SELECT c.clip_link, u.username
                FROM clips c
                JOIN user u ON c.user_id_id = u.id
                WHERE c.clip_status = 1
                ORDER BY RAND()
                LIMIT :totalClips;";

        $result = $em->getConnection()->executeQuery($sql, ['totalClips' => $totalClips], ['totalClips' => \Doctrine\DBAL\ParameterType::INTEGER])->fetchAllAssociative();
        return new JsonResponse(['status' => 'success', 'data' => $result], 200);
    }

    #[OA\Get(
        path: '/failrun/api/get-stats',
        summary: 'Get platform statistics (total users and clips)',
        tags: ['Stats'],
        security: [['ApiKey' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Platform stats'),
            new OA\Response(response: 401, description: 'Invalid or missing API key'),
        ]
    )]
    #[Route('/failrun/api/get-stats', name: 'app_failrun_api_get_stats', methods: ['GET'])]
    public function getStats(EntityManagerInterface $em): JsonResponse
    {
        $sql = "SELECT 
                    (SELECT COUNT(*) FROM user) AS total_users,
                    (SELECT COUNT(*) FROM clips) AS total_clips;";

        $result = $em->getConnection()->executeQuery($sql)->fetchAssociative();
        return new JsonResponse(['status' => 'success', 'data' => $result], 200);
    }

    #[OA\Get(
        path: '/failrun/api/get-recent-clips/{totalClips}',
        summary: 'Get the N most recently approved clips',
        tags: ['Clips'],
        security: [['ApiKey' => []]],
        parameters: [
            new OA\Parameter(name: 'totalClips', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of recent clips'),
            new OA\Response(response: 401, description: 'Invalid or missing API key'),
        ]
    )]
    #[Route('/failrun/api/get-recent-clips/{totalClips}', name: 'app_failrun_api_get_recent_clips', methods: ['GET'])]
    public function getRecentClips(int $totalClips, EntityManagerInterface $em): JsonResponse
    {
        $sql = "SELECT c.clip_link, u.username
                FROM clips c
                JOIN user u ON c.user_id_id = u.id
                WHERE c.clip_status = 1
                ORDER BY c.created_at DESC
                LIMIT :totalClips;";

        $result = $em->getConnection()->executeQuery($sql, ['totalClips' => $totalClips], ['totalClips' => \Doctrine\DBAL\ParameterType::INTEGER])->fetchAllAssociative();
        return new JsonResponse(['status' => 'success', 'data' => $result], 200);
    }
}
