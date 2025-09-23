<?php declare(strict_types=1);

$root = $argv[1] ?? getcwd();
$root = realpath($root) ?: $root;

$skipDirs = [
    '/vendor/',
    '/node_modules/',
    '/translations-tools/',
    '/tmp/',
    '/.git/',
    '/assets/dist/',
];

$translationFunctions = [
    '__','_e','_ex','_n','_nx','_x',
    'esc_html__','esc_html_e','esc_html_x',
    'esc_attr__','esc_attr_e','esc_attr_x',
    'esc_attr_x','esc_html_x','translate',
    'wp_kses_post','wpautop',
];

$results = [];

$rii = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($rii as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile()) {
        continue;
    }
    $path = $file->getPathname();
    if (substr($path, -4) !== '.php') {
        continue;
    }
    $skip = false;
    foreach ($skipDirs as $skipDir) {
        if (str_contains($path, $skipDir)) {
            $skip = true; break;
        }
    }
    if ($skip) {
        continue;
    }

    $code = file_get_contents($path);
    if ($code === false) {
        fwrite(STDERR, "Could not read $path\n");
        continue;
    }

    $tokens = token_get_all($code);
    $tokenCount = count($tokens);

    for ($i = 0; $i < $tokenCount; $i++) {
        $token = $tokens[$i];
        if (!is_array($token)) {
            continue;
        }
        $tokenId = $token[0];
        if (!in_array($tokenId, [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
            continue;
        }

        $raw = $token[1];
        $line = $token[2] ?? null;

        $value = decodeString($raw);
        if ($value === null) {
            continue;
        }

        if (trim(strip_tags($value)) === '') {
            continue;
        }

        if (!preg_match('/[A-Za-z]/', $value)) {
            continue; // skip strings without letters
        }

        if (shouldSkipLiteral($tokens, $i, $value)) {
            continue;
        }

        $results[] = [
            'file' => substr($path, strlen($root) + 1),
            'line' => $line,
            'text' => $value,
        ];
    }
}

usort($results, function ($a, $b) {
    return $a['file'] <=> $b['file'] ?: $a['line'] <=> $b['line'];
});

$output = [
    'generated_at' => date('c'),
    'root' => $root,
    'count' => count($results),
    'entries' => $results,
];

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

function decodeString(string $literal): ?string {
    if ($literal === '') {
        return null;
    }
    $quote = $literal[0];
    if (($quote !== "'" && $quote !== '"') || substr($literal, -1) !== $quote) {
        return null;
    }
    $inner = substr($literal, 1, -1);
    if ($quote === '"') {
        $inner = stripcslashes($inner);
    } else {
        $inner = str_replace(['\\\\', "\\'"], ['\\', "'"], $inner);
    }
    return trim($inner);
}

function previousMeaningfulToken(array $tokens, int $index): array|string|null {
    for ($i = $index - 1; $i >= 0; $i--) {
        $token = $tokens[$i];
        if (is_array($token)) {
            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            return $token;
        }
        if (trim($token) === '') {
            continue;
        }
        return $token;
    }
    return null;
}

function nextMeaningfulToken(array $tokens, int $index): array|string|null {
    $count = count($tokens);
    for ($i = $index + 1; $i < $count; $i++) {
        $token = $tokens[$i];
        if (is_array($token)) {
            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            return $token;
        }
        if (trim($token) === '') {
            continue;
        }
        return $token;
    }
    return null;
}

function shouldSkipLiteral(array $tokens, int $index, string $value): bool {
    global $translationFunctions;

    // skip short helper strings like 'Y', 'N'
    if (strlen($value) <= 1) {
        return true;
    }

    // skip array keys (next token is =>)
    $next = nextMeaningfulToken($tokens, $index);
    if ($next === '=>') {
        return true;
    }

    // skip function names etc (previous token is -> or ::)
    $prev = previousMeaningfulToken($tokens, $index);
    if ($prev === '->' || $prev === '::') {
        return true;
    }

    // determine if this is part of translation function call
    $func = getEnclosingFunctionName($tokens, $index);
    if ($func !== null && in_array($func, $translationFunctions, true)) {
        return true;
    }

    // heuristics: skip placeholders or slug-like strings
    if (!str_contains($value, ' ') && preg_match('/^[a-z0-9_\-]+$/', $value)) {
        return true;
    }

    return false;
}

function getEnclosingFunctionName(array $tokens, int $index): ?string {
    $depth = 0;
    for ($i = $index - 1; $i >= 0; $i--) {
        $token = $tokens[$i];
        if (is_array($token)) {
            $id = $token[0];
            if (in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            continue;
        }

        if ($token === ')') {
            $depth++;
            continue;
        }

        if ($token === '(') {
            if ($depth === 0) {
                $previous = previousMeaningfulToken($tokens, $i);
                if (is_array($previous) && $previous[0] === T_STRING) {
                    return strtolower($previous[1]);
                }
                if (is_array($previous) && $previous[0] === T_NS_SEPARATOR) {
                    for ($j = $i - 1; $j >= 0; $j--) {
                        $prevToken = $tokens[$j];
                        if (is_array($prevToken)) {
                            if (in_array($prevToken[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_NS_SEPARATOR], true)) {
                                continue;
                            }
                            if ($prevToken[0] === T_STRING) {
                                return strtolower($prevToken[1]);
                            }
                            break;
                        }
                        if (trim((string)$prevToken) === '') {
                            continue;
                        }
                        break;
                    }
                }
                return null;
            }

            if ($depth > 0) {
                $depth--;
            }
        }
    }

    return null;
}
