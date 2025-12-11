<?php

namespace App\Validator\Constraints;

use App\Enum\SettingName;
use App\Repository\SettingRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class DomainNotWhitelistedValidator extends ConstraintValidator
{
    public function __construct(private readonly SettingRepository $settingRepository)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DomainNotWhitelisted) {
            return;
        }

        // skip empty values (let NotBlank handle those)
        if ($value === null || $value === '') {
            return;
        }


        // Retrieve the valid domains setting from the database
        $validDomainsMicrosoft = $this->settingRepository->findOneBy([
            'name' => SettingName::VALID_DOMAINS_MICROSOFT_LOGIN->value
        ]);

        // Retrieve the valid domains setting from the database
        $validDomainsGoogle = $this->settingRepository->findOneBy([
            'name' => SettingName::VALID_DOMAINS_GOOGLE_LOGIN->value
        ]);

        // Throw an exception if the setting is not found
        if (!is_null($validDomainsMicrosoft)) {
            // If the valid domains setting is empty, allow all domains
            $validDomains = $validDomainsMicrosoft->getValue();

            // Validate whitelist
            if (!empty($validDomains)) {
                // Split the valid domains into an array and trim whitespace
                $validDomains = explode(',', $validDomains);
                $validDomains = array_map(trim(...), $validDomains);

                // Check if the domain is in the list of valid domains
                if (in_array($value, $validDomains, true)) {
                    // Validation
                    $this->context->buildViolation($constraint->message)->addViolation();
                    return;
                }
            }
        }

        // Throw an exception if the setting is not found
        if (!is_null($validDomainsGoogle)) {
            // If the valid domains setting is empty, allow all domains
            $validDomains = $validDomainsGoogle->getValue();

            // Validate whitelist
            if (!empty($validDomains)) {
                // Split the valid domains into an array and trim whitespace
                $validDomains = explode(',', $validDomains);
                $validDomains = array_map(trim(...), $validDomains);

                // Check if the domain is in the list of valid domains
                if (in_array($value, $validDomains, true)) {
                    // Validation
                    $this->context->buildViolation($constraint->message)->addViolation();
                }
            }
        }
    }
}
