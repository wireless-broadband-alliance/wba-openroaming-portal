<?php

use App\Twig\Rules\HashQuoteCustomRule;

$ruleset = new TwigCsFixer\Ruleset\Ruleset();

// You can start from a default standard
$ruleset->addStandard(new TwigCsFixer\Standard\TwigCsFixer());

// Todo: write custom rule to verify if it contains UTF8 chars
$ruleset->removeRule(TwigCsFixer\Rules\String\HashQuoteRule::class);
$ruleset->addRule(new HashQuoteCustomRule());

$config = new TwigCsFixer\Config\Config();
$config->setRuleset($ruleset);

return $config;
