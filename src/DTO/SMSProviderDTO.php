<?php

namespace App\DTO;

use App\Entity\SMSProvider;
use App\Enum\SMSProviderType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class SMSProviderDTO
{
    public ?int $id = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Length(max: 255, maxMessage: 'fieldCannotBeLongerThan')]
    public ?string $name = null;

    #[Assert\NotNull(message: 'fieldCannotBeBlank')]
    public ?SMSProviderType $smsProviderType = null;

    public bool $testMode = false;

    // --- BudgetSMS fields -------------------------------------------------
    // Only required when smsProviderType === BUDGET_SMS (see validate() below).
    // When a second provider type is added, its own fields land here too, each
    // guarded by its own branch in validate() — if this list keeps growing,
    // that's the signal to split provider-specific fields into their own
    // nested DTOs instead of flat properties on this one.

    #[Assert\Length(max: 32, maxMessage: 'fieldCannotBeLongerThan')]
    public ?string $username = null;

    #[Assert\Length(max: 32, maxMessage: 'fieldCannotBeLongerThan')]
    public ?string $userid = null;

    #[Assert\Length(max: 64, maxMessage: 'fieldCannotBeLongerThan')]
    public ?string $handle = null;

    // Length is validated in validateBudgetSms(): the limit depends on whether
    // the sender ID is numeric (16) or alphanumeric (11).
    public ?string $from = null;

    public static function fromEntity(SMSProvider $provider): self
    {
        $dto = new self();
        $dto->id = $provider->getId();
        $dto->name = $provider->getName();
        $dto->smsProviderType = $provider->getSMSProviderType();
        $dto->testMode = $provider->isTestMode();

        foreach ($provider->getSmsProviderParams() as $param) {
            match ($param->getParamType()) {
                'username' => $dto->username = $param->getValue(),
                'userid' => $dto->userid = $param->getValue(),
                'handle' => $dto->handle = $param->getValue(),
                'from' => $dto->from = $param->getValue(),
                default => null,
            };
        }

        return $dto;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->smsProviderType === SMSProviderType::BUDGET_SMS) {
            $this->validateBudgetSms($context);
        }
        // Future provider types add their own validateXxx() branch here.
    }

    private function validateBudgetSms(ExecutionContextInterface $context): void
    {
        if ($this->username === null || trim($this->username) === '') {
            $context->buildViolation('fieldCannotBeBlank')->atPath('username')->addViolation();
        }

        if ($this->userid === null || trim($this->userid) === '') {
            $context->buildViolation('fieldCannotBeBlank')->atPath('userid')->addViolation();
        }

        if ($this->handle === null || trim($this->handle) === '') {
            $context->buildViolation('fieldCannotBeBlank')->atPath('handle')->addViolation();
        }

        if ($this->from === null || trim($this->from) === '') {
            $context->buildViolation('fieldCannotBeBlank')->atPath('from')->addViolation();

            return;
        }

        // BudgetSMS: numeric sender ID max 16 digits, OR alphanumeric max 11 characters
        // (their own error codes 2002/2003 — see the Send SMS error code list).
        $isNumeric = ctype_digit($this->from);
        $limit = $isNumeric ? 16 : 11;

        if (strlen($this->from) > $limit) {
            $context->buildViolation('invalidSenderId')
                ->setParameter('{{ limit }}', (string)$limit)
                ->atPath('from')
                ->addViolation();
        }
    }
}
