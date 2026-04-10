<?php

namespace App\Controller;

use App\Entity\Clips;
use App\Entity\Games;
use App\Entity\Mark;
use App\Entity\MarkType;
use App\Entity\User;
use App\Entity\UserRate;
use App\Entity\UserRequest;
use App\Form\Admin\UserAdminType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\UserPasswordHasherInterface;
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
     * 
     * Redirige automáticamente al panel si el usuario ya está autenticado como admin.
     * 
     * @param AuthenticationUtils $authenticationUtils Servicio para obtener errores de autenticación
     * @return Response Vista de login o redirección al panel
     */
    #[Route('/failrun/admin/login', name: 'app_failrun_admin_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Si ya es admin, redirige al panel
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_failrun_admin_panel');
        }

        // Obtiene el último error de autenticación (si lo hay)
        $error = $authenticationUtils->getLastAuthenticationError();
        // Obtiene el último usuario introducido en el formulario
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('failrun_admin_panel/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    /**
     * Panel principal del administrador
     * 
     * Carga todas las entidades del sistema y las muestra en vistas tabulares
     * con funcionalidades de búsqueda, filtrado y ordenamiento.
     * 
     * @param EntityManagerInterface $em Manager de Doctrine para acceder a repositorios
     * @return Response Vista del panel con todos los datos
     */
    #[Route('/failrun/admin/panel', name: 'app_failrun_admin_panel')]
    public function index(EntityManagerInterface $em): Response
    {
        // Verifica que el usuario tenga permisos de administrador
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Obtiene todos los registros de cada entidad
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
     * Obtiene un registro específico en formato JSON
     * 
     * Ruta: /failrun/admin/entity/{entity}/{id}
     * Ejemplo: /failrun/admin/entity/users/5
     * 
     * @param string $entity Nombre de la entidad (users, clips, games, etc.)
     * @param int $id ID del registro a obtener
     * @param EntityManagerInterface $em Manager de Doctrine
     * @return JsonResponse Datos del registro en JSON
     */
    #[Route('/failrun/admin/entity/{entity}/{id}', name: 'app_failrun_admin_get_entity', methods: ['GET'])]
    public function getEntity(string $entity, int $id, EntityManagerInterface $em): JsonResponse
    {
        // Verifica permisos de admin
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Mapea nombres de entidades a clases
        $entityMap = [
            'users'         => User::class,
            'clips'         => Clips::class,
            'games'         => Games::class,
            'marks'         => Mark::class,
            'mark_types'    => MarkType::class,
            'user_rates'    => UserRate::class,
            'user_requests' => UserRequest::class,
        ];

        // Valida que la entidad exista
        if (!isset($entityMap[$entity])) {
            return $this->json(['error' => 'Entidad no válida'], 400);
        }

        // Obtiene el registro
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
                'id' => $record->getId(),
                'username' => $record->getUsername(),
                'email' => $record->getEmail(),
                'profilePic' => $record->getProfilePic(),
                'role' => $role,
            ]);
        }

        // Convierte el objeto a array (simplificado para entidades básicas)
        return $this->json($this->entityToArray($record));
    }

    /**
     * Crea o actualiza un registro
     * 
     * Ruta: /failrun/admin/entity/{entity}
     * POST: Crear nuevo
     * PUT: Actualizar existente (requiere 'id' en el body)
     * 
     * @param string $entity Nombre de la entidad
     * @param Request $request Contiene los datos del formulario
     * @param EntityManagerInterface $em Manager de Doctrine
     * @return JsonResponse Respuesta con el resultado de la operación
     */
    #[Route('/failrun/admin/entity/{entity}', name: 'app_failrun_admin_save_entity', methods: ['POST', 'PUT'])]
    public function saveEntity(
        string $entity,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse
    {
        // Verifica permisos de admin
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Mapea nombres de entidades a clases
        $entityMap = [
            'users'         => User::class,
            'clips'         => Clips::class,
            'games'         => Games::class,
            'marks'         => Mark::class,
            'mark_types'    => MarkType::class,
            'user_rates'    => UserRate::class,
            'user_requests' => UserRequest::class,
        ];

        // Valida que la entidad exista
        if (!isset($entityMap[$entity])) {
            return $this->json(['error' => 'Entidad no válida'], 400);
        }

        // Obtiene los datos del request (JSON o form data)
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            $data = $request->request->all();
        }

        if ($entity === 'users') {
            return $this->saveUserEntity($data, $em, $passwordHasher);
        }

        try {
            // Determina si es una actualización o creación
            $isUpdate = isset($data['id']) && !empty($data['id']);
            $className = $entityMap[$entity];

            if ($isUpdate) {
                // Obtiene el registro existente
                $record = $em->getRepository($className)->find($data['id']);
                if (!$record) {
                    return $this->json(['error' => 'Registro no encontrado'], 404);
                }
            } else {
                // Crea una nueva instancia
                $record = new $className();
            }

            // Asigna los datos al objeto (método genérico simple)
            // En producción, considera usar un DTO/FormType propio de Symfony
            foreach ($data as $key => $value) {
                if ($key !== 'id') {
                    // Convierte nombres camelCase a setters (ej: 'userName' -> 'setUserName')
                    $setter = 'set' . str_replace('_', '', ucwords($key, '_'));
                    if (method_exists($record, $setter)) {
                        $record->$setter($value);
                    }
                }
            }

            // Persiste el registro
            $em->persist($record);
            $em->flush();

            return $this->json([
                'success' => true,
                'message' => $isUpdate ? 'Registro actualizado correctamente' : 'Registro creado correctamente',
                'id' => $record->getId() ?? null
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crea o actualiza usuarios usando Symfony Forms y hasheo seguro de contraseña.
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

            $form = $this->createForm(UserAdminType::class, $user, [
                'csrf_protection' => false,
                'is_edit' => $isUpdate,
            ]);

            // En edición no se vacían campos no enviados; en creación sí se exige payload completo.
            $form->submit($data, !$isUpdate);

            if (!$form->isValid()) {
                return $this->json([
                    'success' => false,
                    'error' => implode(' | ', $this->collectFormErrors($form)),
                ], 400);
            }

            $role = (string) $form->get('role')->getData();
            if ($role === 'ROLE_ADMIN') {
                $user->setRoles(['ROLE_ADMIN']);
            } elseif ($role === 'ROLE_MODERATOR') {
                $user->setRoles(['ROLE_MODERATOR']);
            } else {
                $user->setRoles([]);
            }

            $plainPassword = (string) $form->get('plainPassword')->getData();
            if (!$isUpdate && $plainPassword === '') {
                return $this->json([
                    'success' => false,
                    'error' => 'La contraseña es obligatoria al crear un usuario',
                ], 400);
            }

            if ($plainPassword !== '') {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            $em->persist($user);
            $em->flush();

            return $this->json([
                'success' => true,
                'message' => $isUpdate ? 'Usuario actualizado correctamente' : 'Usuario creado correctamente',
                'id' => $user->getId(),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function collectFormErrors($form): array
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }

        return array_values(array_unique($errors));
    }

    /**
     * Elimina un registro
     * 
     * Ruta: /failrun/admin/entity/{entity}/{id}
     * DELETE: Elimina el registro con ese ID
     * 
     * @param string $entity Nombre de la entidad
     * @param int $id ID del registro a eliminar
     * @param EntityManagerInterface $em Manager de Doctrine
     * @return JsonResponse Respuesta con el resultado de la operación
     */
    #[Route('/failrun/admin/entity/{entity}/{id}', name: 'app_failrun_admin_delete_entity', methods: ['DELETE'])]
    public function deleteEntity(string $entity, int $id, EntityManagerInterface $em): JsonResponse
    {
        // Verifica permisos de admin
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Mapea nombres de entidades a clases
        $entityMap = [
            'users'         => User::class,
            'clips'         => Clips::class,
            'games'         => Games::class,
            'marks'         => Mark::class,
            'mark_types'    => MarkType::class,
            'user_rates'    => UserRate::class,
            'user_requests' => UserRequest::class,
        ];

        // Valida que la entidad exista
        if (!isset($entityMap[$entity])) {
            return $this->json(['error' => 'Entidad no válida'], 400);
        }

        try {
            // Obtiene el registro
            $record = $em->getRepository($entityMap[$entity])->find($id);

            if (!$record) {
                return $this->json(['error' => 'Registro no encontrado'], 404);
            }

            // Elimina el registro
            $em->remove($record);
            $em->flush();

            return $this->json([
                'success' => true,
                'message' => 'Registro eliminado correctamente'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cierra la sesión del administrador
     * 
     * Symfony maneja automáticamente la eliminación de la sesión.
     */
    #[Route('/failrun/admin/logout', name: 'app_failrun_admin_logout')]
    public function logout(): void {}

    /**
     * Método auxiliar para convertir una entidad a array
     * 
     * Utiliza reflexión para obtener las propiedades públicas y sus getters.
     * 
     * @param object $entity Objeto entidad a convertir
     * @return array Array con los datos de la entidad
     */
    private function entityToArray(object $entity): array
    {
        $result = [];
        $reflection = new \ReflectionClass($entity);

        // Obtiene todas las propiedades de la entidad
        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($entity);

            // Obtiene el nombre de la propiedad
            $propertyName = $property->getName();

            // Si el valor es un objeto, obtiene su ID o string
            if (is_object($value) && method_exists($value, 'getId')) {
                $result[$propertyName] = $value->getId();
            } else {
                $result[$propertyName] = $value;
            }
        }

        return $result;
    }
}