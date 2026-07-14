<?php

declare(strict_types=1);

namespace Veloquent\Core\Domain\RuleEngine;

use RuntimeException;
use Kevintherm\Exprc\Lexer;
use Illuminate\Support\Carbon;

/**
 * Extends exprc's Lexer to natively understand Veloquent's DSL syntax.
 *
 * Handles before the default lexer loop:
 *  - `&&` / `||`          → AND / OR tokens
 *  - `?=`                 → CONTAINS operator
 *  - `?&`                 → HASKEY operator
 *  - `@path.to.var`       → IDENTIFIER token (sysvars kept as-is, evaluators interpret them)
 *  - `field->sub`         → IDENTIFIER token (JSON path, read as one unit)
 *  - `daysago(30)` etc.   → VALUE token (date function resolved to string at lex time)
 */
class VeloquentLexer extends Lexer
{
    private const DATE_FUNCTIONS_SIMPLE = [
        'now', 'today', 'yesterday', 'tomorrow',
        'thisweek', 'lastweek', 'nextweek',
        'thismonth', 'lastmonth', 'nextmonth',
        'thisyear', 'lastyear', 'nextyear',
        'startofday', 'endofday',
        'startofweek', 'endofweek',
        'startofmonth', 'endofmonth',
        'startofyear', 'endofyear',
    ];

    private const DATE_FUNCTIONS_PARAM = [
        'daysago', 'daysfromnow',
        'weeksago', 'weeksfromnow',
        'monthsago', 'monthsfromnow',
        'yearsago', 'yearsfromnow',
    ];

    /** @return array{type: string, value: mixed, position: int}|null */
    protected function tokenizeCustom(string $input, int &$cursor): ?array
    {
        $position = $cursor;
        $length = strlen($input);
        $char = $input[$cursor];

        // Numbers as identifiers (to allow literal-on-left like 5 > id)
        if (ctype_digit($char) || ($char === '-' && $cursor + 1 < $length && ctype_digit($input[$cursor + 1]))) {
            $num = '';
            if ($input[$cursor] === '-') {
                $num .= $input[$cursor++];
            }
            while ($cursor < $length && ctype_digit($input[$cursor])) {
                $num .= $input[$cursor++];
            }
            if ($cursor < $length && $input[$cursor] === '.' && $cursor + 1 < $length && ctype_digit($input[$cursor + 1])) {
                $num .= $input[$cursor++];
                while ($cursor < $length && ctype_digit($input[$cursor])) {
                    $num .= $input[$cursor++];
                }
            }

            return $this->token(Lexer::T_IDENTIFIER, '__numeric__' . $num, $position);
        }

        // && → AND
        if ($char === '&' && isset($input[$cursor + 1]) && $input[$cursor + 1] === '&') {
            $cursor += 2;

            return $this->token(Lexer::T_AND, 'AND', $position);
        }

        // || → OR
        if ($char === '|' && isset($input[$cursor + 1]) && $input[$cursor + 1] === '|') {
            $cursor += 2;

            return $this->token(Lexer::T_OR, 'OR', $position);
        }

        // ?= → CONTAINS operator (Veloquent JSON array or substring contains)
        if ($char === '?' && isset($input[$cursor + 1]) && $input[$cursor + 1] === '=') {
            $cursor += 2;

            return $this->token(Lexer::T_OPERATOR, 'CONTAINS', $position);
        }

        // ?& → HASKEY operator (Veloquent JSON key existence check)
        if ($char === '?' && isset($input[$cursor + 1]) && $input[$cursor + 1] === '&') {
            $cursor += 2;

            return $this->token(Lexer::T_OPERATOR, 'HASKEY', $position);
        }

        // !~ → NOT LIKE operator
        if ($char === '!' && isset($input[$cursor + 1]) && $input[$cursor + 1] === '~') {
            $cursor += 2;

            return $this->token(Lexer::T_OPERATOR, '!~', $position);
        }

        // ~ → LIKE operator
        if ($char === '~') {
            $cursor++;

            return $this->token(Lexer::T_OPERATOR, '~', $position);
        }

        // @sysvar.path → single IDENTIFIER token
        if ($char === '@') {
            $cursor++; // consume @
            $path = $this->readSysvarPath($input, $cursor);

            return $this->token(Lexer::T_IDENTIFIER, '@' . $path, $position);
        }

        // Bare-word that might be a date function or a field with -> JSON path
        if (ctype_alpha($char) || $char === '_') {
            return $this->tryReadVeloquentIdentifier($input, $cursor, $position);
        }

        // ! → NOT (but NOT !=)
        if ($char === '!' && (!isset($input[$cursor + 1]) || $input[$cursor + 1] !== '=')) {
            $cursor++;

            return $this->token(Lexer::T_NOT, 'NOT', $position);
        }

        return null;
    }

    /**
     * Reads a bare word and checks:
     *  1. Is it a date function call? → resolve to VALUE token.
     *  2. Does it have a -> JSON path suffix? → return as IDENTIFIER.
     *  3. Otherwise → return null and let the default lexer handle it.
     *
     * @return array{type: string, value: mixed, position: int}|null
     */
    private function tryReadVeloquentIdentifier(string $input, int &$cursor, int $position): ?array
    {
        $length = strlen($input);
        $start = $cursor;

        // Read the first word segment
        $word = '';
        while ($cursor < $length && (ctype_alnum($input[$cursor]) || $input[$cursor] === '_')) {
            $word .= $input[$cursor++];
        }

        $lower = strtolower($word);

        // Date function: bare word immediately followed by '('
        if ($cursor < $length && $input[$cursor] === '(') {
            $allDateFuncs = array_merge(self::DATE_FUNCTIONS_SIMPLE, self::DATE_FUNCTIONS_PARAM);
            $fieldFuncs = ['date', 'year', 'month', 'day', 'time'];

            if (in_array($lower, $allDateFuncs, true)) {
                $cursor++; // consume '('

                $arg = null;
                if (ctype_digit($input[$cursor] ?? '')) {
                    $num = '';
                    while ($cursor < $length && ctype_digit($input[$cursor])) {
                        $num .= $input[$cursor++];
                    }
                    $arg = (int) $num;
                }

                if ($cursor < $length && $input[$cursor] === ')') {
                    $cursor++; // consume ')'
                }

                return $this->token(Lexer::T_VALUE, $this->resolveDateFunction($lower, $arg), $position);
            }

            if (in_array($lower, $fieldFuncs, true)) {
                $cursor++; // consume '('

                // Read field name
                $fieldName = '';
                while ($cursor < $length && (ctype_alnum($input[$cursor]) || $input[$cursor] === '_' || $input[$cursor] === '.')) {
                    $fieldName .= $input[$cursor++];
                }

                if ($cursor < $length && $input[$cursor] === ')') {
                    $cursor++; // consume ')'

                    return $this->token(Lexer::T_IDENTIFIER, $fieldName . '__' . $lower, $position);
                }

                // If not followed by ')', it's not our function, rewind
                $cursor = $start;

                return null;
            }
        }

        // JSON path: word followed by ->
        if ($cursor + 1 < $length && $input[$cursor] === '-' && $input[$cursor + 1] === '>') {
            $identifier = $word;
            while ($cursor + 1 < $length && $input[$cursor] === '-' && $input[$cursor + 1] === '>') {
                $identifier .= '->';
                $cursor += 2;
                // Read the next path segment
                while ($cursor < $length && (ctype_alnum($input[$cursor]) || $input[$cursor] === '_')) {
                    $identifier .= $input[$cursor++];
                }
            }

            return $this->token(Lexer::T_IDENTIFIER, $identifier, $position);
        }

        // Not a Veloquent-specific token — rewind and let the default lexer handle it
        $cursor = $start;

        return null;
    }

    private function resolveDateFunction(string $name, ?int $value): string
    {
        $now = Carbon::now();

        return match ($name) {
            'now'          => $now->toDateTimeString(),
            'today'        => $now->toDateString(),
            'yesterday'    => $now->subDay()->toDateString(),
            'tomorrow'     => $now->addDay()->toDateString(),
            'thisweek'     => $now->startOfWeek()->toDateString(),
            'lastweek'     => $now->subWeek()->startOfWeek()->toDateString(),
            'nextweek'     => $now->addWeek()->startOfWeek()->toDateString(),
            'thismonth'    => $now->startOfMonth()->toDateString(),
            'lastmonth'    => $now->subMonth()->startOfMonth()->toDateString(),
            'nextmonth'    => $now->addMonth()->startOfMonth()->toDateString(),
            'thisyear'     => $now->startOfYear()->toDateString(),
            'lastyear'     => $now->subYear()->startOfYear()->toDateString(),
            'nextyear'     => $now->addYear()->startOfYear()->toDateString(),
            'startofday'   => $now->startOfDay()->toDateTimeString(),
            'endofday'     => $now->endOfDay()->toDateTimeString(),
            'startofweek'  => $now->startOfWeek()->toDateTimeString(),
            'endofweek'    => $now->endOfWeek()->toDateTimeString(),
            'startofmonth' => $now->startOfMonth()->toDateTimeString(),
            'endofmonth'   => $now->endOfMonth()->toDateTimeString(),
            'startofyear'  => $now->startOfYear()->toDateTimeString(),
            'endofyear'    => $now->endOfYear()->toDateTimeString(),
            'daysago'      => $now->subDays($value)->toDateTimeString(),
            'daysfromnow'  => $now->addDays($value)->toDateTimeString(),
            'weeksago'     => $now->subWeeks($value)->toDateTimeString(),
            'weeksfromnow' => $now->addWeeks($value)->toDateTimeString(),
            'monthsago'    => $now->subMonths($value)->toDateTimeString(),
            'monthsfromnow'=> $now->addMonths($value)->toDateTimeString(),
            'yearsago'     => $now->subYears($value)->toDateTimeString(),
            'yearsfromnow' => $now->addYears($value)->toDateTimeString(),
            default        => throw new RuntimeException("Unsupported date function: {$name}"),
        };
    }

    private function readSysvarPath(string $input, int &$cursor): string
    {
        $start = $cursor;
        $length = strlen($input);
        $bracketDepth = 0;
        $quote = null;

        while ($cursor < $length) {
            $char = $input[$cursor];

            if ($quote !== null) {
                if ($char === '\\') {
                    $cursor += 2;
                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                $cursor++;
                continue;
            }

            if ($char === '\'' || $char === '"') {
                if ($bracketDepth === 0) {
                    break;
                }

                $quote = $char;
                $cursor++;
                continue;
            }

            if ($char === '[') {
                $bracketDepth++;
                $cursor++;
                continue;
            }

            if ($char === ']') {
                if ($bracketDepth === 0) {
                    break;
                }

                $bracketDepth--;
                $cursor++;
                continue;
            }

            if ($bracketDepth === 0 && (ctype_space($char) || str_contains('(),=<>!', $char))) {
                break;
            }

            if (
                $bracketDepth === 0
                && !ctype_alnum($char)
                && !in_array($char, ['_', '.'], true)
            ) {
                break;
            }

            $cursor++;
        }

        if ($bracketDepth !== 0 || $quote !== null) {
            throw new \Kevintherm\Exprc\Exceptions\LexerException(sprintf('Malformed system variable path near position %d.', $start));
        }

        return substr($input, $start, $cursor - $start);
    }

    /** @return array{type: string, value: mixed, position: int} */
    private function token(string $type, mixed $value, int $position): array
    {
        return ['type' => $type, 'value' => $value, 'position' => $position];
    }
}
