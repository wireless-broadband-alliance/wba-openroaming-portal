<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Vich\UploaderBundle\Mapping\Annotation\Uploadable;
use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\Constraints as CustomAssert;

#[Uploadable]
class CertificateFreeradiusUploadAutoDTO
{
  public array $notices = [];

  #[NotBlank(message: 'Radius domain cannot be blank. ADD TRANSLATION HERE PLS')]
  public ?string $radiusDomain = null;

  #[NotBlank(message: 'nullCA')]
  #[Assert\File(
      maxSize: '5M',
      mimeTypes: [
          'application/x-x509-ca-cert',
          'application/x-pem-file',
          'application/octet-stream',
          'text/plain',
      ],
      notFoundMessage: 'nullCA',
      mimeTypesMessage: 'invalidFileTypeCA'
  )]
  #[CustomAssert\ValidPemCertificate]
  #[CustomAssert\ValidRsaCertificate]
  public ?File $letsEncryptRootPemFile = null;
}
