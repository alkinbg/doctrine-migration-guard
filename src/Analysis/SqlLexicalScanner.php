<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Analysis;

final class SqlLexicalScanner
{
    public function hasTopLevelKeyword(string $sql, string $keyword): bool
    {
        if ($keyword === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $keyword) !== 1) {
            return false;
        }

        $mask = $this->topLevelMask($sql);
        $pattern = '/(?<![A-Za-z0-9_])'.preg_quote($keyword, '/').'(?![A-Za-z0-9_])/i';

        return preg_match($pattern, $mask) === 1;
    }

    public function hasTopLevelComma(string $sql): bool
    {
        return str_contains($this->topLevelMask($sql), ',');
    }

    public function hasMultipleTopLevelStatements(string $sql): bool
    {
        $mask = $this->topLevelMask($sql);
        $offset = 0;

        while (($semicolon = strpos($mask, ';', $offset)) !== false) {
            $remainder = substr($mask, $semicolon + 1);
            if (preg_match('/[^\s;]/', $remainder) === 1) {
                return true;
            }

            $offset = $semicolon + 1;
        }

        return false;
    }

    public function isLexicallyComplete(string $sql): bool
    {
        return $this->scan($sql)['complete'];
    }

    public function hasExecutableComment(string $sql): bool
    {
        return $this->scan($sql)['executableComment'];
    }

    public function hasModeDependentBackslashEscape(string $sql): bool
    {
        return $this->scan($sql)['modeDependentBackslashEscape'];
    }

    private function topLevelMask(string $sql): string
    {
        return $this->scan($sql)['mask'];
    }

    /** @return array{mask: string, complete: bool, executableComment: bool, modeDependentBackslashEscape: bool} */
    private function scan(string $sql): array
    {
        $length = strlen($sql);
        $mask = '';
        $depth = 0;
        $quote = null;
        $lineComment = false;
        $blockComment = false;
        $unmatchedClosingParenthesis = false;
        $executableComment = false;
        $modeDependentBackslashEscape = false;

        for ($i = 0; $i < $length; ++$i) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : null;
            $third = $i + 2 < $length ? $sql[$i + 2] : null;
            $fourth = $i + 3 < $length ? $sql[$i + 3] : null;

            if ($lineComment) {
                $mask .= $char === "\n" || $char === "\r" ? $char : ' ';
                if ($char === "\n" || $char === "\r") {
                    $lineComment = false;
                }
                continue;
            }

            if ($blockComment) {
                $mask .= ' ';
                if ($char === '*' && $next === '/') {
                    $mask .= ' ';
                    ++$i;
                    $blockComment = false;
                }
                continue;
            }

            if ($quote !== null) {
                $mask .= ' ';

                if ($char === '\\' && $next !== null) {
                    if ($quote !== '`') {
                        $modeDependentBackslashEscape = true;
                    }

                    $mask .= ' ';
                    ++$i;
                    continue;
                }

                if ($char === $quote) {
                    if ($next === $quote) {
                        $mask .= ' ';
                        ++$i;
                        continue;
                    }

                    $quote = null;
                }

                continue;
            }

            if ($char === '#') {
                $lineComment = true;
                $mask .= ' ';
                continue;
            }

            if ($char === '-' && $next === '-' && $third !== null && $this->isWhitespaceOrControl($third)) {
                $lineComment = true;
                $mask .= '  ';
                ++$i;
                continue;
            }

            if ($char === '/' && $next === '*') {
                if ($third === '!' || (($third === 'M' || $third === 'm') && $fourth === '!')) {
                    $executableComment = true;
                }

                $blockComment = true;
                $mask .= '  ';
                ++$i;
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $mask .= ' ';
                continue;
            }

            if ($char === '(') {
                ++$depth;
                $mask .= ' ';
                continue;
            }

            if ($char === ')') {
                if ($depth > 0) {
                    --$depth;
                } else {
                    $unmatchedClosingParenthesis = true;
                }
                $mask .= ' ';
                continue;
            }

            $mask .= $depth === 0 ? $char : ' ';
        }

        return [
            'mask' => $mask,
            'complete' => $quote === null && !$blockComment && $depth === 0 && !$unmatchedClosingParenthesis,
            'executableComment' => $executableComment,
            'modeDependentBackslashEscape' => $modeDependentBackslashEscape,
        ];
    }

    private function isWhitespaceOrControl(string $char): bool
    {
        $ord = ord($char);

        return $ord <= 32 || $ord === 127;
    }
}
