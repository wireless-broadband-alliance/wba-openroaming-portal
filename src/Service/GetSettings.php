<?php

namespace App\Service;

use App\Enum\LanguageType;
use App\Enum\SettingName;
use App\Repository\SettingRepository;
use App\Repository\SettingTranslationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

readonly class GetSettings
{
    public function __construct(
        private SettingRepository $settingRepository,
        private SettingTranslationRepository $settingTranslationRepository,
        private RequestStack $requestStack,
        private TranslatorInterface $translator
    ) {
    }

    public function getSettings(?string $language = null): array|JsonResponse
    {
        // Get the current request from the RequestStack
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            throw new \RuntimeException(
                $this->translator->trans('noRequestAvailable', [], 'GetSettings')
            );
        }

        // Ignore locale logic for API requests
        if (str_starts_with($request->getPathInfo(), '/api')) {
            return [];
        }

        // Always fetch the latest setting names from the DB
        $allSettings = $this->settingRepository->findAll();

        $locale = $language
            ?? $request->getSession()->get('_locale')
            ?? LanguageType::EN->value;

        $data = [];

        // Fetch translations for the current locale
        $translations = $this->settingTranslationRepository->findBy(['locale' => $locale]);
        $localizedSettings = [];
        foreach ($translations as $translation) {
            $settingName = $translation->getSetting()->getName();
            $localizedSettings[$settingName] = [
                'value' => $translation->getTranslation(),
            ];
        }

        foreach ($allSettings as $setting) {
            $name = $setting->getName();

            $data[$name] = [
                'value' => $localizedSettings[$name]['value'] ?? $setting->getValue(),
                'description' => $this->getSettingDescription($name),
            ];
        }

        $currentSettingsName = array_keys($data);
        $expectedSettings = array_map(static fn($e) => $e->value, SettingName::cases());

        // Compare both sets
        $missingInDb = array_diff($expectedSettings, $currentSettingsName);
        $notInEnum = array_diff($currentSettingsName, $expectedSettings);

        // Check if all the settings on the DB are set and valid
        if (!empty($missingInDb)) {
            throw new HttpException(
                500,
                $this->translator->trans(
                    'settingsMissing',
                    ['%missing%' => implode(', ', $missingInDb)],
                    'GetSettings'
                )
            );
        }
        if (!empty($notInEnum)) {
            throw new HttpException(
                500,
                $this->translator->trans(
                    'notInEnum',
                    ['%notInEnum%' => implode(', ', $notInEnum)],
                    'GetSettings'
                )
            );
        }

        return $data;
    }

    public function getSettingsByLocale(array $settings, array $data): array
    {
        $settingsToTranslate = $this->arraySettingsToTranslate();

        foreach ($settings as $setting) {
            if (in_array($setting->getName(), $settingsToTranslate, true)) {
                $setting->setValue($data[$setting->getName()]['value']);
            }
        }
        return $settings;
    }

    public function getSettingDescription($settingName): ?string
    {
        // Retrieve current locale from the session, default to 'en' if not found
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            throw new \RuntimeException(
                $this->translator->trans('noRequestAvailable', [], 'GetSettings')
            );
        }

        $session = $request->getSession();
        $locale = $session->get('_locale') ?: LanguageType::EN->value;

        // phpcs:disable Generic.Files.LineLength.TooLong
        $descriptions = [
            LanguageType::EN->value => [
                SettingName::RADIUS_REALM_NAME->value => 'The realm name for your RADIUS server',
                SettingName::DISPLAY_NAME->value => 'The name used on the profiles',
                SettingName::PAYLOAD_IDENTIFIER->value => 'The identifier for the payload used on the profiles. This is only used to create iOS/macOS profiles.',
                SettingName::OPERATOR_NAME->value => 'The operator name used on the profiles',
                SettingName::DOMAIN_NAME->value => 'The domain name used for the service',
                SettingName::RADIUS_TLS_NAME->value => 'The hostname of your RADIUS server used for TLS',
                SettingName::NAI_REALM->value => 'The realm used for Network Access Identifier (NAI)',
                SettingName::RADIUS_TRUSTED_ROOT_CA_SHA1_HASH->value => 'The SHA1 hash of your RADIUS server\'s trusted root CA (Defaults to LetsEncrypt CA)',
                SettingName::PLATFORM_MODE->value => 'Live || Demo. When demo, only "demo login" is displayed, and SAML and other login methods are disabled regardless of other settings. A demo warning will also be displayed.',
                SettingName::API_STATUS->value => 'Defines whether the API is enabled or disabled.',
                SettingName::LOGIN_WITH_UUID_ONLY->value => 'Defines whether authentication is performed using a confirmation code sent to the user or a tradicional uuid & password. This feature when is set to "OFF" will send a reset password email for all the users portal accounts.',
                SettingName::USER_VERIFICATION->value => 'ON || OFF. When it\'s ON it activates the verification system. This system requires all the users to verify is own account before they download any profile',
                SettingName::TURNSTILE_CHECKER->value => 'The Turnstile checker is a validation step to between genuine users and bots. This can be used in Live or Demo modes.',
                SettingName::TWO_FACTOR_AUTH_STATUS->value => 'The status of two factor authentication when users log in to the platform',
                SettingName::TWO_FACTOR_AUTH_APP_LABEL->value => 'Platform identifier in two factor application',
                SettingName::TWO_FACTOR_AUTH_APP_ISSUER->value => 'Issuer identifier in two factor application',
                SettingName::TWO_FACTOR_AUTH_CODE_EXPIRATION_TIME->value => 'Local two-factor authentication code expiration time',
                SettingName::TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE->value => 'Number of attempts to request resending of the two  factor authentication code',
                SettingName::TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS->value => 'Time in minutes to reset attempts to send two factor authentication code',
                SettingName::TWO_FACTOR_AUTH_RESEND_INTERVAL->value => 'Time interval in seconds to request a new authentication code',
                SettingName::PAGE_TITLE->value => 'The title displayed on the webpage',
                SettingName::CUSTOMER_LOGO_ENABLED->value => 'Shows the customer logo on the landing page.',
                SettingName::CUSTOMER_LOGO->value => 'The resource path or URL to the customer\'s logo image',
                SettingName::OPENROAMING_LOGO->value => 'The resource path or URL to the OpenRoaming logo image',
                SettingName::WALLPAPER_IMAGE->value => 'The resource path or URL to the wallpaper image. Is recommended to use an image with a ratio of 13 : 14',
                SettingName::WELCOME_TEXT->value => 'The welcome text displayed on the user interface',
                SettingName::WELCOME_DESCRIPTION->value => 'The description text displayed under the welcome text',
                SettingName::ADDITIONAL_LABEL->value => 'Additional label displayed on the landing page for more, if necessary, information',
                SettingName::CONTACT_EMAIL->value => 'The email address for contact inquiries',
                SettingName::AUTH_METHOD_SAML_ENABLED->value => 'Enable or disable SAML authentication method',
                SettingName::AUTH_METHOD_SAML_LABEL->value => 'The label for SAML authentication button on the login page',
                SettingName::AUTH_METHOD_SAML_DESCRIPTION->value => 'The description for SAML authentication on the login page',
                SettingName::AUTH_METHOD_GOOGLE_LOGIN_ENABLED->value => 'Enable or disable Google authentication method',
                SettingName::AUTH_METHOD_GOOGLE_LOGIN_LABEL->value => 'The label for Google authentication button on the login page',
                SettingName::AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION->value => 'The description for Google authentication on the login page',
                SettingName::AUTH_METHOD_MICROSOFT_LOGIN_ENABLED->value => 'Enable or disable Microsoft authentication method',
                SettingName::AUTH_METHOD_MICROSOFT_LOGIN_LABEL->value => 'The label for Microsoft authentication button on the login page',
                SettingName::AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION->value => 'The description for Microsoft authentication on the login page',
                SettingName::AUTH_METHOD_REGISTER_ENABLED->value => 'Enable or disable Register authentication method',
                SettingName::AUTH_METHOD_REGISTER_LABEL->value => 'The label for Register authentication button on the login page',
                SettingName::AUTH_METHOD_REGISTER_DESCRIPTION->value => 'The description for Register authentication on the login page',
                SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED->value => 'Enable or disable Login with phone Number or Email',
                SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_LABEL->value => 'The label for Login authentication button on the login page',
                SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION->value => 'The description for Login authentication on the login page',
                SettingName::AUTH_METHOD_SMS_REGISTER_ENABLED->value => 'Enable or disable authentication register with the phone number',
                SettingName::AUTH_METHOD_SMS_REGISTER_LABEL->value => 'The label for authentication with the phone number, on button of the login page',
                SettingName::AUTH_METHOD_SMS_REGISTER_DESCRIPTION->value => 'The description for authentication with the phone number on the login page',
                SettingName::SYNC_LDAP_ENABLED->value => 'Enable or disable synchronization with LDAP.',
                SettingName::SYNC_LDAP_SERVER->value => "The LDAP server's URL.",
                SettingName::SYNC_LDAP_BIND_USER_DN->value => 'The Distinguished Name (DN) used to bind to the LDAP server.',
                SettingName::SYNC_LDAP_BIND_USER_PASSWORD->value => 'The password for the bind user on the LDAP server.',
                SettingName::SYNC_LDAP_SEARCH_BASE_DN->value => 'The base DN used when searching the LDAP directory.',
                SettingName::SYNC_LDAP_SEARCH_FILTER->value => 'The filter used when searching the LDAP directory. The placeholder `@ID` is replaced with the user\'s ID.',
                SettingName::TOS->value => 'Terms and Conditions format',
                SettingName::PRIVACY_POLICY->value => 'Privacy policy format',
                SettingName::TOS_LINK->value => 'Terms and Conditions URL',
                SettingName::PRIVACY_POLICY_LINK->value => 'Privacy policy URL',
                SettingName::TOS_EDITOR->value => 'Terms and Conditions text editor',
                SettingName::PRIVACY_POLICY_EDITOR->value => 'Privacy policy text editor',
                SettingName::VALID_DOMAINS_GOOGLE_LOGIN->value => 'When this is empty, it allows all the domains to authenticate. Please only type the domains you want to be able to authenticate. Domains must be separated by ","',
                SettingName::VALID_DOMAINS_MICROSOFT_LOGIN->value => 'When this is empty, it allows all the domains to authenticate. Please only type the domains you want to be able to authenticate. Domains must be separated by ","',
                SettingName::PROFILES_ENCRYPTION_TYPE_IOS_ONLY->value => 'Type of encryption defined for the creation of the profiles',
                SettingName::CAPPORT_ENABLED->value => 'Enable or disable Capport DHCP configuration',
                SettingName::CAPPORT_PORTAL_URL->value => 'Domain that is from the entity hosting the service',
                SettingName::CAPPORT_VENUE_INFO_URL->value => 'Domain where the user is redirected after clicking the DHCP notification',
                SettingName::SMS_USERNAME->value => 'Budget SMS Username',
                SettingName::SMS_USER_ID->value => 'Budget SMS User ID',
                SettingName::SMS_HANDLE->value => 'Budget SMS Handle hash',
                SettingName::SMS_FROM->value => 'Entity sending the SMS for the users',
                SettingName::SMS_TIMER_RESEND->value => 'Time in minutes to make the user wait to resend a new SMS',
                SettingName::EMAIL_TIMER_RESEND->value => 'Time in minutes to make the user wait to resend a new email for reset password requests',
                SettingName::LINK_VALIDITY->value => 'Time in minutes a link stays active before it expires.',
                SettingName::USER_DELETE_TIME->value => 'Time in hours to delete the unverified user',
                SettingName::TIME_INTERVAL_NOTIFICATION->value => 'The notification interval (in days) to alert a user before their profile expires',
                SettingName::DEFAULT_REGION_PHONE_INPUTS->value => 'Set the default regions for the phone number inputs',
                SettingName::PROFILE_LIMIT_DATE_GOOGLE->value => 'Time in days to disable profiles for users with Google login',
                SettingName::PROFILE_LIMIT_DATE_MICROSOFT->value => 'Time in days to disable profiles for users with Microsoft login',
                SettingName::PROFILE_LIMIT_DATE_SAML->value => 'Time in days to disable profiles for users with SAML login',
                SettingName::PROFILE_LIMIT_DATE_EMAIL->value => 'Time in days to disable profiles for users with EMAIL login',
                SettingName::PROFILE_LIMIT_DATE_SMS->value => 'Time in days to disable profiles for users with SMS login',
                SettingName::DELETE_UNCONFIRMED_USERS_CRON->value => 'Defines the schedule to delete unconfirmed users from the portal',
                SettingName::USERS_WHEN_PROFILE_EXPIRES_CRON->value => 'Defines the schedule to notify the users when their profile to expire',
                SettingName::LDAP_SYNC_CRON->value => 'Defines the schedule for LDAP synchronization automation command',
                SettingName::FREERADIUS_LAST_CONNECTION_CRON->value => 'Defines the schedule for Freeradius server & the user profile last connection',
                SettingName::CRON_ADVANCED_STATUS->value => 'Saves the previous status mode on the schedule cron configuration page (Simple/Advanced)'
            ],
            LanguageType::PT->value => [
                SettingName::RADIUS_REALM_NAME->value => 'O nome do realm para o seu servidor RADIUS',
                SettingName::DISPLAY_NAME->value => 'O nome utilizado nos perfis',
                SettingName::PAYLOAD_IDENTIFIER->value => 'O identificador da carga útil utilizada nos perfis. Apenas usado para criar perfis iOS/macOS.',
                SettingName::OPERATOR_NAME->value => 'O nome do operador utilizado nos perfis',
                SettingName::DOMAIN_NAME->value => 'O nome de domínio utilizado para o serviço',
                SettingName::RADIUS_TLS_NAME->value => 'O nome do host do seu servidor RADIUS utilizado para TLS',
                SettingName::NAI_REALM->value => 'O realm utilizado para o Identificador de Acesso à Rede (NAI)',
                SettingName::RADIUS_TRUSTED_ROOT_CA_SHA1_HASH->value => 'O hash SHA1 da CA raiz confiável do seu servidor RADIUS (Padrão: CA da LetsEncrypt)',
                SettingName::PLATFORM_MODE->value => 'Live || Demo. Quando em demo, apenas o "login demo" é mostrado, e SAML e outros métodos de login são desativados, independentemente das outras definições. Um aviso de demonstração também será exibido.',
                SettingName::API_STATUS->value => 'Define se a API está ativa ou desativada.',
                SettingName::LOGIN_WITH_UUID_ONLY->value => 'Define se a autenticação é feita usando um código de confirmação enviado ao utilizador ou a tradicional uuid e password. Esta funcionalidade, quando definida como "OFF", irá enviar um e-mail de reset de palavra-passe para todas os utilizadores com conta no portal.',
                SettingName::USER_VERIFICATION->value => 'ON || OFF. Quando está ON, ativa o sistema de verificação. Este sistema exige que todos os utilizadores verifiquem a sua conta antes de descarregarem qualquer perfil',
                SettingName::TURNSTILE_CHECKER->value => 'O verificador Turnstile é uma etapa de validação entre utilizadores genuínos e bots. Pode ser usado nos modos Live ou Demo.',
                SettingName::TWO_FACTOR_AUTH_STATUS->value => 'O estado da autenticação de dois fatores quando os utilizadores iniciam sessão na plataforma',
                SettingName::TWO_FACTOR_AUTH_APP_LABEL->value => 'Identificador da plataforma na aplicação de dois fatores',
                SettingName::TWO_FACTOR_AUTH_APP_ISSUER->value => 'Identificador do emissor na aplicação de dois fatores',
                SettingName::TWO_FACTOR_AUTH_CODE_EXPIRATION_TIME->value => 'Tempo de expiração do código de autenticação de dois fatores (local)',
                SettingName::TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE->value => 'Número de tentativas para pedir novo envio do código de autenticação de dois fatores',
                SettingName::TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS->value => 'Tempo em minutos para redefinir as tentativas de envio do código de autenticação',
                SettingName::TWO_FACTOR_AUTH_RESEND_INTERVAL->value => 'Intervalo de tempo em segundos para pedir um novo código de autenticação',
                SettingName::PAGE_TITLE->value => 'O título apresentado na página web',
                SettingName::CUSTOMER_LOGO_ENABLED->value => 'Mostra o logótipo do cliente na página inicial.',
                SettingName::CUSTOMER_LOGO->value => 'O caminho ou URL do recurso para a imagem do logótipo do cliente',
                SettingName::OPENROAMING_LOGO->value => 'O caminho ou URL do recurso para a imagem do logótipo OpenRoaming',
                SettingName::WALLPAPER_IMAGE->value => 'O caminho ou URL do recurso para a imagem de fundo. É recomendado usar uma imagem com proporção de 13:14',
                SettingName::WELCOME_TEXT->value => 'O texto de boas-vindas apresentado na interface do utilizador',
                SettingName::WELCOME_DESCRIPTION->value => 'O texto de descrição apresentado abaixo do texto de boas-vindas',
                SettingName::ADDITIONAL_LABEL->value => 'Etiqueta adicional apresentada na página inicial para mais informações, se necessário',
                SettingName::CONTACT_EMAIL->value => 'O endereço de e-mail para contactos',
                SettingName::AUTH_METHOD_SAML_ENABLED->value => 'Ativar ou desativar o método de autenticação SAML',
                SettingName::AUTH_METHOD_SAML_LABEL->value => 'Etiqueta para o botão de autenticação SAML na página de login',
                SettingName::AUTH_METHOD_SAML_DESCRIPTION->value => 'Descrição da autenticação SAML na página de login',
                SettingName::AUTH_METHOD_GOOGLE_LOGIN_ENABLED->value => 'Ativar ou desativar o método de autenticação Google',
                SettingName::AUTH_METHOD_GOOGLE_LOGIN_LABEL->value => 'Etiqueta para o botão de autenticação Google na página de login',
                SettingName::AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION->value => 'Descrição da autenticação Google na página de login',
                SettingName::AUTH_METHOD_MICROSOFT_LOGIN_ENABLED->value => 'Ativar ou desativar o método de autenticação Microsoft',
                SettingName::AUTH_METHOD_MICROSOFT_LOGIN_LABEL->value => 'Etiqueta para o botão de autenticação Microsoft na página de login',
                SettingName::AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION->value => 'Descrição da autenticação Microsoft na página de login',
                SettingName::AUTH_METHOD_REGISTER_ENABLED->value => 'Ativar ou desativar o método de autenticação Registo',
                SettingName::AUTH_METHOD_REGISTER_LABEL->value => 'Etiqueta para o botão de autenticação Registo na página de login',
                SettingName::AUTH_METHOD_REGISTER_DESCRIPTION->value => 'Descrição da autenticação Registo na página de login',
                SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED->value => 'Ativar ou desativar o login com número de telefone ou e-mail',
                SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_LABEL->value => 'Etiqueta para o botão de autenticação Login na página de login',
                SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION->value => 'Descrição da autenticação Login na página de login',
                SettingName::AUTH_METHOD_SMS_REGISTER_ENABLED->value => 'Ativar ou desativar o registo de autenticação com número de telefone',
                SettingName::AUTH_METHOD_SMS_REGISTER_LABEL->value => 'Etiqueta para autenticação com número de telefone, no botão da página de login',
                SettingName::AUTH_METHOD_SMS_REGISTER_DESCRIPTION->value => 'Descrição da autenticação com número de telefone na página de login',
                SettingName::SYNC_LDAP_ENABLED->value => 'Ativar ou desativar a sincronização com o LDAP.',
                SettingName::SYNC_LDAP_SERVER->value => 'URL do servidor LDAP.',
                SettingName::SYNC_LDAP_BIND_USER_DN->value => 'O Nome Distinto (DN) utilizado para ligação ao servidor LDAP.',
                SettingName::SYNC_LDAP_BIND_USER_PASSWORD->value => 'A palavra-passe do utilizador de ligação no servidor LDAP.',
                SettingName::SYNC_LDAP_SEARCH_BASE_DN->value => 'O DN base utilizado ao pesquisar no diretório LDAP.',
                SettingName::SYNC_LDAP_SEARCH_FILTER->value => 'O filtro utilizado ao pesquisar no diretório LDAP. O marcador `@ID` é substituído pelo ID do utilizador.',
                SettingName::TOS->value => 'Formato dos Termos e Condições',
                SettingName::PRIVACY_POLICY->value => 'Formato da Política de Privacidade',
                SettingName::TOS_LINK->value => 'URL dos Termos e Condições',
                SettingName::PRIVACY_POLICY_LINK->value => 'URL da Política de Privacidade',
                SettingName::TOS_EDITOR->value => 'Editor de texto dos Termos e Condições',
                SettingName::PRIVACY_POLICY_EDITOR->value => 'Editor de texto da Política de Privacidade',
                SettingName::VALID_DOMAINS_GOOGLE_LOGIN->value => 'Quando vazio, permite que todos os domínios se autentiquem. Introduza apenas os domínios que pretende permitir. Os dominios devem ser separados por ","',
                SettingName::VALID_DOMAINS_MICROSOFT_LOGIN->value => 'Quando vazio, permite que todos os domínios se autentiquem. Introduza apenas os domínios que pretende permitir. Os dominios devem ser separados por ","',
                SettingName::PROFILES_ENCRYPTION_TYPE_IOS_ONLY->value => 'Tipo de encriptação definido para criação de perfis',
                SettingName::CAPPORT_ENABLED->value => 'Ativar ou desativar a configuração Capport DHCP',
                SettingName::CAPPORT_PORTAL_URL->value => 'Domínio da entidade que aloja o serviço',
                SettingName::CAPPORT_VENUE_INFO_URL->value => 'Domínio para onde o utilizador é redirecionado após clicar na notificação DHCP',
                SettingName::SMS_USERNAME->value => 'Nome de utilizador do Budget SMS',
                SettingName::SMS_USER_ID->value => 'ID de utilizador do Budget SMS',
                SettingName::SMS_HANDLE->value => 'Hash de identificação do Budget SMS',
                SettingName::SMS_FROM->value => 'Entidade que envia o SMS para os utilizadores',
                SettingName::SMS_TIMER_RESEND->value => 'Tempo em minutos que o utilizador tem de esperar para reenviar um novo SMS',
                SettingName::EMAIL_TIMER_RESEND->value => 'Tempo em minutos que o utilizador deve aguardar para reenviar um novo e-mail para pedidos de redefinição de palavra-passe',
                SettingName::LINK_VALIDITY->value => 'Tempo em minutos durante o qual um link permanece ativo antes de expirar.',
                SettingName::USER_DELETE_TIME->value => 'Tempo em horas para eliminar o utilizador não verificado',
                SettingName::TIME_INTERVAL_NOTIFICATION->value => 'Intervalo de notificação (em dias) para alertar o utilizador antes de o perfil expirar',
                SettingName::DEFAULT_REGION_PHONE_INPUTS->value => 'Definir as regiões padrão para os campos de número de telefone',
                SettingName::PROFILE_LIMIT_DATE_GOOGLE->value => 'Tempo em dias para desativar perfis de utilizadores com login Google',
                SettingName::PROFILE_LIMIT_DATE_MICROSOFT->value => 'Tempo em dias para desativar perfis de utilizadores com login Microsoft',
                SettingName::PROFILE_LIMIT_DATE_SAML->value => 'Tempo em dias para desativar perfis de utilizadores com login SAML',
                SettingName::PROFILE_LIMIT_DATE_EMAIL->value => 'Tempo em dias para desativar perfis de utilizadores com login por e-mail',
                SettingName::PROFILE_LIMIT_DATE_SMS->value => 'Tempo em dias para desativar perfis de utilizadores com login por SMS',
                SettingName::DELETE_UNCONFIRMED_USERS_CRON->value => 'Define o agendamento para eliminar utilizadores não confirmados do portal',
                SettingName::USERS_WHEN_PROFILE_EXPIRES_CRON->value => 'Define o agendamento para notificar os utilizadores quando o perfil estiver prestes a expirar',
                SettingName::LDAP_SYNC_CRON->value => 'Define o agendamento para o comando automático de sincronização LDAP',
                SettingName::FREERADIUS_LAST_CONNECTION_CRON->value => 'Define o agendamento para o servidor Freeradius e a última ligação do perfil do utilizador',
                SettingName::CRON_ADVANCED_STATUS->value => 'Guarda o modo de estado anterior na página de configuração do cron (Simples/Avançado)'
            ]
        ];
        // phpcs:enable

        return $descriptions[$locale][$settingName]
            ?? $descriptions[LanguageType::EN->value][$settingName]
            ?? null;
    }

    public function arraySettingsToTranslate(): array
    {
        return [
            SettingName::WELCOME_TEXT->value,
            SettingName::WELCOME_DESCRIPTION->value,
            SettingName::ADDITIONAL_LABEL->value,
            SettingName::AUTH_METHOD_SAML_LABEL->value,
            SettingName::AUTH_METHOD_SAML_DESCRIPTION->value,
            SettingName::AUTH_METHOD_GOOGLE_LOGIN_LABEL->value,
            SettingName::AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION->value,
            SettingName::AUTH_METHOD_MICROSOFT_LOGIN_LABEL->value,
            SettingName::AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION->value,
            SettingName::AUTH_METHOD_REGISTER_LABEL->value,
            SettingName::AUTH_METHOD_REGISTER_DESCRIPTION->value,
            SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_LABEL->value,
            SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION->value,
            SettingName::AUTH_METHOD_SMS_REGISTER_LABEL->value,
            SettingName::AUTH_METHOD_SMS_REGISTER_DESCRIPTION->value,
        ];
    }
}
