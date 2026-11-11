<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/**
 * The configured RADIUS realm cannot produce a valid RADIUS username (empty, or too long to leave room for
 * the random username prefix). Thrown instead of issuing a broken or shared profile.
 */
class InvalidRadiusRealmException extends RuntimeException
{
}
