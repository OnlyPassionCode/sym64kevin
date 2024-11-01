<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\Admin\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/user')]
final class AdminUserController extends AbstractController{

    public function __construct(
        private UserPasswordHasherInterface $hasher
    ) {
    }

    #[Route(name: 'app_admin_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('admin_user/index.html.twig', [
            'users' => array_reverse($userRepository->findAll()),
            'title' => "CRUD Users",
        ]);
    }

    #[Route('/new', name: 'app_admin_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $pwdHash = $this->hasher->hashPassword($user, $user->getPassword());
            $user->setPassword($pwdHash);
            $user->setUniqid(uniqid('', true));
            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin_user/new.html.twig', [
            'user' => $user,
            'form' => $form,
            'title' => "Nouveau User",
        ]);
    }

    #[Route('/{id}/edit/activate', name: 'app_admin_user_edit_activate', methods: ['POST'])]
    public function editActivate(Request $request, User $user, EntityManagerInterface $entityManager): JsonResponse
    {
        $checked = $request->request->get('checked');
        $boolean = filter_var($checked, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if(!is_bool($boolean))
            throw new BadRequestException("The checked key must be a boolean !");
        
        $user->setActivate($boolean);
        $entityManager->flush();

        return $this->json([
            'checked' => $checked,
            'id' => $user->getId()
        ]);
    }

    #[Route('/{id}', name: 'app_admin_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('admin_user/show.html.twig', [
            'user' => $user,
            'title' => 'User ' . $user->getUsername(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($request->request->get('action') === 'delete')
                $entityManager->remove($user);
            
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin_user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
            'title' => 'Editer User',
        ]);
    }

    #[Route('/{id}', name: 'app_admin_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_admin_user_index', [], Response::HTTP_SEE_OTHER);
    }
}
