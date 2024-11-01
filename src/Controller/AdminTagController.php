<?php

namespace App\Controller;

use App\Entity\Tag;
use App\Form\TagType;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/tag')]
final class AdminTagController extends AbstractController
{
    #[Route(name: 'app_admin_tag_index', methods: ['GET'])]
    public function index(TagRepository $tagRepository): Response
    {
        return $this->render('admin_tag/index.html.twig', [
            'tags' => array_reverse($tagRepository->findAll()),
            'title' => "CRUD Tags",
        ]);
    }

    #[Route('/new', name: 'app_admin_tag_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $tag = new Tag();
        $form = $this->createForm(TagType::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($tag);
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_tag_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin_tag/new.html.twig', [
            'tag' => $tag,
            'form' => $form,
            'title' => "Nouveau Tag",
        ]);
    }

    #[Route('/{id}', name: 'app_admin_tag_show', methods: ['GET'])]
    public function show(Tag $tag): Response
    {
        return $this->render('admin_tag/show.html.twig', [
            'tag' => $tag,
            'title' => $tag->getTagName(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_tag_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Tag $tag, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TagType::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ($request->request->get('action') === 'delete')
                $entityManager->remove($tag);
            
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_tag_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin_tag/edit.html.twig', [
            'tag' => $tag,
            'form' => $form,
            'title' => "Editer Tag",
        ]);
    }

    #[Route('/{id}', name: 'app_admin_tag_delete', methods: ['POST'])]
    public function delete(Request $request, Tag $tag, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$tag->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($tag);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_admin_tag_index', [], Response::HTTP_SEE_OTHER);
    }
}
