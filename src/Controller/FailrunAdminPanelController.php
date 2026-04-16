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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Controlador del panel administrativo de Failrun
 * 
 * Gestiona la autenticación, visualización y operaciones CRUD
 * en todas las entidades del sistema.
 */
final class FailrunAdminPanelController extends AbstractController
{
    /**
     * Página de login para administradores
     */
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

    /**
     * Panel principal del administrador
     */
    #[Route('/failrun/admin/panel', name: 'app_failrun_admin_panel')]
    public function index(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('failrun_admin_panel/index.html.twig', [
            'users'         => $em->getRepository(User::class)->findAll(),
            'clips'         => $em->getRepository(Clips::class)->findAll(),
            'games'         => $em->getRepository(Games::class)->findAll(),
            'marks'         => $em->getRepository(Mark::class)->findAll(),
            'mark_types'    => $em->getRepository(MarkType::class)->findAll(),
            'user_rates'    => $em->getRepository(UserRate::class)->findAll(),
            'user_requests' => $em->getRepository(UserRequest::class)->findAll(),
        ]);
    }

    /**
     * Vista de detalle de un registro en página completa.
     * 
     * Disponible para: clips, games, marks, mark_types, user_rates, user_requests.
     * NO disponible para users (redirige al panel).
     * 
     * Ruta: /failrun/admin/entity/{entity}/{id}/view
     * IMPORTANTE: esta ruta debe declararse ANTES de app_failrun_admin_get_entity
     * para que Symfony no confunda "/view" con un {id} entero.
     */
    #[Route('/failrun/admin/entity/{entity}/{id}/view', name: 'app_failrun_admin_view_entity')]
    public function viewEntityPage(string $entity, int $id, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Los usuarios no tienen vista de detalle
        if ($entity === 'users') {
            return $this->redirectToRoute('app_failrun_admin_panel');
        }

        $entityMap = [
            'clips'         => Clips::class,
            'games'         => Games::class,
            'marks'         => Mark::class,
            'mark_types'    => MarkType::class,
            'user_rates'    => UserRate::class,
            'user_requests' => UserRequest::class,
        ];

        if (!isset($entityMap[$entity])) {
            throw $this->createNotFoundException('Entidad no válida: ' . $entity);
        }

        $record = $em->getRepository($entityMap[$entity])->find($id);

        if (!$record) {
            throw $this->createNotFoundException('Registro no encontrado con ID: ' . $id);
        }

        return $this->render('failrun_admin_panel/view.html.twig', [
            'entity' => $entity,
            'record' => $record,
        ]);
    }

    /**
     * Obtiene un registro específico en formato JSON.
     * 
     * Ruta: /failrun/admin/entity/{entity}/{id}
     */
    #[Route('/failrun/admin/entity/{entity}/{id}', name: 'app_failrun_admin_get_entity', methods: ['GET'])]
    public function getEntity(string $entity, int $id, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $entityMap = [
            'users'         => User::class,
            'clips'         => Clips::class,
            'games'         => Games::class,
            'marks'         => Mark::class,
            'mark_types'    => MarkType::class,
            'user_rates'    => UserRate::class,
            'user_requests' => UserRequest::class,
        ];

        if (!isset($entityMap[$entity])) {
            return $this->json(['error' => 'Entidad no válida'], 400);
        }

        $record = $em->getRepository($entityMap[$entity])->find($id);

        if (!$record) {
            return $this->json(['error' => 'Registro no encontrado'], 404);
        }

        if ($entity === 'users' && $record instanceof User) {
            $roles = $record->getRoles();
            $role = 'ROLE_USER';

            if (in_array('ROLE_ADMIN', $roles, true)) {
                $role = 'ROLE_ADMIN';
            } elseif (in_array('ROLE_MODERATOR', $roles, true)) {
                $role = 'ROLE_MODERATOR';
            }

            return $this->json([
                'id'         => $record->getId(),
                'username'   => $record->getUsername(),
                'email'      => $record->getEmail(),
                'profilePic' => $record->getProfilePic(),
                'role'       => $role,
            ]);
        }

        return $this->json($this->entityToArray($record));
    }

    /**
     * Crea o actualiza un registro.
     * 
     * POST: Crear nuevo  |  PUT: Actualizar existente (requiere 'id' en el body)
     */
    #[Route('/failrun/admin/entity/{entity}', name: 'app_failrun_admin_save_entity', methods: ['POST', 'PUT'])]
    public function saveEntity(
        string $entity,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $entityMap = [
            'users'         => User::class,
            'clips'         => Clips::class,
            'games'         => Games::class,
            'marks'         => Mark::class,
            'mark_types'    => MarkType::class,
            'user_rates'    => UserRate::class,
            'user_requests' => UserRequest::class,
        ];

        if (!isset($entityMap[$entity])) {
            return $this->json(['error' => 'Entidad no válida'], 400);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            $data = $request->request->all();
        }

        if ($entity === 'users') {
            return $this->saveUserEntity($data, $em, $passwordHasher);
        }

        try {
            $isUpdate = isset($data['id']) && !empty($data['id']);
            $className = $entityMap[$entity];

            if ($isUpdate) {
                $record = $em->getRepository($className)->find($data['id']);
                if (!$record) {
                    return $this->json(['error' => 'Registro no encontrado'], 404);
                }
            } else {
                $record = new $className();
            }

            foreach ($data as $key => $value) {
                if ($key !== 'id') {
                    $setter = 'set' . str_replace('_', '', ucwords($key, '_'));
                    if (method_exists($record, $setter)) {
                        $record->$setter($value);
                    }
                }
            }

            $em->persist($record);
            $em->flush();

            return $this->json([
                'success' => true,
                'message' => $isUpdate ? 'Registro actualizado correctamente' : 'Registro creado correctamente',
                'id'      => $record->getId() ?? null,
            ]);

        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Crea o actualiza usuarios con hasheo seguro de contraseña.
     */
    private function saveUserEntity(
        array $data,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        try {
            $isUpdate = isset($data['id']) && !empty($data['id']);

            if ($isUpdate) {
                $user = $em->getRepository(User::class)->find($data['id']);
                if (!$user instanceof User) {
                    return $this->json(['error' => 'Registro no encontrado'], 404);
                }
            } else {
                $user = new User();
                $user->setRegisterDate(new \DateTime());
            }

            if (!$isUpdate) {
                if (empty($data['username'])) {
                    return $this->json(['success' => false, 'error' => 'El username es obligatorio'], 400);
                }
                if (empty($data['email'])) {
                    return $this->json(['success' => false, 'error' => 'El email es obligatorio'], 400);
                }
                if (empty($data['plainPassword'])) {
                    return $this->json(['success' => false, 'error' => 'La contraseña es obligatoria al crear un usuario'], 400);
                }
            }

            if (!empty($data['username']))  $user->setUsername($data['username']);
            if (!empty($data['email']))     $user->setEmail($data['email']);
            if (isset($data['profilePic'])) $user->setProfilePic($data['profilePic']);

            $role = $data['role'] ?? 'ROLE_USER';
            if ($role === 'ROLE_ADMIN') {
                $user->setRoles(['ROLE_ADMIN']);
            } elseif ($role === 'ROLE_MODERATOR') {
                $user->setRoles(['ROLE_MODERATOR']);
            } else {
                $user->setRoles([]);
            }

            $plainPassword = $data['plainPassword'] ?? '';
            if ($plainPassword !== '') {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            $em->persist($user);
            $em->flush();

            return $this->json([
                'success' => true,
                'message' => $isUpdate ? 'Usuario actualizado correctamente' : 'Usuario creado correctamente',
                'id'      => $user->getId(),
            ]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Elimina un registro.
     */
    #[Route('/failrun/admin/entity/{entity}/{id}', name: 'app_failrun_admin_delete_entity', methods: ['DELETE'])]
    public function deleteEntity(string $entity, int $id, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $entityMap = [
            'users'         => User::class,
            'clips'         => Clips::class,
            'games'         => Games::class,
            'marks'         => Mark::class,
            'mark_types'    => MarkType::class,
            'user_rates'    => UserRate::class,
            'user_requests' => UserRequest::class,
        ];

        if (!isset($entityMap[$entity])) {
            return $this->json(['error' => 'Entidad no válida'], 400);
        }

        try {
            $record = $em->getRepository($entityMap[$entity])->find($id);

            if (!$record) {
                return $this->json(['error' => 'Registro no encontrado'], 404);
            }

            $em->remove($record);
            $em->flush();

            return $this->json(['success' => true, 'message' => 'Registro eliminado correctamente']);

        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Cierra la sesión del administrador.
     */
    #[Route('/failrun/admin/logout', name: 'app_failrun_admin_logout')]
    public function logout(): void {}

    /**
     * Convierte una entidad a array usando reflexión.
     */
    private function entityToArray(object $entity): array
    {
        $result = [];
        $reflection = new \ReflectionClass($entity);

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($entity);
            $propertyName = $property->getName();

            if (is_object($value) && method_exists($value, 'getId')) {
                $result[$propertyName] = $value->getId();
            } else {
                $result[$propertyName] = $value;
            }
        }

        return $result;
    }
}