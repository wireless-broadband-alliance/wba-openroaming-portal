<?php

namespace App\Validator\Constraints;

use App\DTO\AccessPointDTO;
use App\Entity\Network;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidAccessPointLocationValidator extends ConstraintValidator
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    /**
     * @throws \JsonException
     * @throws Exception
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidAccessPointLocation) {
            throw new UnexpectedTypeException($constraint, ValidAccessPointLocation::class);
        }

        if (!$value instanceof AccessPointDTO) {
            return;
        }

        if ($value->latitude === null || $value->longitude === null || !$value->network instanceof Network) {
            return;
        }

        $networkGeometry = $value->network->getGeometry();
        if ($networkGeometry === null) {
            $this->context->buildViolation($constraint->noGeometryMessage)
                ->atPath('network')
                ->addViolation();
            return;
        }

        $pointJson = json_encode([
            'type' => 'Point',
            'coordinates' => [(float)$value->longitude, (float)$value->latitude],
        ], JSON_THROW_ON_ERROR);

        $sql = "
                SELECT ST_Contains(
                    geometry,
                    ST_GeomFromGeoJSON(:point_geo, 1, 4326)
                )
                FROM Network
                WHERE id = :network_id
            ";

        try {
            $isInside = (bool) $this->connection->fetchOne($sql, [
                'network_id' => $value->network->getId(),
                'point_geo' => $pointJson,
            ]);

            if (!$isInside) {
                $this->context->buildViolation($constraint->message)
                    ->atPath('latitude')
                    ->addViolation();
            }
        } catch (Exception) {
            $this->context->buildViolation('invalidGeometryFormat')
                ->atPath('latitude')
                ->addViolation();
        }
    }
}
