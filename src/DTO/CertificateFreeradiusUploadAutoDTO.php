<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Vich\UploaderBundle\Mapping\Annotation\Uploadable;
use Vich\UploaderBundle\Mapping\Annotation\UploadableField;

#[Uploadable]
class CertificateFreeradiusUploadAutoDTO
{
  #[NotBlank(message: 'Radius domain cannot be blank. ADD TRANSLATION HERE PLS')]
  public ?string $radiusDomain = null;

  #[UploadableField(mapping: 'letsencrypt_root_ca', fileNameProperty: 'letsEncryptRootPemName')]
  #[NotBlank(message: 'Please upload the Let’s Encrypt root CA PEM file. ADD TRANSLATION HERE PLS')]
  public ?File $letsEncryptRootPemFile = null;
}
