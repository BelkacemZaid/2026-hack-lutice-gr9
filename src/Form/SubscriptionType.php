<?php

namespace App\Form;

use App\Entity\Subscription;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Vich\UploaderBundle\Form\Type\VichImageType;
// Assure-toi d'avoir VichUploaderBundle

class SubscriptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('clientName', TextType::class, [
                'label' => 'Nom de l\'entreprise (Sous-domaine)',
                'constraints' => [
                    new NotBlank(['message' => 'Nom obligatoire']),
                    new Length(['min' => 3, 'max' => 30]),
                    // Idéalement : ajouter une Regex pour forcer [a-z0-9-]
                ],
                'attr' => ['placeholder' => 'ex: mon-ecole']
            ])
            ->add('clientEmail', EmailType::class, [
                'label' => 'Email de contact',
                'attr' => ['placeholder' => 'admin@ecole.fr']
            ])
            ->add('logoFile', VichImageType::class, [
                'required' => false,
                'label' => 'Logo (Optionnel)',
                'allow_delete' => true,
                'download_uri' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Subscription::class,
        ]);
    }
}
