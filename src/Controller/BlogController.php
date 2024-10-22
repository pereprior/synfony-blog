<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Post;
use App\Form\CommentFormType;
use App\Form\PostFormType;
use App\Repository\PostRepository;
use App\Service\FileService;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class BlogController extends AbstractController
{
    private PostRepository $repository;
    private array $posts;
    private array $lastFivePosts;

    public function __construct(ManagerRegistry $doctrine)
    {
        $repository = $doctrine->getRepository(Post::class);

        $this->repository = $repository;
        $this->posts = $repository->findAll();
        $this->lastFivePosts = $repository->findLastPosts();
    }

    #[Route('/blog', name: 'blog')]
    public function blog(ManagerRegistry $doctrine): Response
    {
        return $this->render('blog/index.html.twig', [
            'posts' => $this->posts,
            'recents' => $this->lastFivePosts
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

    #[Route('/single_post/{slug}', name: 'single_post')]
    public function post(ManagerRegistry $doctrine, Request $request, $slug): Response
    {
        $post = $this->repository->findOneBy(["slug"=>$slug]);

        $commentRepository = $doctrine->getRepository(Comment::class);
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
            'recents' => $this->lastFivePosts,
            'commentForm' => $form->createView(),
            'comments' => $comments
        ]);
    }

    #[Route('/single_post/{slug}/like', name: 'post_like')]
    public function like(ManagerRegistry $doctrine, $slug): Response
    {
        $post = $this->repository->findOneBy(["slug"=>$slug]);

        if ($post){
            $post->like();
            $entityManager = $doctrine->getManager();
            $entityManager->persist($post);
            $entityManager->flush();
        }
        return $this->redirectToRoute('single_post', ["slug" => $post->getSlug()]);

    }

    #[Route('/blog/buscar', name: 'blog_buscar')]
    public function buscar(ManagerRegistry $doctrine, Request $request): Response
    {
        $searchTerm = $request->query->get('searchTerm', '');
        $posts = $this->repository->findByText($searchTerm);

        return $this->render('blog/index.html.twig', [
            'posts' => $posts,
            'recents' => $this->lastFivePosts,
            'searchTerm' => $searchTerm
        ]);
    }

}
