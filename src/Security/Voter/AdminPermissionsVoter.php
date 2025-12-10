<?php

namespace App\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AdminPermissionsVoter extends Voter
{

    const VIEW = 'VIEW';
    const EDIT = 'EDIT';
    const NO_PERM = 'NO_PERM';

    protected function supports($attribute, $subject): bool
    {
        return in_array($attribute, $subject);
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        return true;
    }
}