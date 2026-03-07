<?php
// src/Service/EmailService.php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Twig\Environment;
use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\Personnel;

class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private Security $security
    ) {}

    public function sendAccountApprovalEmail(string $toEmail, string $userName): bool
    {
        try {
            $loginUrl = 'http://127.0.0.1:8000/login';
            

            $admin = $this->security->getUser();
            
            // Vérifier si c'est un objet Personnel et récupérer les informations
            if ($admin instanceof Personnel) {
                $adminEmail = $admin->getEmailPer();
                $adminName = $admin->getPrenomPer() . ' ' . $admin->getNomPer();
            } else {
                // Fallback si l'admin n'est pas un objet Personnel
                $adminEmail = 'juniorazanamparany@gmail.com';
                $adminName = 'Administrateur MIC';
            }
            
            $email = (new Email())
                ->from(new Address($adminEmail, $adminName . ' - MIC PTA'))
                ->to($toEmail)
                ->subject('Votre compte a été approuvé - MIC PTA')
                ->html($this->twig->render('emails/account_approved.html.twig', [
                    'user_name' => $userName,
                    'login_url' => $loginUrl,
                    'admin_name' => $adminName
                ]));

            $this->mailer->send($email);
            return true;

        } catch (\Exception $e) {
            error_log('Erreur envoi email: ' . $e->getMessage());
            return false;
        }
    }

    public function sendAccountRejectionEmail(string $toEmail, string $userName): bool
    {
        try {
            // Récupérer l'admin connecté
            $admin = $this->security->getUser();
            
            // Vérifier si c'est un objet Personnel et récupérer les informations
            if ($admin instanceof Personnel) {
                $adminEmail = $admin->getEmailPer();
                $adminName = $admin->getPrenomPer() . ' ' . $admin->getNomPer();
            } else {
                // Fallback si l'admin n'est pas un objet Personnel
                $adminEmail = 'administration@mic.gov.sn';
                $adminName = 'Administrateur MIC';
            }
            
            $email = (new Email())
                ->from(new Address($adminEmail, $adminName . ' - MIC PTA'))
                ->to($toEmail)
                ->subject('Votre demande d\'inscription - MIC PTA')
                ->html($this->twig->render('emails/account_rejected.html.twig', [
                    'user_name' => $userName,
                    'admin_name' => $adminName
                ]));

            $this->mailer->send($email);
            return true;

        } catch (\Exception $e) {
            error_log('Erreur envoi email: ' . $e->getMessage());
            return false;
        }
    }

    public function sendPasswordResetEmail(Personnel $user): bool
    {
        try {
            // Utiliser l'URL de base de l'application
            $baseUrl = 'http://127.0.0.1:8000'; // À adapter en production
            $resetUrl = $baseUrl . '/reset-password/' . $user->getResetToken();
            
            // Récupérer l'admin connecté ou utiliser un expéditeur par défaut
            $admin = $this->security->getUser();
            
            if ($admin instanceof Personnel) {
                $adminEmail = $admin->getEmailPer();
                $adminName = $admin->getPrenomPer() . ' ' . $admin->getNomPer();
            } else {
                $adminEmail = 'juniorazanamparany@gmail.com';
                $adminName = 'Administrateur MIC';
            }
            
            $email = (new Email())
                ->from(new Address($adminEmail, $adminName . ' - MIC PTA'))
                ->to($user->getEmailPer())
                ->subject('Réinitialisation de votre mot de passe - MIC PTA')
                ->html($this->twig->render('emails/password_reset.html.twig', [
                    'user' => $user,
                    'reset_url' => $resetUrl,
                    'expiration_date' => $user->getResetTokenExpiresAt()->format('d/m/Y à H:i'),
                    'admin_name' => $adminName
                ]));

            $this->mailer->send($email);
            return true;

        } catch (\Exception $e) {
            error_log('Erreur envoi email réinitialisation: ' . $e->getMessage());
            return false;
        }
    }
}