<?php

namespace App\Controller;

use App\Controller\BaseController;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends BaseController {

  public function __construct(ManagerRegistry $doctrine) {
    parent::__construct($doctrine);
  }

  #[Route('/logonscreen', name: 'app_login')]
  public function index(AuthenticationUtils $authenticationUtils): Response {

    $error = $authenticationUtils->getLastAuthenticationError();
    $lastUsername = $authenticationUtils->getLastUsername();

    return $this->render('logon_screen.twig', [
      'controller_name' => 'LoginController',
      'pageTitle' => 'Stats Logon',
      'pageHeader' => 'Please log on!',
      'error' => $error,
      'last_username' => $lastUsername ]);
  }

  #[Route('/logout', name: 'app_logout', methods: ['GET'])]
  public function logout() {
  }
}
