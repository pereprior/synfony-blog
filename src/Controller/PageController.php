<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Contact;
use App\Form\ContactFormType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PageController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(ManagerRegistry $doctrine): Response
    {
        $repository = $doctrine->getRepository(Category::class);
        $categories = $repository->findAll();

        return $this->render('page/index.html.twig', ['categories' => $categories]);
    }

    #[Route('/about', name: 'about')]
    public function about(): Response
    {
        return $this->render('page/about.html.twig');
    }

    #[Route('/contact', name: 'contact')]
    public function contact(ManagerRegistry $doctrine, Request $request): Response
    {
        $form = $this->createForm(ContactFormType::class, new Contact());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contact = $form->getData();

            $entityManager = $doctrine->getManager();
            $entityManager->persist($contact);
            $entityManager->flush();

            return $this->redirectToRoute('contact_sent');
        }

        return $this->render('page/contact.html.twig', array(
            'form' => $form->createView()
        ));
    }

    #[Route('/contact_sent', name: 'contact_sent')]
    public function contactSent(): Response
    {
        return $this->render('page/contact_sent.html.twig');
    }

}
