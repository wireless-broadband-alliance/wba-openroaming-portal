<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ScheduleType extends AbstractType
{
    public function __construct()
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('DELETE_UNCONFIRMED_USERS_CRON', TextType::class, [
                'label' => 'Delete Unconfirmed Users Cron',
                'required' => true,
            ])
            ->add('USERS_WHEN_PROFILE_EXPIRES_CRON', TextType::class, [
                'label' => 'Notify Users When Profile Expires Cron',
                'required' => true,
            ])
            ->add('LDAP_SYNC_CRON', TextType::class, [
                'label' => 'LDAP Sync Cron',
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'settings' => [], // No need to set settings here
        ]);
    }
}
