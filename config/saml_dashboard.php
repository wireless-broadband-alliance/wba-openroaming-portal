<?php

return [
    'strict' => false,
    'debug' => false,

    'sp' => [
        'entityId' => $_ENV['SAML_SP_ENTITY_ID'],
        'assertionConsumerService' => [
            'url' => $_ENV['SAML_DASHBOARD_ACS_URL'],
            'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
        ],
    ],

    'idp' => [
        'entityId' => $_ENV['SAML_IDP_ENTITY_ID'],
        'singleSignOnService' => [
            'url' => $_ENV['SAML_IDP_SSO_URL'],
            'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
        ],
        'x509cert' => $_ENV['SAML_IDP_X509_CERT'],
    ],

    'security' => [
        'allowRepeatAttributeName' => true,
    ],
];