<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\OperationMode;
use App\Enum\SettingName;
use App\Repository\SettingRepository;
use App\Service\GetSettings;
use App\Validator\Constraints\DomainValidNotInBlacklist;
use PixelOpen\CloudflareTurnstileBundle\Type\TurnstileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<null>
 */
class RegistrationFormType extends AbstractType
{
    public function __construct(
        private readonly SettingRepository $settingRepository,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $turnstileCheckerValue = $this->settingRepository->findOneBy(
            ['name' => SettingName::TURNSTILE_CHECKER->value]
        )->getValue();

        $builder
            ->add('email', EmailType::class, ['constraints' => [new DomainValidNotInBlacklist()]]);

        // Check if TURNSTILE_CHECKER value is ON
        if ($turnstileCheckerValue === OperationMode::ON->value) {
            $builder->add('security', TurnstileType::class, [
                'attr' => [
                    'data-action' => 'contact',
                    'data-theme' => 'light',
                    'data-language' => $this->translator->getLocale()
                ],
                'label' => false
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
