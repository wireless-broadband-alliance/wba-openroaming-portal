<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class SamlMetadataValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof SamlMetadata) {
            throw new UnexpectedTypeException($constraint, SamlMetadata::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            // If the value is not a string, raise a violation
            $this->context->buildViolation('The SAML Metadata URL should be a valid string.')
                ->addViolation();
            return;
        }

        // Check if the URL is valid
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $this->context->buildViolation('The provided metadata URL is not a valid URL.')
                ->addViolation();
            return;
        }

        // Fetch the XML metadata
        $metadataXml = @file_get_contents($value);
        if ($metadataXml === false) {
            $this->context->buildViolation('Failed to fetch metadata from the URL.')
                ->addViolation();
            return;
        }

        // Suppress errors and load the XML document
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($metadataXml);
        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();

            $this->context->buildViolation(
                'The metadata XML is not well-formed: ' . implode(
                    ', ',
                    array_map(static fn($e) => $e->message, $errors)
                )
            )->addViolation();
            return;
        }

        // Check for the root element EntityDescriptor
        $ns = 'urn:oasis:names:tc:SAML:2.0:metadata';
        if ($xml->getName() !== 'EntityDescriptor' || $xml->getNamespaces()['md'] !== $ns) {
            $this->context->buildViolation('The root element is not a valid SAML EntityDescriptor.')
                ->addViolation();
            return;
        }

        // Validate required attributes on EntityDescriptor
        $validUntil = isset($xml['validUntil']) ? strtotime((string)$xml['validUntil']) : null;
        if ($validUntil && $validUntil < time()) {
            $this->context->buildViolation(
                'The metadata has expired. ValidUntil: ' . date('Y-m-d H:i:s', $validUntil)
            )->addViolation();
            return;
        }

        $entityID = (string)$xml['entityID'];
        if ($entityID === '' || $entityID === '0') {
            $this->context->buildViolation('The metadata is missing the required entityID attribute.')
                ->addViolation();
            return;
        }

        // Validate SPSSODescriptor element
        $spSsoDescriptor = $xml->children($ns)->SPSSODescriptor;
        if (!$spSsoDescriptor) {
            $this->context->buildViolation('The metadata is missing the SPSSODescriptor element.')
                ->addViolation();
            return;
        }

        // Validate assertion consumer service (ACS)
        $acs = $spSsoDescriptor->AssertionConsumerService;
        if (!$acs) {
            $this->context->buildViolation('The SPSSODescriptor does not define an AssertionConsumerService.')
                ->addViolation();
            return;
        }
    }
}
