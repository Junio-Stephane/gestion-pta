<?php
// src/Controller/RegistrationController.php

namespace App\Controller;

use App\Entity\Service;
use App\Entity\Direction;
use App\Entity\Personnel;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function signup(EntityManagerInterface $entityManager, Request $request): Response
    {
        // EMPÊCHER L'ACCÈS SI DÉJÀ CONNECTÉ
        $user = $this->getUser();
        if ($user instanceof Personnel) {
            $this->addFlash('error', 
                'Vous êtes déjà connecté en tant que <strong>' . 
                $user->getPrenomPer() . ' ' . $user->getNomPer() . 
                '</strong>. Pour créer un nouveau compte, veuillez vous déconnecter d\'abord.'
            );
            return $this->redirectToRoute('app_dash_board');
        }

        $services = $entityManager->getRepository(Service::class)->findAll();
        $directions = $entityManager->getRepository(Direction::class)->findAll();

        // Récupérer les erreurs de la session
        $errors = $request->getSession()->get('registration_errors', []);
        $request->getSession()->remove('registration_errors');

        // Récupérer les données du formulaire pour les réafficher
        $formData = $request->getSession()->get('registration_form_data', []);
        $request->getSession()->remove('registration_form_data');

        return $this->render('registration/signup.html.twig', [
            'services' => $services,
            'directions' => $directions,
            'errors' => $errors,
            'formData' => $formData
        ]);
    }

    #[Route('/check/immatricule', name: 'app_check_immatricule', methods: ['POST'])]
    public function checkImmatricule(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $imPer = $data['im_per'] ?? '';

        // Vérifier le format
        if (!preg_match('/^\d{6}$/', $imPer)) {
            return $this->json(['exists' => false, 'valid' => false]);
        }

        // Vérifier si l'immatricule existe
        $existingIm = $em->getRepository(Personnel::class)->find($imPer);
        
        return $this->json([
            'exists' => $existingIm !== null,
            'valid' => true
        ]);
    }

    #[Route('/check/email', name: 'app_check_email', methods: ['POST'])]
    public function checkEmail(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email_per'] ?? '';

        // Vérifier le format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['exists' => false, 'valid' => false]);
        }

        // Vérifier si l'email existe
        $existingEmail = $em->getRepository(Personnel::class)->findOneBy(['EmailPer' => $email]);
        
        return $this->json([
            'exists' => $existingEmail !== null,
            'valid' => true
        ]);
    }

    #[Route('/register/submit', name: 'app_register_submit', methods: ['POST'])]
    public function submit(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        NotificationService $notificationService
    ): Response {
        date_default_timezone_set('Indian/Antananarivo');
        // EMPÊCHER L'ACCÈS SI DÉJÀ CONNECTÉ
        if ($this->getUser()) {
            $this->addFlash('error', 
                'Vous êtes déjà connecté. Pour créer un nouveau compte, veuillez vous déconnecter d\'abord.'
            );
            return $this->redirectToRoute('app_dash_board');
        }

        $data = $request->request->all();
        $errors = [];

        // Sauvegarder les données du formulaire pour les réafficher en cas d'erreur
        $request->getSession()->set('registration_form_data', $data);

        // Validation des données obligatoires
        if (empty($data['im_per'])) {
            $errors['im_per'] = 'L\'immatricule est obligatoire';
        }

        if (empty($data['nom_per'])) {
            $errors['nom_per'] = 'Le nom est obligatoire';
        }

        if (empty($data['prenom_per'])) {
            $errors['prenom_per'] = 'Le prénom est obligatoire';
        }

        if (empty($data['email_per'])) {
            $errors['email_per'] = 'L\'email est obligatoire';
        }

        if (empty($data['mdp_per'])) {
            $errors['mdp_per'] = 'Le mot de passe est obligatoire';
        }

        // Si erreurs de base, rediriger
        if (!empty($errors)) {
            $request->getSession()->set('registration_errors', $errors);
            return $this->redirectToRoute('app_register');
        }

        // Validation du format de l'immatricule (6 chiffres)
        if (!preg_match('/^\d{6}$/', $data['im_per'])) {
            $errors['im_per'] = 'L\'immatricule doit contenir exactement 6 chiffres';
        }

        // Validation du format de l'email
        if (!filter_var($data['email_per'], FILTER_VALIDATE_EMAIL)) {
            $errors['email_per'] = 'L\'adresse email n\'est pas valide';
        }

        // Validation de la longueur du mot de passe
        if (strlen($data['mdp_per']) < 6) {
            $errors['mdp_per'] = 'Le mot de passe doit contenir au moins 6 caractères';
        }

        // Validation de la complexité du mot de passe
        if (!preg_match('/[a-z]/', $data['mdp_per'])) {
            $errors['mdp_per'] = 'Le mot de passe doit contenir au moins une lettre minuscule';
        } elseif (!preg_match('/[A-Z]/', $data['mdp_per'])) {
            $errors['mdp_per'] = 'Le mot de passe doit contenir au moins une lettre majuscule';
        } elseif (!preg_match('/[0-9]/', $data['mdp_per'])) {
            $errors['mdp_per'] = 'Le mot de passe doit contenir au moins un chiffre';
        } elseif (!preg_match('/[@$!%*?&]/', $data['mdp_per'])) {
            $errors['mdp_per'] = 'Le mot de passe doit contenir au moins un caractère spécial (@$!%*?&)';
        }

        // Vérification de la correspondance des mots de passe
        if ($data['mdp_per'] !== $data['confirm_mdp_per']) {
            $errors['password_mismatch'] = 'Les mots de passe ne correspondent pas';
        }

        // Vérifier si l'utilisateur existe déjà par email
        $existingUser = $em->getRepository(Personnel::class)->findOneBy(['EmailPer' => $data['email_per']]);
        if ($existingUser) {
            $errors['email_per'] = 'Cet email est déjà utilisé';
        }

        // Vérifier si l'immatricule existe déjà
        $existingIm = $em->getRepository(Personnel::class)->find($data['im_per']);
        if ($existingIm) {
            $errors['im_per'] = 'Cet immatricule est déjà utilisé';
        }

        // Si erreurs de validation, rediriger
        if (!empty($errors)) {
            $request->getSession()->set('registration_errors', $errors);
            return $this->redirectToRoute('app_register');
        }

        // Créer le nouvel utilisateur
        $user = new Personnel();
        $user->setImPer($data['im_per']);
        $user->setNomPer($data['nom_per']);
        $user->setPrenomPer($data['prenom_per'] ?? '');
        $user->setEmailPer($data['email_per']);
        $user->setTelPer($data['tel_per'] ?? '');
        
        // Gestion du service
        if (!empty($data['code_service'])) {
            $service = $em->getRepository(Service::class)->find($data['code_service']);
            if ($service) {
                $user->setService($service);
            }
        }

        // Gestion de la direction
        // if (!empty($data['code_direction'])) {
        //     $direction = $em->getRepository(Direction::class)->find($data['code_direction']);
        //     if ($direction) {
        //         $user->setDirectionD($direction);
        //     }
        // }
        
        // Hasher le mot de passe
        $hashedPassword = $passwordHasher->hashPassword($user, $data['mdp_per']);
        $user->setMdpPer($hashedPassword);
        
        // Statut par défaut - en attente de validation
        $user->setStatutPer('EN ATTENTE');
        $user->setIsValidated(false);
        $user->setRolePer('ROLE_EN_ATTENTE');

        try {
            // Sauvegarder l'utilisateur
            $em->persist($user);
            $em->flush();

            // CRÉER LA NOTIFICATION POUR L'ADMIN
            $notificationService->notifyNewRegistration($user);

            // STOCKER L'EMAIL POUR LA PAGE DE CONFIRMATION
            $request->getSession()->set('registered_email', $user->getEmailPer());

            // NETTOYER LES DONNÉES DE SESSION
            $request->getSession()->remove('registration_form_data');

            // REDIRECTION VERS LA PAGE DE CONFIRMATION
            return $this->redirectToRoute('app_registration_confirmation');

        } catch (\Exception $e) {
            $this->addFlash('error', 
                'Une erreur est survenue lors de l\'inscription. ' .
                'Veuillez réessayer. Erreur: ' . $e->getMessage()
            );
            return $this->redirectToRoute('app_register');
        }
    }

    

    #[Route('/register/confirmation', name: 'app_registration_confirmation')]
    public function confirmation(Request $request): Response
    {
        // Vérifier qu'un email est stocké en session (protection)
        $registeredEmail = $request->getSession()->get('registered_email');
        
        if (!$registeredEmail) {
            // Rediriger vers l'inscription si pas d'email en session
            return $this->redirectToRoute('app_register');
        }

        // Nettoyer la session après affichage
        $request->getSession()->remove('registered_email');

        return $this->render('registration/confirmation.html.twig', [
            'registered_email' => $registeredEmail
        ]);
    }
}