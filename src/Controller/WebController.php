<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WebController extends AbstractController
{
    #[Route('/login', name: 'web_login', methods: ['GET'])]
    public function loginPage(): Response
    {
        return $this->render('auth/login.html.twig');
    }

    #[Route('/register', name: 'web_register', methods: ['GET'])]
    public function registerPage(): Response
    {
        return $this->render('auth/register.html.twig');
    }

    #[Route('/app', name: 'web_app', methods: ['GET'])]
    public function appPage(): Response
    {
        return $this->render('app/index.html.twig');
    }

    #[Route('/rooms/{id}', name: 'web_room_view', methods: ['GET'])]
    public function roomViewPage(int $id): Response
    {
        return $this->render('rooms/view.html.twig', ['roomId' => $id]);
    }

    #[Route('/admin/users', name: 'web_admin_users', methods: ['GET'])]
    public function adminUsersPage(): Response
    {
        return $this->render('admin/users.html.twig');
    }
}
