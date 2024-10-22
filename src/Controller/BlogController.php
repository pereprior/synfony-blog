<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Post;
use App\Form\CommentFormType;
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
    public function post(ManagerRegistry $doctrine, Request $request, $slug): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $commentRepository = $doctrine->getRepository(Comment::class);
        $post = $repository->findOneBy(["slug"=>$slug]);
        $recents = $repository->findLastPosts();
        $comment = new Comment();
        $form = $this->createForm(CommentFormType::class, $comment);
        $form->handleRequest($request);
        $comments = $commentRepository->findAll();

        if ($form->isSubmitted() && $form->isValid()) {
            $comment = $form->getData();
            $comment->setPost($post);
            //Aumentamos en 1 el número de comentarios del post
            $post->setNumComments($post->getNumComments() + 1);
            $entityManager = $doctrine->getManager();
            $entityManager->persist($comment);
            $entityManager->flush();
            return $this->redirectToRoute('single_post', ["slug" => $post->getSlug()]);
        }
        return $this->render('blog/single_post.html.twig', [
            'post' => $post,
            'recents' => $recents,
            'commentForm' => $form->createView(),
            'comments' => $comments
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

    #[Route('/single_post/{slug}/like', name: 'post_like')]
    public function like(ManagerRegistry $doctrine, $slug): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $post = $repository->findOneBy(["slug"=>$slug]);

        if ($post){
            $post->like();
            $entityManager = $doctrine->getManager();
            $entityManager->persist($post);
            $entityManager->flush();
        }
        return $this->redirectToRoute('single_post', ["slug" => $post->getSlug()]);

    }

    #[Route('/blog/buscar', name: 'blog_buscar')]
    public function buscar(ManagerRegistry $doctrine,  Request $request): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $searchTerm = $request->query->get('searchTerm', '');
        $posts = $repository->findByText($searchTerm);
        $recents = $repository->findLastPosts();
        return $this->render('blog/index.html.twig', [
            'posts' => $posts,
            'recents' => $recents,
            'searchTerm' => $searchTerm
        ]);
    }

}
