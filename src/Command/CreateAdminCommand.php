<?php
// src/Command/CreateAdminCommand.php

namespace App\Command;

use App\Entity\Personnel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée un utilisateur administrateur par défaut'
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('Cette commande crée un administrateur avec email admin@mic.gov.mg et mot de passe admin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Vérifier si l'admin existe déjà
        $existingAdmin = $this->entityManager->getRepository(Personnel::class)
            ->findOneBy(['EmailPer' => 'admin@mic.gov.mg']);

        if ($existingAdmin) {
            $io->warning('L\'administrateur existe déjà !');
            return Command::FAILURE;
        }

        // Créer le nouvel admin
        $admin = new Personnel();
        $admin->setImPer('000001');
        $admin->setNomPer('Admin');
        $admin->setPrenomPer('System');
        $admin->setEmailPer('admin@mic.gov.mg');
        $admin->setTelPer('+261 00 000 00');
        
        // Hasher le mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'admin');
        $admin->setMdpPer($hashedPassword);
        
        // Définir comme admin
        $admin->setRolePer('ROLE_ADMIN');
        $admin->setStatus('APPROVED');
        $admin->setIsValidated(true);

        try {
            // Sauvegarder
            $this->entityManager->persist($admin);
            $this->entityManager->flush();

            $io->success('✅ Administrateur créé avec succès !');
            $io->text('📧 Email: admin@mic.gov.mg');
            $io->text('🔑 Mot de passe: admin');
            $io->note('⚠️  Changez le mot de passe après la première connexion!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('❌ Erreur lors de la création: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}