<?php

namespace App\Service;

use App\Repository\SettingRepository;
use App\Repository\SettingTranslationRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class GetSettings
{
    public function __construct(
        private SettingRepository $settingRepository,
        private SettingTranslationRepository $settingTranslationRepository,
        private RequestStack $requestStack
    ) {
    }

    public function getSettings(): array
    {
        // Get the current request from the RequestStack
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            throw new \RuntimeException('No current request available.');
        }

        $session = $request->getSession();
        $locale = $session->get('_locale') ?: 'en';
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

        $specialSettings = [
            'TURNSTILE_CHECKER',
            'USER_VERIFICATION',
            'PLATFORM_MODE',
        ];

        foreach ($this->settingRepository->findAll() as $setting) {
            if (in_array($setting->getName(), $specialSettings, true)) {
                continue;
            }

            $settingName = $setting->getName();
            $data[$this->mapSetting($settingName)] = [
                'value' => $localizedSettings[$settingName]['value'] ?? $setting->getValue(),
                'description' => $this->getSettingDescription($settingName),
            ];
        }

        $turnstile_checker = $this->settingRepository->findOneBy(['name' => 'TURNSTILE_CHECKER']);
        if ($turnstile_checker !== null) {
            $data['TURNSTILE_CHECKER'] = [
                'value' => $turnstile_checker->getValue(),
                'description' => $this->getSettingDescription('TURNSTILE_CHECKER'),
            ];
        }

        $user_verification = $this->settingRepository->findOneBy(['name' => 'USER_VERIFICATION']);
        if ($user_verification !== null) {
            $data['USER_VERIFICATION'] = [
                'value' => $user_verification->getValue(),
                'description' => $this->getSettingDescription('USER_VERIFICATION'),
            ];
        }

        $data['PLATFORM_MODE'] = [
            'value' => $this->settingRepository->findOneBy(['name' => 'PLATFORM_MODE'])->getValue() === 'Demo',
            'description' => $this->getSettingDescription('PLATFORM_MODE'),
        ];

        return $data;
    }

    public function getSettingDescription($settingName): string
    {
        // Retrieve current locale from the session, default to 'en' if not found
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            throw new \RuntimeException('No current request available.');
        }

        $session = $request->getSession();
        $locale = $session->get('_locale') ?: 'en';

        // phpcs:disable Generic.Files.LineLength.TooLong
        $descriptions = [
            'en' => [
                'RADIUS_REALM_NAME' => 'The realm name for your RADIUS server',
                'DISPLAY_NAME' => 'The name used on the profiles',
                'PAYLOAD_IDENTIFIER' => 'The identifier for the payload used on the profiles. This is only used to create iOS/macOS profiles.',
                'OPERATOR_NAME' => 'The operator name used on the profiles',
                'DOMAIN_NAME' => 'The domain name used for the service',
                'RADIUS_TLS_NAME' => 'The hostname of your RADIUS server used for TLS',
                'NAI_REALM' => 'The realm used for Network Access Identifier (NAI)',
                'RADIUS_TRUSTED_ROOT_CA_SHA1_HASH' => 'The SHA1 hash of your RADIUS server\'s trusted root CA (Defaults to LetsEncrypt CA)',
                'PLATFORM_MODE' => 'Live || Demo. When demo, only "demo login" is displayed, and SAML and other login methods are disabled regardless of other settings. A demo warning will also be displayed.',
                'API_STATUS' => 'Defines whether the API is enabled or disabled.',
                'USER_VERIFICATION' => 'ON || OFF. When it\'s ON it activates the verification system. This system requires all the users to verify is own account before they download any profile',
                'TURNSTILE_CHECKER' => 'The Turnstile checker is a validation step to between genuine users and bots. This can be used in Live or Demo modes.',
                'TWO_FACTOR_AUTH_STATUS' => 'The status of two factor authentication when users log in to the platform',
                'TWO_FACTOR_AUTH_APP_LABEL' => 'Platform identifier in two factor application',
                'TWO_FACTOR_AUTH_APP_ISSUER' => 'Issuer identifier in two factor application',
                'TWO_FACTOR_AUTH_CODE_EXPIRATION_TIME' => 'Local two-factor authentication code expiration time',
                'TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE' => 'Number of attempts to request resending of the two  factor authentication code',
                'TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS' => 'Time in minutes to reset attempts to send two factor authentication code',
                'TWO_FACTOR_AUTH_RESEND_INTERVAL' => 'Time interval in seconds to request a new authentication code',
                'PAGE_TITLE' => 'The title displayed on the webpage',
                'CUSTOMER_LOGO_ENABLED' => 'Shows the customer logo on the landing page.',
                'CUSTOMER_LOGO' => 'The resource path or URL to the customer\'s logo image',
                'OPENROAMING_LOGO' => 'The resource path or URL to the OpenRoaming logo image',
                'WALLPAPER_IMAGE' => 'The resource path or URL to the wallpaper image. Is recommended to use an image with a ratio of 13 : 14',
                'WELCOME_TEXT' => 'The welcome text displayed on the user interface',
                'WELCOME_DESCRIPTION' => 'The description text displayed under the welcome text',
                'ADDITIONAL_LABEL' => 'Additional label displayed on the landing page for more, if necessary, information',
                'CONTACT_EMAIL' => 'The email address for contact inquiries',
                'AUTH_METHOD_SAML_ENABLED' => 'Enable or disable SAML authentication method',
                'AUTH_METHOD_SAML_LABEL' => 'The label for SAML authentication button on the login page',
                'AUTH_METHOD_SAML_DESCRIPTION' => 'The description for SAML authentication on the login page',
                'AUTH_METHOD_GOOGLE_LOGIN_ENABLED' => 'Enable or disable Google authentication method',
                'AUTH_METHOD_GOOGLE_LOGIN_LABEL' => 'The label for Google authentication button on the login page',
                'AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION' => 'The description for Google authentication on the login page',
                'AUTH_METHOD_MICROSOFT_LOGIN_ENABLED' => 'Enable or disable Microsoft authentication method',
                'AUTH_METHOD_MICROSOFT_LOGIN_LABEL' => 'The label for Microsoft authentication button on the login page',
                'AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION' =>
                    'The description for Microsoft authentication on the login page',
                'AUTH_METHOD_REGISTER_ENABLED' => 'Enable or disable Register authentication method',
                'AUTH_METHOD_REGISTER_LABEL' => 'The label for Register authentication button on the login page',
                'AUTH_METHOD_REGISTER_DESCRIPTION' => 'The description for Register authentication on the login page',
                'AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED' => 'Enable or disable Login with phone Number or Email',
                'AUTH_METHOD_LOGIN_TRADITIONAL_LABEL' => 'The label for Login authentication button on the login page',
                'AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION' => 'The description for Login authentication on the login page',
                'AUTH_METHOD_SMS_REGISTER_ENABLED' => 'Enable or disable authentication register with the phone number',
                'AUTH_METHOD_SMS_REGISTER_LABEL' => 'The label for authentication with the phone number, on button of the login page',
                'AUTH_METHOD_SMS_REGISTER_DESCRIPTION' => 'The description for authentication with the phone number on the login page',
                'SYNC_LDAP_ENABLED' => 'Enable or disable synchronization with LDAP.',
                'SYNC_LDAP_SERVER' => "The LDAP server's URL.",
                'SYNC_LDAP_BIND_USER_DN' => 'The Distinguished Name (DN) used to bind to the LDAP server.',
                'SYNC_LDAP_BIND_USER_PASSWORD' => 'The password for the bind user on the LDAP server.',
                'SYNC_LDAP_SEARCH_BASE_DN' => 'The base DN used when searching the LDAP directory.',
                'SYNC_LDAP_SEARCH_FILTER' => 'The filter used when searching the LDAP directory. The placeholder `@ID` is replaced with the user\'s ID.',
                'TOS' => 'Terms and Conditions format',
                'PRIVACY_POLICY' => 'Privacy policy format',
                'TOS_LINK' => 'Terms and Conditions URL',
                'PRIVACY_POLICY_LINK' => 'Privacy policy URL',
                'TOS_EDITOR' => 'Terms and Conditions text editor',
                'PRIVACY_POLICY_EDITOR' => 'Privacy policy text editor',
                'VALID_DOMAINS_GOOGLE_LOGIN' => 'When this is empty, it allows all the domains to authenticate. Please only type the domains you want to be able to authenticate',
                'VALID_DOMAINS_MICROSOFT_LOGIN' => 'When this is empty, it allows all the domains to authenticate. Please only type the domains you want to be able to authenticate',
                'PROFILES_ENCRYPTION_TYPE_IOS_ONLY' => 'Type of encryption defined for the creation of the profiles',
                'CAPPORT_ENABLED' => 'Enable or disable Capport DHCP configuration',
                'CAPPORT_PORTAL_URL' => 'Domain that is from the entity hosting the service',
                'CAPPORT_VENUE_INFO_URL' => 'Domain where the user is redirected after clicking the DHCP notification',

                'SMS_USERNAME' => 'Budget SMS Username',
                'SMS_USER_ID' => 'Budget SMS User ID',
                'SMS_HANDLE' => 'Budget SMS Handle hash',
                'SMS_FROM' => 'Entity sending the SMS for the users',
                'SMS_TIMER_RESEND' => 'Time in minutes to make the user wait to resend a new SMS',
                'USER_DELETE_TIME' => 'Time in hours to delete the unverified user',
                'TIME_INTERVAL_NOTIFICATION' =>
                    'The notification interval (in days) to alert a user before their profile expires',
                'DEFAULT_REGION_PHONE_INPUTS' => 'Set the default regions for the phone number inputs',
                'PROFILE_LIMIT_DATE_GOOGLE' => 'Time in days to disable profiles for users with Google login',
                'PROFILE_LIMIT_DATE_MICROSOFT' => 'Time in days to disable profiles for users with Microsoft login',
                'PROFILE_LIMIT_DATE_SAML' => 'Time in days to disable profiles for users with SAML login',
                'PROFILE_LIMIT_DATE_EMAIL' => 'Time in days to disable profiles for users with EMAIL login',
                'PROFILE_LIMIT_DATE_SMS' => 'Time in days to disable profiles for users with SMS login',
            ],
            'pt' => [
                'RADIUS_REALM_NAME' => 'O nome do realm para o seu servidor RADIUS',
                'DISPLAY_NAME' => 'O nome utilizado nos perfis',
                'PAYLOAD_IDENTIFIER' => 'O identificador da carga útil utilizada nos perfis. Apenas usado para criar perfis iOS/macOS.',
                'OPERATOR_NAME' => 'O nome do operador utilizado nos perfis',
                'DOMAIN_NAME' => 'O nome de domínio utilizado para o serviço',
                'RADIUS_TLS_NAME' => 'O nome do host do seu servidor RADIUS utilizado para TLS',
                'NAI_REALM' => 'O realm utilizado para o Identificador de Acesso à Rede (NAI)',
                'RADIUS_TRUSTED_ROOT_CA_SHA1_HASH' => 'O hash SHA1 da CA raiz confiável do seu servidor RADIUS (Padrão: CA da LetsEncrypt)',
                'PLATFORM_MODE' => 'Live || Demo. Quando em demo, apenas o "login demo" é mostrado, e SAML e outros métodos de login são desativados, independentemente das outras definições. Um aviso de demonstração também será exibido.',
                'API_STATUS' => 'Define se a API está ativa ou desativada.',
                'USER_VERIFICATION' => 'ON || OFF. Quando está ON, ativa o sistema de verificação. Este sistema exige que todos os utilizadores verifiquem a sua conta antes de descarregarem qualquer perfil',
                'TURNSTILE_CHECKER' => 'O verificador Turnstile é uma etapa de validação entre utilizadores genuínos e bots. Pode ser usado nos modos Live ou Demo.',
                'TWO_FACTOR_AUTH_STATUS' => 'O estado da autenticação de dois fatores quando os utilizadores iniciam sessão na plataforma',
                'TWO_FACTOR_AUTH_APP_LABEL' => 'Identificador da plataforma na aplicação de dois fatores',
                'TWO_FACTOR_AUTH_APP_ISSUER' => 'Identificador do emissor na aplicação de dois fatores',
                'TWO_FACTOR_AUTH_CODE_EXPIRATION_TIME' => 'Tempo de expiração do código de autenticação de dois fatores (local)',
                'TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE' => 'Número de tentativas para pedir novo envio do código de autenticação de dois fatores',
                'TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS' => 'Tempo em minutos para redefinir as tentativas de envio do código de autenticação',
                'TWO_FACTOR_AUTH_RESEND_INTERVAL' => 'Intervalo de tempo em segundos para pedir um novo código de autenticação',
                'PAGE_TITLE' => 'O título apresentado na página web',
                'CUSTOMER_LOGO_ENABLED' => 'Mostra o logótipo do cliente na página inicial.',
                'CUSTOMER_LOGO' => 'O caminho ou URL do recurso para a imagem do logótipo do cliente',
                'OPENROAMING_LOGO' => 'O caminho ou URL do recurso para a imagem do logótipo OpenRoaming',
                'WALLPAPER_IMAGE' => 'O caminho ou URL do recurso para a imagem de fundo. É recomendado usar uma imagem com proporção de 13:14',
                'WELCOME_TEXT' => 'O texto de boas-vindas apresentado na interface do utilizador',
                'WELCOME_DESCRIPTION' => 'O texto de descrição apresentado abaixo do texto de boas-vindas',
                'ADDITIONAL_LABEL' => 'Etiqueta adicional apresentada na página inicial para mais informações, se necessário',
                'CONTACT_EMAIL' => 'O endereço de e-mail para contactos',
                'AUTH_METHOD_SAML_ENABLED' => 'Ativar ou desativar o método de autenticação SAML',
                'AUTH_METHOD_SAML_LABEL' => 'Etiqueta para o botão de autenticação SAML na página de login',
                'AUTH_METHOD_SAML_DESCRIPTION' => 'Descrição da autenticação SAML na página de login',
                'AUTH_METHOD_GOOGLE_LOGIN_ENABLED' => 'Ativar ou desativar o método de autenticação Google',
                'AUTH_METHOD_GOOGLE_LOGIN_LABEL' => 'Etiqueta para o botão de autenticação Google na página de login',
                'AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION' => 'Descrição da autenticação Google na página de login',
                'AUTH_METHOD_MICROSOFT_LOGIN_ENABLED' => 'Ativar ou desativar o método de autenticação Microsoft',
                'AUTH_METHOD_MICROSOFT_LOGIN_LABEL' => 'Etiqueta para o botão de autenticação Microsoft na página de login',
                'AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION' => 'Descrição da autenticação Microsoft na página de login',
                'AUTH_METHOD_REGISTER_ENABLED' => 'Ativar ou desativar o método de autenticação Registo',
                'AUTH_METHOD_REGISTER_LABEL' => 'Etiqueta para o botão de autenticação Registo na página de login',
                'AUTH_METHOD_REGISTER_DESCRIPTION' => 'Descrição da autenticação Registo na página de login',
                'AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED' => 'Ativar ou desativar o login com número de telefone ou e-mail',
                'AUTH_METHOD_LOGIN_TRADITIONAL_LABEL' => 'Etiqueta para o botão de autenticação Login na página de login',
                'AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION' => 'Descrição da autenticação Login na página de login',
                'AUTH_METHOD_SMS_REGISTER_ENABLED' => 'Ativar ou desativar o registo de autenticação com número de telefone',
                'AUTH_METHOD_SMS_REGISTER_LABEL' => 'Etiqueta para autenticação com número de telefone, no botão da página de login',
                'AUTH_METHOD_SMS_REGISTER_DESCRIPTION' => 'Descrição da autenticação com número de telefone na página de login',
                'SYNC_LDAP_ENABLED' => 'Ativar ou desativar a sincronização com o LDAP.',
                'SYNC_LDAP_SERVER' => 'URL do servidor LDAP.',
                'SYNC_LDAP_BIND_USER_DN' => 'O Nome Distinto (DN) utilizado para ligação ao servidor LDAP.',
                'SYNC_LDAP_BIND_USER_PASSWORD' => 'A palavra-passe do utilizador de ligação no servidor LDAP.',
                'SYNC_LDAP_SEARCH_BASE_DN' => 'O DN base utilizado ao pesquisar no diretório LDAP.',
                'SYNC_LDAP_SEARCH_FILTER' => 'O filtro utilizado ao pesquisar no diretório LDAP. O marcador `@ID` é substituído pelo ID do utilizador.',
                'TOS' => 'Formato dos Termos e Condições',
                'PRIVACY_POLICY' => 'Formato da Política de Privacidade',
                'TOS_LINK' => 'URL dos Termos e Condições',
                'PRIVACY_POLICY_LINK' => 'URL da Política de Privacidade',
                'TOS_EDITOR' => 'Editor de texto dos Termos e Condições',
                'PRIVACY_POLICY_EDITOR' => 'Editor de texto da Política de Privacidade',
                'VALID_DOMAINS_GOOGLE_LOGIN' => 'Quando vazio, permite que todos os domínios se autentiquem. Introduza apenas os domínios que pretende permitir',
                'VALID_DOMAINS_MICROSOFT_LOGIN' => 'Quando vazio, permite que todos os domínios se autentiquem. Introduza apenas os domínios que pretende permitir',
                'PROFILES_ENCRYPTION_TYPE_IOS_ONLY' => 'Tipo de encriptação definido para criação de perfis',
                'CAPPORT_ENABLED' => 'Ativar ou desativar a configuração Capport DHCP',
                'CAPPORT_PORTAL_URL' => 'Domínio da entidade que aloja o serviço',
                'CAPPORT_VENUE_INFO_URL' => 'Domínio para onde o utilizador é redirecionado após clicar na notificação DHCP',
                'SMS_USERNAME' => 'Nome de utilizador do Budget SMS',
                'SMS_USER_ID' => 'ID de utilizador do Budget SMS',
                'SMS_HANDLE' => 'Hash de identificação do Budget SMS',
                'SMS_FROM' => 'Entidade que envia o SMS para os utilizadores',
                'SMS_TIMER_RESEND' => 'Tempo em minutos que o utilizador tem de esperar para reenviar um novo SMS',
                'USER_DELETE_TIME' => 'Tempo em horas para eliminar o utilizador não verificado',
                'TIME_INTERVAL_NOTIFICATION' => 'Intervalo de notificação (em dias) para alertar o utilizador antes de o perfil expirar',
                'DEFAULT_REGION_PHONE_INPUTS' => 'Definir as regiões padrão para os campos de número de telefone',
                'PROFILE_LIMIT_DATE_GOOGLE' => 'Tempo em dias para desativar perfis de utilizadores com login Google',
                'PROFILE_LIMIT_DATE_MICROSOFT' => 'Tempo em dias para desativar perfis de utilizadores com login Microsoft',
                'PROFILE_LIMIT_DATE_SAML' => 'Tempo em dias para desativar perfis de utilizadores com login SAML',
                'PROFILE_LIMIT_DATE_EMAIL' => 'Tempo em dias para desativar perfis de utilizadores com login por e-mail',
                'PROFILE_LIMIT_DATE_SMS' => 'Tempo em dias para desativar perfis de utilizadores com login por SMS',
            ]
        ];
        // phpcs:enable

        return $descriptions[$locale][$settingName] ?? $descriptions['en'][$settingName];
    }

    private function mapSetting($settingName): string
    {
        return match ($settingName) {
            'PAGE_TITLE' => 'title',
            'CUSTOMER_LOGO' => 'customerLogoName',
            'OPENROAMING_LOGO' => 'openroamingLogoName',
            'WALLPAPER_IMAGE' => 'wallpaperImageName',
            'WELCOME_TEXT' => 'welcomeText',
            'WELCOME_DESCRIPTION' => 'welcomeDescription',
            'CONTACT_EMAIL' => 'contactEmail',
            default => $settingName,
        };
    }
}
