<?php

namespace App\Controller;

use App\Entity\Post;
use App\Form\PostFormType;
use App\Service\FileService;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class BlogController extends AbstractController
{
    #[Route('/blog', name: 'blog')]
    public function blog(ManagerRegistry $doctrine): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $posts = $repository->findAll();
        $last = $repository->findLastPosts();

        return $this->render('blog/index.html.twig', [
            'posts' => $posts,
            'recents' => $last
        ]);
    }

    #[Route('/single_post/{slug}', name: 'single_post')]
    public function post(ManagerRegistry $doctrine, $slug): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $post = $repository->findOneBy(["slug"=>$slug]);
        $last = $repository->findLastPosts();
        return $this->render('blog/single_post.html.twig', [
            'post' => $post,
            'recents' => $last
        ]);
    }

    #[Route('/blog/new', name: 'new_post')]
    public function newPost(ManagerRegistry $doctrine, Request $request, SluggerInterface $slugger, FileService $fileService): Response
    {
        if(!$this->getUser())
        {
            return $this->redirectToRoute('login');
        }

        $post = new Post();
        $form = $this->createForm(PostFormType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            // Subir imagen
            $image = $form->get('image')->getData();
            if ($image) {
                $newFile = $fileService->setFileAsImage($image);
                $post->setImage($newFile);
            }

            $post = $form->getData();
            $post->setSlug($slugger->slug($post->getTitle()));
            $post->setUser($this->getUser());
            $post->setNumLikes(0);
            $post->setNumComments(0);

            $entityManager = $doctrine->getManager();
            $entityManager->persist($post);
            $entityManager->flush();

            return $this->redirectToRoute('single_post', ["slug" => $post->getSlug()]);
        }

        return $this->render('blog/new_post.html.twig', array(
            'form' => $form->createView()
        ));
    }

}
