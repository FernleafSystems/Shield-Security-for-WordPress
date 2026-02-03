<?php declare(strict_types=1);

$options = [
    'min_likelihood' => null,
    'unique' => false,
];

$root = getcwd();

for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];
    if (str_starts_with($arg, '--')) {
        if ($arg === '--unique') {
            $options['unique'] = true;
        } elseif (str_starts_with($arg, '--min-likelihood=')) {
            $value = strtolower(substr($arg, strlen('--min-likelihood=')));
            if (!in_array($value, ['low', 'medium', 'high'], true)) {
                fwrite(STDERR, "Invalid likelihood level: $value\n");
                exit(1);
            }
            $options['min_likelihood'] = $value;
        } else {
            fwrite(STDERR, "Unknown option: $arg\n");
            exit(1);
        }
        continue;
    }

    if ($root === getcwd()) {
        $root = $arg;
        continue;
    }

    fwrite(STDERR, "Unexpected argument: $arg\n");
    exit(1);
}

$root = realpath($root) ?: $root;

$skipDirs = [
    '/vendor/',
    '/node_modules/',
    '/translations-tools/',
    '/tmp/',
    '/.git/',
    '/assets/dist/',
    '/Actions/Debug/SimplePluginTests.php',
    '/Components/Worpdrive/',
];

$translationFunctions = [
    '__','_e','_ex','_n','_nx','_x',
    'esc_html__','esc_html_e','esc_html_x',
    'esc_attr__','esc_attr_e','esc_attr_x',
    'esc_attr_x','esc_html_x','translate',
    'wp_kses_post','wpautop',
];

$brandNames = [
    'silentcaptcha',
    'shield',
    'shield security',
    'shieldpro',
    'wordpress',
    'recaptcha',
    'captcha',
    'crowdsec',
    'contact form 7',
    'the events calendar',
    'wp statistics',
    'windows hello',
    'apple face id',
    'apple touch id',
    'compatible fingerprint readers',
    'fido2 yubikeys',
    'fido2 google titan keys',
    '1password, bitwarden, etc.',
];

$skipStrings = [
    'list alternate outline',
];

$results = [];
$dedupe = [];

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

    if (str_contains($value, '$')) {
        continue;
    }

    if (shouldSkipLiteral($tokens, $i, $value)) {
        continue;
    }

    if (preg_match('/^%[sd][^A-Za-z0-9]+%[sd]$/', $value)) {
        continue;
    }

    if (preg_match('/^-\s*%s$/', trim($value))) {
        continue;
    }

    $likelihood = classifyLiteral($value);
        if ($likelihood === 'skip') {
            continue;
        }

        if ($options['min_likelihood'] !== null) {
            if (likelihoodRank($likelihood) < likelihoodRank($options['min_likelihood'])) {
                continue;
            }
        }

        if ($options['unique']) {
            $hash = strtolower($value);
            if (isset($dedupe[$hash])) {
                continue;
            }
            $dedupe[$hash] = true;
        }

        $results[] = [
            'file' => substr($path, strlen($root) + 1),
            'line' => $line,
            'text' => $value,
            'likelihood' => $likelihood,
            'needs_translation' => needsTranslation($likelihood, $value),
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
    global $translationFunctions, $brandNames, $skipStrings;

    // skip short helper strings like 'Y', 'N'
    if (strlen($value) <= 1) {
        return true;
    }

    $next = nextMeaningfulToken($tokens, $index);
    $prev = previousMeaningfulToken($tokens, $index);

    // skip array keys (next token is =>)
    if ($next === '=>') {
        return true;
    }

    // skip array index lookups $foo['bar']
    if ($next === ']' || $prev === '[') {
        return true;
    }

    // skip function names etc (previous token is -> or ::)
    if ($prev === '->' || $prev === '::') {
        return true;
    }

    // determine if this is part of translation function call
    $func = getEnclosingFunctionName($tokens, $index);
    if ($func !== null && in_array($func, $translationFunctions, true)) {
        return true;
    }

    if ($func === 'class_exists') {
        return true;
    }

    // heuristics: skip placeholders or slug-like strings
    if (!str_contains($value, ' ') && preg_match('/^[a-z0-9_\-\/]+$/', $value)) {
        return true;
    }

    $lowerValue = strtolower(trim($value));
    if (in_array($lowerValue, $brandNames, true)) {
        return true;
    }

    if (in_array($lowerValue, $skipStrings, true)) {
        return true;
    }

    if (preg_match('/^[a-z]+[a-z0-9]*[A-Z][A-Za-z0-9]*$/', $value)) {
        return true;
    }

    if (preg_match('/^[A-Za-z][a-z0-9]+(?:[A-Z][a-z0-9]+)+$/', $value)) {
        return true;
    }

    if (preg_match('/^[A-Z0-9 _-]+$/', $value)) {
        return true;
    }

    $technicalTokens = [
        'action class',
        'action handler',
        'action slug',
        'action nonce',
        'sub_action',
        'slug:',
        'handler',
        'unexpected data',
        'missing action',
    ];

    foreach ($technicalTokens as $token) {
        if (str_contains($lowerValue, $token)) {
            return true;
        }
    }

    if (str_contains($value, '`')) {
        return true;
    }

    if (stripos($value, 'var ') === 0 && str_contains($value, '=')) {
        return true;
    }

    $trimmed = trim($value);
    if (str_starts_with($trimmed, '* @')) {
        return true;
    }

    if (str_contains($value, '{') || str_contains($value, '}')) {
        return true;
    }

    if (preg_match('/^(create|select|show|insert|update|delete|alter|drop|set|where|limit|unlock|help|and|or)\b/i', trim($value))) {
        return true;
    }

    if (str_starts_with(trim($value), '--')) {
        return true;
    }

    if (preg_match('/^%s[^A-Za-z]*%s/', $value)) {
        return true;
    }

    if (preg_match('/^[dDmMyYHhis\-:\s\/]+$/', $value)) {
        return true;
    }

    $trimmed = trim($value);
    if (str_contains($value, ' as ') && (str_contains($value, '.') || str_contains($value, '_'))) {
        return true;
    }

    if (preg_match('/%s\s*:\s*{{/i', $value)) {
        return true;
    }

    if ($trimmed !== '' && preg_match('/^\d+px\s*x\s*\d+px$/i', $trimmed)) {
        return true;
    }

    if (preg_match('/^(order by|offset)\b/i', $trimmed)) {
        return true;
    }

    if ($trimmed !== '' && preg_match('/^%s\s*\(x%s\)$/i', $trimmed)) {
        return true;
    }

    if ($trimmed !== '' && preg_match('/^%s\s*\(v%s\)$/i', $trimmed)) {
        return true;
    }

    if ($trimmed !== '' && preg_match('/^%s\s+ID:%s$/i', $trimmed)) {
        return true;
    }

    if ($trimmed !== '' && preg_match('/^[A-Za-z0-9+\/]+=*$/', $trimmed) && strlen($trimmed) >= 8) {
        return true; // looks like base64
    }

    if (str_contains($lowerValue, 'render') && (str_contains($lowerValue, 'template') || str_contains($lowerValue, 'action'))) {
        return true;
    }

    if (str_contains($lowerValue, 'exception') && str_contains($lowerValue, 'render')) {
        return true;
    }

    if (str_contains($lowerValue, 'api token')) {
        return true;
    }

    if (preg_match('/no-?store|no-?cache|cache-control/i', $value)) {
        return true;
    }

    if (stripos($value, 'drop table') !== false) {
        return true;
    }

    $trimmed = trim($value);
    if (str_ends_with($trimmed, ':') && str_word_count($trimmed) <= 4) {
        return true;
    }

    return false;
}

function classifyLiteral(string $value): string {
    $value = trim($value);

    if ($value === '') {
        return 'skip';
    }

    if (preg_match('/https?:\/\/|\//i', $value)) {
        return 'skip';
    }

    if (preg_match('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/', $value)) {
        return 'skip';
    }

    if (preg_match('/^[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/', $value)) {
        return 'skip';
    }

    if (preg_match('/^[\d\W]+$/u', $value)) {
        return 'skip';
    }

    $wordCount = preg_match_all('/[A-Za-z][A-Za-z\-\']*/', $value) ?: 0;
    if ($wordCount >= 5) {
        return 'high';
    }

    if (str_contains($value, ' ')) {
        return 'high';
    }

    if (preg_match('/[A-Z][a-z]/', $value)) {
        return 'medium';
    }

    if (preg_match('/^[a-z]{4,}$/', $value)) {
        return 'medium';
    }

    return 'low';
}

function likelihoodRank(string $likelihood): int {
    return match ($likelihood) {
        'high' => 3,
        'medium' => 2,
        'low' => 1,
        default => 0,
    };
}

function needsTranslation(string $likelihood, string $value): bool {
    if ($likelihood === 'high') {
        return true;
    }

    if ($likelihood === 'medium') {
        if (str_contains($value, ' ')) {
            return true;
        }
        if (preg_match('/^(?:error|warning|status|message|notice)/i', $value)) {
            return true;
        }
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
    if (in_array(strtolower(trim($value)), $skipStrings, true)) {
        return true;
    }
