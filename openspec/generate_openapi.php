<?php
declare(strict_types=1);

/**
 * Generates an OpenAPI 3.0.3 document by scanning API-related PHP endpoints.
 *
 * Usage:
 *   php openspec/generate_openapi.php
 *   php openspec/generate_openapi.php --watch
 *   php openspec/generate_openapi.php --watch --interval=3
 */

const OPENAPI_VERSION = '3.0.3';
const DEFAULT_INTERVAL_SECONDS = 2;

$rootDir = realpath(__DIR__ . '/..');
if ($rootDir === false) {
    fwrite(STDERR, "Unable to resolve project root." . PHP_EOL);
    exit(1);
}

$outputFile = __DIR__ . '/openapi.json';

$watchMode = in_array('--watch', $argv, true);
$interval = DEFAULT_INTERVAL_SECONDS;

foreach ($argv as $arg) {
    if (strpos($arg, '--interval=') === 0) {
        $value = (int)substr($arg, strlen('--interval='));
        if ($value > 0) {
            $interval = $value;
        }
    }
}

$lastFingerprint = null;

do {
    $apiFiles = discoverApiFiles($rootDir);
    $fingerprint = fingerprintFiles($apiFiles);

    if ($fingerprint !== $lastFingerprint) {
        $openapi = buildOpenApiDocument($rootDir, $apiFiles);
        $json = json_encode($openapi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            fwrite(STDERR, "Failed to encode OpenAPI JSON." . PHP_EOL);
            exit(1);
        }

        file_put_contents($outputFile, $json . PHP_EOL);
        echo 'OpenSpec updated: ' . relativePath($rootDir, $outputFile) . ' (' . count($apiFiles) . ' files scanned)' . PHP_EOL;

        $lastFingerprint = $fingerprint;
    }

    if (!$watchMode) {
        break;
    }

    sleep($interval);
} while (true);

function buildOpenApiDocument(string $rootDir, array $apiFiles): array
{
    $paths = [];

    foreach ($apiFiles as $file) {
        $content = file_get_contents($file);
        if ($content === false) {
            continue;
        }

        $pathKey = '/' . str_replace('\\', '/', relativePath($rootDir, $file));
        $methods = detectMethods($content);
        $queryParams = extractNamedParams($content, '/\$_GET\[[\'\"]([a-zA-Z0-9_]+)[\'\"]\]/');
        $postParams = extractNamedParams($content, '/\$_POST\[[\'\"]([a-zA-Z0-9_]+)[\'\"]\]/');
        $dataParams = extractNamedParams($content, '/\$data\[[\'\"]([a-zA-Z0-9_]+)[\'\"]\]/');
        $bodyParams = array_values(array_unique(array_merge($postParams, $dataParams)));
        $statusCodes = detectStatusCodes($content);

        $description = detectDescription($content);
        $authUsesApiKey = (bool)preg_match('/HTTP_X_API_KEY/', $content);
        $authUsesSession = (bool)preg_match('/session_start\s*\(/', $content);

        $tag = detectTagFromPath($pathKey);

        foreach ($methods as $method) {
            $operation = [
                'operationId' => buildOperationId($pathKey, $method),
                'summary' => buildSummary($pathKey, $method),
                'description' => $description,
                'tags' => [$tag],
                'responses' => buildResponses($statusCodes)
            ];

            if ($method === 'get' && !empty($queryParams)) {
                $operation['parameters'] = buildQueryParameters($queryParams, $content);
            }

            if ($method === 'post' && !empty($bodyParams)) {
                $operation['requestBody'] = [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => buildBodySchema($bodyParams, $content)
                        ],
                        'application/x-www-form-urlencoded' => [
                            'schema' => buildBodySchema($bodyParams, $content)
                        ]
                    ]
                ];
            }

            if ($authUsesApiKey || $authUsesSession) {
                $security = [];
                if ($authUsesApiKey) {
                    $security[] = ['ApiKeyAuth' => []];
                }
                if ($authUsesSession) {
                    $security[] = ['SessionCookie' => []];
                }
                if (!empty($security)) {
                    $operation['security'] = $security;
                }
            }

            $paths[$pathKey][$method] = $operation;
        }
    }

    ksort($paths);

    return [
        'openapi' => OPENAPI_VERSION,
        'info' => [
            'title' => 'Etiqueta API',
            'version' => date('Y.m.d.His'),
            'description' => 'OpenSpec generated automatically from current PHP endpoints.'
        ],
        'servers' => [
            [
                'url' => '/',
                'description' => 'Project root'
            ]
        ],
        'paths' => $paths,
        'components' => [
            'securitySchemes' => [
                'ApiKeyAuth' => [
                    'type' => 'apiKey',
                    'in' => 'header',
                    'name' => 'X-API-KEY'
                ],
                'SessionCookie' => [
                    'type' => 'apiKey',
                    'in' => 'cookie',
                    'name' => 'PHPSESSID'
                ]
            ],
            'schemas' => [
                'BaseSuccess' => [
                    'type' => 'object',
                    'properties' => [
                        'success' => ['type' => 'boolean'],
                        'message' => ['type' => 'string']
                    ]
                ]
            ]
        ],
        'x-generatedAt' => date(DATE_ATOM),
        'x-source' => 'openspec/generate_openapi.php'
    ];
}

function discoverApiFiles(string $rootDir): array
{
    $scanRoots = [
        $rootDir . '/api',
        $rootDir . '/ecommerce/api'
    ];

    $files = [];

    foreach ($scanRoots as $scanRoot) {
        if (!is_dir($scanRoot)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof SplFileInfo) {
                continue;
            }
            if (!$fileInfo->isFile()) {
                continue;
            }
            if (strtolower($fileInfo->getExtension()) !== 'php') {
                continue;
            }
            $files[] = $fileInfo->getPathname();
        }
    }

    $singleFiles = [
        $rootDir . '/scan_api.php'
    ];

    foreach ($singleFiles as $file) {
        if (is_file($file)) {
            $files[] = $file;
        }
    }

    $files = array_values(array_unique($files));
    sort($files);

    return $files;
}

function detectMethods(string $content): array
{
    $hasGet = (bool)preg_match('/\$_GET\[[\'\"]/', $content);
    $hasPost = (bool)preg_match('/\$_POST\[[\'\"]|php:\/\/input|\$data\[[\'\"]/', $content);

    $methods = [];

    if ($hasGet) {
        $methods[] = 'get';
    }
    if ($hasPost) {
        $methods[] = 'post';
    }

    if (empty($methods)) {
        $methods[] = 'get';
    }

    return $methods;
}

function extractNamedParams(string $content, string $pattern): array
{
    preg_match_all($pattern, $content, $matches);
    $params = $matches[1] ?? [];

    $clean = [];
    foreach ($params as $param) {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $param)) {
            continue;
        }
        $clean[] = $param;
    }

    $clean = array_values(array_unique($clean));
    sort($clean);

    return $clean;
}

function detectStatusCodes(string $content): array
{
    preg_match_all('/http_response_code\s*\(\s*(\d{3})\s*\)/', $content, $matches);
    $codes = array_map('intval', $matches[1] ?? []);

    if (!in_array(200, $codes, true)) {
        $codes[] = 200;
    }

    $codes = array_values(array_unique($codes));
    sort($codes);

    return $codes;
}

function buildResponses(array $statusCodes): array
{
    $responses = [];

    foreach ($statusCodes as $statusCode) {
        $description = 'Response ' . $statusCode;
        if ($statusCode >= 200 && $statusCode < 300) {
            $description = 'Successful response';
        } elseif ($statusCode >= 400 && $statusCode < 500) {
            $description = 'Client error';
        } elseif ($statusCode >= 500) {
            $description = 'Server error';
        }

        $responses[(string)$statusCode] = [
            'description' => $description,
            'content' => [
                'application/json' => [
                    'schema' => [
                        '$ref' => '#/components/schemas/BaseSuccess'
                    ]
                ]
            ]
        ];
    }

    return $responses;
}

function buildQueryParameters(array $params, string $content): array
{
    $result = [];

    foreach ($params as $param) {
        $result[] = [
            'name' => $param,
            'in' => 'query',
            'required' => isParamRequired($content, $param),
            'schema' => inferSchemaForParam($param)
        ];
    }

    return $result;
}

function buildBodySchema(array $params, string $content): array
{
    $properties = [];
    $required = [];

    foreach ($params as $param) {
        $properties[$param] = inferSchemaForParam($param);
        if (isParamRequired($content, $param)) {
            $required[] = $param;
        }
    }

    $schema = [
        'type' => 'object',
        'properties' => $properties,
        'additionalProperties' => true
    ];

    if (!empty($required)) {
        $schema['required'] = array_values(array_unique($required));
    }

    return $schema;
}

function inferSchemaForParam(string $param): array
{
    $lower = strtolower($param);

    if (str_contains($lower, 'id') || str_contains($lower, 'limite') || str_contains($lower, 'cantidad')) {
        return ['type' => 'integer'];
    }

    if (str_contains($lower, 'fecha')) {
        return [
            'type' => 'string',
            'format' => 'date'
        ];
    }

    return ['type' => 'string'];
}

function isParamRequired(string $content, string $param): bool
{
    $quoted = preg_quote($param, '/');

    $patterns = [
        '/!isset\([^\)]*[\'\"]' . $quoted . '[\'\"][^\)]*\)/',
        '/empty\([^\)]*[\'\"]' . $quoted . '[\'\"][^\)]*\)/',
        '/\[[\'\"]' . $quoted . '[\'\"]\]\s*===\s*[\'\"]\s*[\'\"]/'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            return true;
        }
    }

    return false;
}

function detectDescription(string $content): string
{
    if (preg_match('/\/\*\*(.*?)\*\//s', $content, $m)) {
        $raw = trim($m[1]);
        $lines = preg_split('/\R/', $raw) ?: [];
        $clean = [];

        foreach ($lines as $line) {
            $line = preg_replace('/^\s*\*\s?/', '', $line);
            $line = trim((string)$line);
            if ($line === '') {
                continue;
            }
            $clean[] = $line;
        }

        if (!empty($clean)) {
            return implode(' ', $clean);
        }
    }

    return 'Endpoint auto-detected from source code.';
}

function detectTagFromPath(string $pathKey): string
{
    $parts = explode('/', trim($pathKey, '/'));

    if (count($parts) >= 2 && $parts[0] === 'ecommerce' && $parts[1] === 'api') {
        return 'ecommerce-api';
    }

    if (!empty($parts) && $parts[0] === 'api') {
        return 'api';
    }

    return 'misc';
}

function buildSummary(string $pathKey, string $method): string
{
    $base = basename($pathKey, '.php');
    $base = str_replace(['_', '-'], ' ', $base);
    $base = ucfirst($base);

    return strtoupper($method) . ' ' . $base;
}

function buildOperationId(string $pathKey, string $method): string
{
    $cleanPath = trim($pathKey, '/');
    $cleanPath = str_replace(['/', '.php', '-', '.'], ['_', '', '_', '_'], $cleanPath);

    return strtolower($method . '_' . $cleanPath);
}

function fingerprintFiles(array $files): string
{
    $state = [];

    foreach ($files as $file) {
        $mtime = @filemtime($file);
        $size = @filesize($file);
        $state[] = $file . '|' . (string)$mtime . '|' . (string)$size;
    }

    return sha1(implode('\n', $state));
}

function relativePath(string $rootDir, string $absolutePath): string
{
    $normalizedRoot = rtrim(str_replace('\\', '/', $rootDir), '/');
    $normalizedPath = str_replace('\\', '/', $absolutePath);

    if (strpos($normalizedPath, $normalizedRoot . '/') === 0) {
        return substr($normalizedPath, strlen($normalizedRoot) + 1);
    }

    return ltrim($normalizedPath, '/');
}
