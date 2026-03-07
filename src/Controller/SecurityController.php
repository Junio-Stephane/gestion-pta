<?php
// src/Controller/SecurityController.php

namespace App\Controller;

use App\Entity\Personnel;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard_par_role');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        $message = null;
        if ($error && $error->getMessageKey() === 'Invalid credentials.') {
            $message = "Email ou mot de passe incorrect.";
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'message' => $message,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/forgot-password', name: 'app_forgot_password')]
    public function forgotPassword(Request $request, EntityManagerInterface $em, EmailService $emailService): Response
    {
        date_default_timezone_set('Indian/Antananarivo');

        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard_par_role');
        }

        $error = null;
        $success = null;

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');

            if (empty($email)) {
                $error = 'Veuillez saisir votre adresse email.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'L\'adresse email n\'est pas valide.';
            } else {
                $user = $em->getRepository(Personnel::class)->findOneBy(['EmailPer' => $email]);

                if (!$user) {
                    $success = 'Si votre email est enregistré, vous recevrez un lien de réinitialisation.';
                } else {
                    // Générer un token de réinitialisation
                    $token = bin2hex(random_bytes(32));
                    $user->setResetToken($token);
                    $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));

                    $em->flush();

                    try {
                        $emailSent = $emailService->sendPasswordResetEmail($user);
                        if ($emailSent) {
                            $success = ' Si votre adresse email est reconnue, un lien de réinitialisation vous sera envoyé.';
                        } else {
                            $error = 'Une erreur est survenue lors de l\'envoi de l\'email. Veuillez réessayer.';
                            // Annuler le token en cas d'erreur d'envoi
                            $user->setResetToken(null);
                            $user->setResetTokenExpiresAt(null);
                            $em->flush();
                        }
                    } catch (\Exception $e) {
                        $error = 'Une erreur est survenue lors de l\'envoi de l\'email. Veuillez réessayer.';

                        $user->setResetToken(null);
                        $user->setResetTokenExpiresAt(null);
                        $em->flush();
                    }
                }
            }
        }

        return $this->render('security/forgot_password.html.twig', [
            'error' => $error,
            'success' => $success,
        ]);
    }

    #[Route('/reset-password/success', name: 'app_reset_password_success')]
    public function resetPasswordSuccess(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard_par_role');
        }

        return $this->render('security/reset_password_success.html.twig');
    }

    #[Route('/reset-password/error', name: 'app_reset_password_error')]
    public function resetPasswordError(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard_par_role');
        }

        return $this->render('security/reset_password_error.html.twig');
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password')]
    public function resetPassword(string $token, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard_par_role');
        }

        $user = $em->getRepository(Personnel::class)->findOneBy([
            'resetToken' => $token,
        ]);

        if (!$user) {
            $this->addFlash('error', 'Lien de réinitialisation invalide ou déjà utilisé.');
            return $this->redirectToRoute('app_reset_password_error');
        }

        if ($user->isResetTokenExpired()) {
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);
            $em->flush();

            $this->addFlash('error', 'Le lien de réinitialisation a expiré. Veuillez faire une nouvelle demande.');
            return $this->redirectToRoute('app_reset_password_error');
        }

        if ($user->getResetToken() === null) {
            $this->addFlash('error', 'Ce lien de réinitialisation a déjà été utilisé.');
            return $this->redirectToRoute('app_reset_password_error');
        }

        $errors = [];
        $formData = [];

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');

            $formData = ['password' => $password, 'confirm_password' => $confirmPassword];

            if (empty($password)) {
                $errors['password'] = 'Le mot de passe est obligatoire';
            }

            if (empty($confirmPassword)) {
                $errors['confirm_password'] = 'La confirmation du mot de passe est obligatoire';
            }

            if ($password && $confirmPassword && $password !== $confirmPassword) {
                $errors['password_mismatch'] = 'Les mots de passe ne correspondent pas';
            }

            if ($password) {
                if (strlen($password) < 6) {
                    $errors['password'] = 'Le mot de passe doit contenir au moins 6 caractères';
                } elseif (!preg_match('/[a-z]/', $password)) {
                    $errors['password'] = 'Le mot de passe doit contenir au moins une lettre minuscule';
                } elseif (!preg_match('/[A-Z]/', $password)) {
                    $errors['password'] = 'Le mot de passe doit contenir au moins une lettre majuscule';
                } elseif (!preg_match('/[0-9]/', $password)) {
                    $errors['password'] = 'Le mot de passe doit contenir au moins un chiffre';
                } elseif (!preg_match('/[@$!%*?&]/', $password)) {
                    $errors['password'] = 'Le mot de passe doit contenir au moins un caractère spécial (@$!%*?&)';
                }
            }

            if (empty($errors)) {
                if ($user->getResetToken() !== $token || $user->isResetTokenExpired()) {
                    $this->addFlash('error', 'Ce lien de réinitialisation n\'est plus valide.');
                    return $this->redirectToRoute('app_reset_password_error');
                }

                $hashedPassword = $passwordHasher->hashPassword($user, $password);
                $user->setMdpPer($hashedPassword);
                $user->setResetToken(null);
                $user->setResetTokenExpiresAt(null);

                $em->flush();

                return $this->redirectToRoute('app_reset_password_success');
            }
        }

        return $this->render('security/reset_password.html.twig', [
            'token' => $token,
            'errors' => $errors,
            'formData' => $formData,
        ]);
    }
}
