<?php

namespace App\Form;

use App\DTO\CloudflareDTO;
use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Form\Type\VichFileType;

class CloudflareType extends AbstractType
{

    public function __construct(
        private readonly TranslatorInterface $translator
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('$caCert', VichFileType::class, [
                'label' => 'CA (.pem)',
                'required' => true,
                'allow_delete' => false,
                'download_uri' => false,
            ])
            ->add('host', TextType::class, [
                'label' => $this->translator->trans('hostLabel', [], 'CloudflareType'),
                'required' => true,
            ])
            ->add('token', TextType::class, [
                'label' => $this->translator->trans('tokenLabel', [], 'CloudflareType'),
                'required' => true,
            ])
            ->add('port', TextType::class, [
                'label' => $this->translator->trans('portLabel', [], 'CloudflareType'),
                'required' => true,
            ]);
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CloudflareDTO::class,
        ]);
    }
}