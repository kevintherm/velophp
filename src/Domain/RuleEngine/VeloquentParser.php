<?php

declare(strict_types=1);

namespace Veloquent\Core\Domain\RuleEngine;

use Kevintherm\Exprc\Parser;
use Kevintherm\Exprc\Ast\Node;
use Kevintherm\Exprc\Ast\IdentifierNode;
use Kevintherm\Exprc\Exceptions\ParserException;

/**
 * Extends exprc's Parser to support Veloquent-specific operators.
 *
 * Injects VeloquentLexer so the parser receives correctly tokenized input
 * (sysvars, JSON paths, date functions already resolved).
 *
 * Adds HASKEY to the set of allowed operators.
 */
class VeloquentParser extends Parser
{
    public function __construct()
    {
        parent::__construct(new VeloquentLexer());
    }

    /**
     * @param string|int|float|bool|null|array<int, mixed>|Node $value
     * @param array{type: string, value: mixed, position: int} $token
     */
    protected function assertOperatorValueCompatibility(string $operator, string|int|float|bool|null|array|Node $value, array $token): void
    {
        // Allow HASKEY
        if ($operator === 'HASKEY') {
            if (!is_string($value) && !$value instanceof IdentifierNode) {
                throw new ParserException(
                    sprintf('Operator HASKEY requires a string value, received %s. At position %d.', gettype($value), (int) $token['position'])
                );
            }

            return;
        }

        // Allow ~ and !~
        if ($operator === '~' || $operator === '!~') {
            if (!is_string($value) && !$value instanceof IdentifierNode) {
                throw new ParserException(
                    sprintf('Operator %s requires a string value, received %s. At position %d.', $operator, gettype($value), (int) $token['position'])
                );
            }

            return;
        }

        parent::assertOperatorValueCompatibility($operator, $value, $token);
    }
}
