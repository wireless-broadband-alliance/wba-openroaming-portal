<?php

namespace App\DTO;

class CertificateFreeradiusUploadAutoDTO
{
  public array $notices = []; // TODO MAKE WARNINGS LETS ENCRYPT MANUAL UPLOAD
  public ?string $radiusDomain = null;

}
