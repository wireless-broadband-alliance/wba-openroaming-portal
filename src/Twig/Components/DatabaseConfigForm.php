<?php

namespace App\Twig\Components;

use App\DTO\DatabaseConfigDTO;
use App\Form\DatabaseConfigType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
final class DatabaseConfigForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public DatabaseConfigDTO|null $databaseConfigDTO = null;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(DatabaseConfigType::class, $this->databaseConfigDTO);
    }

    #[LiveAction]
    public function validate(): void
    {
        if (!$this->databaseConfigDTO instanceof DatabaseConfigDTO) {
            $this->databaseConfigDTO = new DatabaseConfigDTO();
        }

        // TODO: Validations
    }
}
