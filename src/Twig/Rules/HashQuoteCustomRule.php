<?php

/**
 * Custom rule for Twig CS fixer
 */

namespace App\Twig\Rules;

use TwigCsFixer\Rules\AbstractFixableRule;
use TwigCsFixer\Token\Token;
use TwigCsFixer\Token\Tokenizer;
use TwigCsFixer\Token\Tokens;
use Webmozart\Assert\Assert;

final class HashQuoteCustomRule extends AbstractFixableRule
{
    #[\Override]
    protected function process(int $tokenIndex, Tokens $tokens): void
    {
        $token = $tokens->get($tokenIndex);
        if (!$token->isMatching(Token::PUNCTUATION_TYPE, ':')) {
            return;
        }
        $previous = $tokens->findPrevious(Token::EMPTY_TOKENS, $tokenIndex - 1, exclude: true);
        Assert::notFalse($previous, 'A punctuation cannot be the first token.');


        $this->stringShouldBeName($previous, $tokens);
    }

    private function stringShouldBeName(int $tokenIndex, Tokens $tokens): void
    {
        $token = $tokens->get($tokenIndex);
        if (!$token->isMatching(Token::STRING_TYPE)) {
            return;
        }

        $expectedValue = substr($token->getValue(), 1, -1);
        if (
            !$this->isInteger($expectedValue)
            && 1 !== preg_match('/^' . Tokenizer::NAME_PATTERN . '$/', $expectedValue)
        ) {
            return;
        }

        if (
            strpbrk(
                $expectedValue,
                "àáâäãåąčćęèéêëėįìíîïłńòóôöõøùúûüųūÿýżźñçčšžÀÁÂÄÃÅĄĆČĖĘÈÉÊËÌÍÎÏĮŁŃÒÓÔÖÕØÙÚÛÜŲŪŸÝŻŹÑßÇŒÆČŠŽ∂ð"
            ) !== false
        ) {
            return;
        }

        $fixer = $this->addFixableError(
            \sprintf('The hash key "%s" does not require to be quoted.', $expectedValue),
            $token
        );

        $fixer?->replaceToken($tokenIndex, $expectedValue);
    }

    private function isInteger(string $value): bool
    {
        return $value === (string)(int)$value;
    }
}
