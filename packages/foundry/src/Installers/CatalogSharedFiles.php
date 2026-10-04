<?php

declare(strict_types=1);

namespace Raid\Foundry\Installers;

use RuntimeException;

/** Catalog's two shared registrations; never evaluates application PHP. */
final class CatalogSharedFiles
{
    public static function value(string $content, array $contribution): mixed
    {
        if ($contribution['type'] === 'autoload') {
            $json = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            return $json['autoload']['psr-4'][$contribution['key']] ?? null;
        }
        $entries = self::providers($content, $contribution['value']);
        if (count($entries) > 1) {
            throw new RuntimeException('Duplicate Catalog provider registrations in bootstrap/providers.php.');
        }

        return $entries === [] ? null : $contribution['value'];
    }

    public static function patch(string $content, array $contribution, bool $remove = false): string
    {
        $value = self::value($content, $contribution);
        if ($value !== null && $value !== $contribution['value']) {
            throw new RuntimeException('Catalog autoload mapping changed: '.$contribution['key']);
        }
        if (($remove && $value === null) || (! $remove && $value !== null)) {
            return $content;
        }
        if ($contribution['type'] === 'autoload') {
            $json = json_decode($content, false, 512, JSON_THROW_ON_ERROR);
            if (! is_object($json)) {
                throw new RuntimeException('Expected Composer object.');
            }
            $json->autoload ??= new \stdClass;
            if (! is_object($json->autoload)) {
                throw new RuntimeException('Expected Composer autoload object.');
            }
            $json->autoload->{'psr-4'} ??= new \stdClass;
            if (! is_object($json->autoload->{'psr-4'})) {
                throw new RuntimeException('Expected Composer autoload.psr-4 object.');
            }
            if ($remove) {
                unset($json->autoload->{'psr-4'}->{$contribution['key']});
            } else {
                $json->autoload->{'psr-4'}->{$contribution['key']} = $contribution['value'];
            }

            return json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        }
        $entries = self::providers($content, $contribution['value']);
        if ($remove) {
            [$start, $end] = $entries[0];
            $line = strrpos(substr($content, 0, $start), "\n");
            if ($line !== false && trim(substr($content, $line + 1, $start - $line - 1)) === ''
                && preg_match('/^[ \t]*\r?\n/', substr($content, $end), $trailing)) {
                $start = $line + 1;
                $end += strlen($trailing[0]);
            }

            return substr($content, 0, $start).substr($content, $end);
        }
        // providers() validates the literal return array and returns its closing offset.
        $parsed = self::providerTokens($content);
        $closing = $parsed['closing'];
        if ($parsed['entries'] !== []) {
            $end = $parsed['entries'][array_key_last($parsed['entries'])][2];
            if ($content[$end - 1] !== ',') {
                $content = substr($content, 0, $end).','.substr($content, $end);
                $closing++;
            }
        }

        return substr($content, 0, $closing).'    \\'.$contribution['value']."::class,\n".substr($content, $closing);
    }

    private static function providers(string $content, string $provider): array
    {
        $parsed = self::providerTokens($content);
        $matches = [];
        foreach ($parsed['entries'] as [$name, $start, $end]) {
            $parts = explode('\\', $name, 2);
            $import = $parsed['imports'][strtolower($parts[0])] ?? null;
            $resolved = $import === null ? ltrim($name, '\\') : $import.(isset($parts[1]) ? '\\'.$parts[1] : '');
            if (strcasecmp($resolved, $provider) === 0) {
                foreach (token_get_all('<?php '.substr($content, $start, $end - $start)) as $token) {
                    if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                        throw new RuntimeException('Customized Catalog provider expression contains a comment. Manual patch: preserve it and review the registration.');
                    }
                }
                $matches[] = [$start, $end];
            }
        }

        return $matches;
    }

    private static function providerTokens(string $content): array
    {
        $tokens = [];
        $offset = 0;
        foreach (token_get_all($content, TOKEN_PARSE) as $token) {
            $text = is_array($token) ? $token[1] : $token;
            if (! is_array($token) || ! in_array($token[0], [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $tokens[] = [$text, $offset, $offset + strlen($text)];
            }
            $offset += strlen($text);
        }
        $imports = [];
        $i = 0;
        if (array_column(array_slice($tokens, 0, 7), 0) === ['declare', '(', 'strict_types', '=', '1', ')', ';']) {
            $i = 7;
        }
        while (($tokens[$i][0] ?? null) === 'use') {
            $name = $tokens[++$i][0] ?? '';
            if (! preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*$/', $name)) {
                self::unsupported();
            }
            $alias = basename(str_replace('\\', '/', $name));
            if (($tokens[++$i][0] ?? null) === 'as') {
                $alias = $tokens[++$i][0];
                $i++;
            }
            if (($tokens[$i++][0] ?? null) !== ';' || isset($imports[strtolower($alias)])) {
                self::unsupported();
            }
            $imports[strtolower($alias)] = ltrim($name, '\\');
        }
        if (($tokens[$i++][0] ?? null) !== 'return' || ($tokens[$i++][0] ?? null) !== '[') {
            self::unsupported();
        }
        $entries = [];
        while (($tokens[$i][0] ?? null) !== ']') {
            [$name, $start] = $tokens[$i++] ?? ['', 0];
            if (! preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*$/', $name)
                || ($tokens[$i++][0] ?? null) !== '::' || strtolower($tokens[$i++][0] ?? '') !== 'class') {
                self::unsupported();
            }
            $end = $tokens[$i - 1][2];
            if (($tokens[$i][0] ?? null) === ',') {
                $end = $tokens[$i++][2];
            } elseif (($tokens[$i][0] ?? null) !== ']') {
                self::unsupported();
            }
            $entries[] = [$name, $start, $end];
        }
        $closing = $tokens[$i++][1];
        if (($tokens[$i++][0] ?? null) !== ';' || isset($tokens[$i])) {
            self::unsupported();
        }

        return compact('imports', 'entries', 'closing');
    }

    private static function unsupported(): never
    {
        throw new RuntimeException('Cannot safely edit bootstrap/providers.php. Manual patch: review the literal provider array and Catalog registration.');
    }
}
