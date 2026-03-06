<?php
// src/Form/DirectionType.php

namespace App\Form;

use App\Entity\Direction;
use App\Entity\Personnel;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DirectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('CodeDirection', TextType::class, [
                'label' => 'Code Direction',
                'attr' => [
                    'placeholder' => 'Ex: DIR001',
                    'maxlength' => 20
                ]
            ])
            ->add('nomDirection', TextType::class, [
                'label' => 'Nom de la Direction',
                'attr' => [
                    'placeholder' => 'Ex: Direction des Ressources Humaines',
                    'maxlength' => 50
                ]
            ])
            ->add('personnel', EntityType::class, [
                'label' => 'Directeur',
                'class' => Personnel::class,
                'choice_label' => function (Personnel $personnel) {
                    return $personnel->getPrenomPer() . ' ' . $personnel->getNomPer() . ' (' . $personnel->getImPer() . ')';
                },
                'choices' => $options['personnels_disponibles'],
                'placeholder' => 'Sélectionner un directeur',
                'required' => false,
                'attr' => [
                    'class' => 'select2'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Direction::class,
            'personnels_disponibles' => [],
        ]);

        $resolver->setAllowedTypes('personnels_disponibles', 'array');
    }
}