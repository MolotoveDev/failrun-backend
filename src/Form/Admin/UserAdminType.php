<?php

namespace App\Form\Admin;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = (bool) $options['is_edit'];

        $builder
            ->add('username', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'El username es obligatorio'),
                    new Length(min: 3, minMessage: 'El username debe tener al menos {{ limit }} caracteres'),
                ],
            ])
            ->add('email', EmailType::class, [
                'constraints' => [
                    new NotBlank(message: 'El email es obligatorio'),
                ],
            ])
            ->add('profilePic', TextType::class, [
                'required' => false,
                'empty_data' => '',
            ])
            ->add('role', ChoiceType::class, [
                'mapped' => false,
                'choices' => [
                    'Usuario (ROLE_USER)' => 'ROLE_USER',
                    'Moderador (ROLE_MODERATOR)' => 'ROLE_MODERATOR',
                    'Admin (ROLE_ADMIN)' => 'ROLE_ADMIN',
                ],
                'constraints' => [
                    new NotBlank(message: 'Debes seleccionar un rol'),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'required' => !$isEdit,
                'empty_data' => '',
                'constraints' => $isEdit ? [] : [
                    new NotBlank(message: 'La contraseña es obligatoria'),
                    new Length(min: 6, minMessage: 'La contraseña debe tener al menos {{ limit }} caracteres'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
            'csrf_protection' => false,
        ]);

        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}
