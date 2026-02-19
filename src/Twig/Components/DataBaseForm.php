<?php

namespace App\Twig\Components;

use App\DTO\DbSetupDTO;
use App\Form\DbSetupType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
class DataBaseForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public DbSetupDTO|null $dbSetupDTO = null;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(DbSetupType::class, $this->dbSetupDTO);
    }

    #[LiveAction]
    public function validate(): void
    {
        if (!$this->dbSetupDTO instanceof DbSetupDTO) {
            $this->dbSetupDTO = new DbSetupDTO();
        }

        $this->form = $this->createForm(DbSetupType::class, $this->dbSetupDTO);
    }
}
