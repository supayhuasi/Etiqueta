<?php
/**
 * API unificada para robot (consultas + escritura controlada + perfil de persona).
 *
 * Autenticación:
 * - Sesión activa, o
 * - Header X-API-KEY con la clave definida en config.php / GASTOS_API_KEY.
 *
 * Uso:
 * GET /ecommerce/api/sistema.php?modulo=empleados
 * GET /ecommerce/api/sistema.php?modulo=gastos&mes=2026-03&page=1&per_page=50
 * GET /ecommerce/api/sistema.php?modulo=pedidos&id=123
 * GET /ecommerce/api/sistema.php?persona=1&q=juan
 * GET /ecommerce/api/sistema.php?modulos=1
 *
 * Escritura (JSON):
 * POST /ecommerce/api/sistema.php
 * {
 *   "accion":"crear|actualizar",
 *   "modulo":"gastos",
 *   "id":123,
 *   "data":{...}
 * }
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

session_start();

$apiKey = $robot_api_key ?? (getenv('GASTOS_API_KEY') ?: 'cambia_esta_clave');
$provided = $_SERVER['HTTP_X_API_KEY'] ?? '';
$hasSession = !empty($_SESSION['user']['id']) || !empty($_SESSION['user_id']);

if (!$hasSession && $provided !== $apiKey) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$MODULES = [
    'usuarios' => [
        'table' => 'usuarios',
        'search' => ['usuario', 'nombre', 'email'],
        'filters' => ['rol_id', 'activo'],
        'date_fields' => ['fecha_creacion', 'created_at']
    ],
    'empleados' => [
        'table' => 'empleados',
        'search' => ['nombre', 'email', 'puesto', 'departamento', 'documento'],
        'filters' => ['activo'],
        'date_fields' => ['fecha_ingreso', 'fecha_creacion', 'created_at']
    ],
    'asistencias' => [
        'table' => 'asistencias',
        'search' => ['estado', 'observaciones'],
        'filters' => ['empleado_id', 'estado', 'creado_por'],
        'date_fields' => ['fecha', 'fecha_creacion']
    ],
    'gastos' => [
        'table' => 'gastos',
        'search' => ['numero_gasto', 'descripcion', 'observaciones'],
        'filters' => ['tipo_gasto_id', 'estado_gasto_id', 'empleado_id', 'usuario_registra'],
        'date_fields' => ['fecha', 'fecha_creacion']
    ],
    'cheques' => [
        'table' => 'cheques',
        'search' => ['numero_cheque', 'beneficiario', 'banco', 'estado'],
        'filters' => ['estado'],
        'date_fields' => ['fecha_emision', 'fecha_pago', 'fecha_creacion']
    ],
    'sueldos' => [
        'table' => 'pagos_sueldos',
        'search' => ['mes_pago'],
        'filters' => ['empleado_id', 'mes_pago'],
        'date_fields' => ['fecha_pago', 'fecha_creacion']
    ],
    'pedidos' => [
        'table' => 'ecommerce_pedidos',
        'search' => ['numero_pedido', 'estado', 'metodo_pago'],
        'filters' => ['cliente_id', 'estado'],
        'date_fields' => ['fecha_pedido', 'fecha_creacion']
    ],
    'ordenes_produccion' => [
        'table' => 'ecommerce_ordenes_produccion',
        'search' => ['estado', 'notas'],
        'filters' => ['pedido_id', 'estado'],
        'date_fields' => ['fecha_entrega', 'fecha_creacion', 'fecha_actualizacion']
    ],
    'produccion_items' => [
        'table' => 'ecommerce_produccion_items_barcode',
        'search' => ['codigo_barcode', 'estado', 'observaciones'],
        'filters' => ['orden_produccion_id', 'pedido_item_id', 'estado', 'usuario_inicio', 'usuario_termino'],
        'date_fields' => ['fecha_inicio', 'fecha_termino', 'fecha_creacion']
    ],
    'productos' => [
        'table' => 'ecommerce_productos',
        'search' => ['codigo', 'nombre', 'tipo_precio'],
        'filters' => ['categoria_id', 'activo'],
        'date_fields' => ['fecha_creacion', 'created_at']
    ],
    'materiales' => [
        'table' => 'ecommerce_materiales',
        'search' => ['nombre', 'tipo_origen', 'unidad_medida'],
        'filters' => ['activo'],
        'date_fields' => ['fecha_creacion', 'created_at']
    ],
    'clientes' => [
        'table' => 'ecommerce_clientes',
        'search' => ['nombre', 'email', 'telefono'],
        'filters' => ['activo', 'email_verificado'],
        'date_fields' => ['fecha_registro', 'created_at']
    ]
];

$WRITE_MODULES = [
    'asistencias',
    'gastos',
    'cheques',
    'pedidos',
    'ordenes_produccion',
    'produccion_items'
];

function outJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function getIntParam(string $name, int $default = 0): int
{
    if (!isset($_GET[$name]) || $_GET[$name] === '') {
        return $default;
    }
    return (int)$_GET[$name];
}

function columnsOf(PDO $pdo, string $table): array
{
    $stmt = $pdo->query("SHOW COLUMNS FROM {$table}");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $row) {
        $out[] = $row['Field'];
    }
    return $out;
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function firstExisting(array $candidates, array $existing): ?string
{
    foreach ($candidates as $field) {
        if (in_array($field, $existing, true)) {
            return $field;
        }
    }
    return null;
}

function sanitizeRow(array $row): array
{
    $sensitiveKeys = [
        'password', 'password_hash', 'token', 'api_key', 'clave', 'secret', 'client_secret',
        'smtp_pass', 'remember_token', 'email_verificacion_token', 'google_id_token'
    ];

    foreach ($row as $key => $value) {
        $low = strtolower((string)$key);
        foreach ($sensitiveKeys as $sensitive) {
            if (strpos($low, $sensitive) !== false) {
                unset($row[$key]);
                continue 2;
            }
        }
    }

    return $row;
}

function allowedWriteColumns(array $columns): array
{
    $blocked = [
        'id', 'password', 'password_hash', 'token', 'api_key', 'clave', 'secret',
        'created_at', 'updated_at', 'fecha_creacion', 'fecha_actualizacion'
    ];
    $out = [];
    foreach ($columns as $col) {
        if (in_array($col, $blocked, true)) {
            continue;
        }
        $low = strtolower($col);
        if (
            strpos($low, 'password') !== false ||
            strpos($low, 'token') !== false ||
            strpos($low, 'secret') !== false
        ) {
            continue;
        }
        $out[] = $col;
    }
    return $out;
}

function personMatches(PDO $pdo, string $term, int $limit = 20): array
{
    $termLike = '%' . $term . '%';
    $items = [];

    if (tableExists($pdo, 'empleados')) {
        $stmt = $pdo->prepare("SELECT id, nombre, email, puesto, departamento, activo FROM empleados WHERE nombre LIKE ? ORDER BY activo DESC, nombre ASC LIMIT {$limit}");
        $stmt->execute([$termLike]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $items[] = [
                'origen' => 'empleado',
                'persona_id' => (int)$row['id'],
                'nombre' => $row['nombre'],
                'email' => $row['email'] ?? null,
                'meta' => [
                    'puesto' => $row['puesto'] ?? null,
                    'departamento' => $row['departamento'] ?? null,
                    'activo' => isset($row['activo']) ? (int)$row['activo'] : null
                ]
            ];
        }
    }

    if (tableExists($pdo, 'usuarios')) {
        $stmt = $pdo->prepare("SELECT id, nombre, usuario, email, activo FROM usuarios WHERE nombre LIKE ? OR usuario LIKE ? ORDER BY activo DESC, nombre ASC LIMIT {$limit}");
        $stmt->execute([$termLike, $termLike]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $items[] = [
                'origen' => 'usuario',
                'persona_id' => (int)$row['id'],
                'nombre' => $row['nombre'] ?: ($row['usuario'] ?? 'Usuario'),
                'email' => $row['email'] ?? null,
                'meta' => [
                    'usuario' => $row['usuario'] ?? null,
                    'activo' => isset($row['activo']) ? (int)$row['activo'] : null
                ]
            ];
        }
    }

    if (tableExists($pdo, 'ecommerce_clientes')) {
        $stmt = $pdo->prepare("SELECT id, nombre, email, telefono, activo FROM ecommerce_clientes WHERE nombre LIKE ? OR email LIKE ? ORDER BY activo DESC, nombre ASC LIMIT {$limit}");
        $stmt->execute([$termLike, $termLike]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $items[] = [
                'origen' => 'cliente',
                'persona_id' => (int)$row['id'],
                'nombre' => $row['nombre'] ?: 'Cliente',
                'email' => $row['email'] ?? null,
                'meta' => [
                    'telefono' => $row['telefono'] ?? null,
                    'activo' => isset($row['activo']) ? (int)$row['activo'] : null
                ]
            ];
        }
    }

    return $items;
}

function personProfile(PDO $pdo, string $origen, int $id): array
{
    if ($id <= 0) {
        return ['perfil' => null, 'relaciones' => []];
    }

    if ($origen === 'empleado') {
        $perfil = null;
        if (tableExists($pdo, 'empleados')) {
            $stmt = $pdo->prepare('SELECT * FROM empleados WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $perfil = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $rel = [];

        if (tableExists($pdo, 'asistencias')) {
            $stmt = $pdo->prepare('SELECT * FROM asistencias WHERE empleado_id = ? ORDER BY fecha DESC, id DESC LIMIT 20');
            $stmt->execute([$id]);
            $rel['asistencias'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if (tableExists($pdo, 'pagos_sueldos')) {
            $stmt = $pdo->prepare('SELECT * FROM pagos_sueldos WHERE empleado_id = ? ORDER BY mes_pago DESC, id DESC LIMIT 12');
            $stmt->execute([$id]);
            $rel['sueldos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if (tableExists($pdo, 'gastos')) {
            $stmt = $pdo->prepare('SELECT * FROM gastos WHERE empleado_id = ? ORDER BY fecha DESC, id DESC LIMIT 20');
            $stmt->execute([$id]);
            $rel['gastos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return ['perfil' => $perfil, 'relaciones' => $rel];
    }

    if ($origen === 'usuario') {
        $perfil = null;
        if (tableExists($pdo, 'usuarios')) {
            $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $perfil = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $rel = [];
        if (tableExists($pdo, 'gastos')) {
            $stmt = $pdo->prepare('SELECT * FROM gastos WHERE usuario_registra = ? ORDER BY fecha DESC, id DESC LIMIT 20');
            $stmt->execute([$id]);
            $rel['gastos_registrados'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        if (tableExists($pdo, 'asistencias')) {
            $stmt = $pdo->prepare('SELECT * FROM asistencias WHERE creado_por = ? ORDER BY fecha DESC, id DESC LIMIT 20');
            $stmt->execute([$id]);
            $rel['asistencias_cargadas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        if (tableExists($pdo, 'ecommerce_produccion_items_barcode')) {
            $stmt = $pdo->prepare('SELECT * FROM ecommerce_produccion_items_barcode WHERE usuario_inicio = ? OR usuario_termino = ? ORDER BY id DESC LIMIT 20');
            $stmt->execute([$id, $id]);
            $rel['produccion_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return ['perfil' => $perfil, 'relaciones' => $rel];
    }

    if ($origen === 'cliente') {
        $perfil = null;
        if (tableExists($pdo, 'ecommerce_clientes')) {
            $stmt = $pdo->prepare('SELECT * FROM ecommerce_clientes WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $perfil = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $rel = [];
        if (tableExists($pdo, 'ecommerce_pedidos')) {
            $stmt = $pdo->prepare('SELECT * FROM ecommerce_pedidos WHERE cliente_id = ? ORDER BY fecha_pedido DESC, id DESC LIMIT 20');
            $stmt->execute([$id]);
            $rel['pedidos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        if (tableExists($pdo, 'ecommerce_pedido_pagos')) {
            $stmt = $pdo->prepare('SELECT * FROM ecommerce_pedido_pagos WHERE cliente_id = ? ORDER BY id DESC LIMIT 20');
            try {
                $stmt->execute([$id]);
                $rel['pagos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $rel['pagos'] = [];
            }
        }

        return ['perfil' => $perfil, 'relaciones' => $rel];
    }

    return ['perfil' => null, 'relaciones' => []];
}

try {
    if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true);
        if (!is_array($body)) {
            outJson(['success' => false, 'message' => 'JSON inválido'], 400);
        }

        $accion = trim((string)($body['accion'] ?? ''));
        $modulo = trim((string)($body['modulo'] ?? ''));
        $data = $body['data'] ?? null;
        $id = isset($body['id']) ? (int)$body['id'] : 0;

        if ($accion === '' || !in_array($accion, ['crear', 'actualizar'], true)) {
            outJson(['success' => false, 'message' => 'Acción inválida. Use crear o actualizar'], 400);
        }
        if ($modulo === '' || !isset($MODULES[$modulo])) {
            outJson(['success' => false, 'message' => 'Módulo inválido'], 400);
        }
        if (!in_array($modulo, $WRITE_MODULES, true)) {
            outJson(['success' => false, 'message' => 'Módulo no habilitado para escritura'], 403);
        }
        if (!is_array($data)) {
            outJson(['success' => false, 'message' => 'El campo data es obligatorio y debe ser objeto'], 400);
        }

        $table = $MODULES[$modulo]['table'];
        $columns = columnsOf($pdo, $table);
        $writable = allowedWriteColumns($columns);

        $clean = [];
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if (in_array($key, $writable, true)) {
                $clean[$key] = $value;
            }
        }

        if (empty($clean)) {
            outJson(['success' => false, 'message' => 'No hay campos válidos para guardar'], 400);
        }

        if ($accion === 'crear') {
            $fields = array_keys($clean);
            $placeholders = array_fill(0, count($fields), '?');
            $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($clean));

            $newId = (int)$pdo->lastInsertId();
            $item = null;
            if ($newId > 0 && in_array('id', $columns, true)) {
                $stmt = $pdo->prepare('SELECT * FROM ' . $table . ' WHERE id = ? LIMIT 1');
                $stmt->execute([$newId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $item = $row ? sanitizeRow($row) : null;
            }

            outJson([
                'success' => true,
                'accion' => 'crear',
                'modulo' => $modulo,
                'id' => $newId > 0 ? $newId : null,
                'item' => $item
            ]);
        }

        if (!in_array('id', $columns, true)) {
            outJson(['success' => false, 'message' => 'El módulo no soporta actualización por id'], 400);
        }
        if ($id <= 0) {
            outJson(['success' => false, 'message' => 'Debe indicar id para actualizar'], 400);
        }

        $set = [];
        $params = [];
        foreach ($clean as $field => $value) {
            $set[] = $field . ' = ?';
            $params[] = $value;
        }
        $params[] = $id;

        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $set) . ' WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $stmt = $pdo->prepare('SELECT * FROM ' . $table . ' WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        outJson([
            'success' => true,
            'accion' => 'actualizar',
            'modulo' => $modulo,
            'id' => $id,
            'item' => $row ? sanitizeRow($row) : null
        ]);
    }

    if (!empty($_GET['modulos'])) {
        $list = [];
        foreach ($MODULES as $name => $cfg) {
            $list[] = [
                'modulo' => $name,
                'tabla' => $cfg['table'],
                'filtros' => array_merge(['id', 'q', 'mes', 'desde', 'hasta', 'page', 'per_page', 'campos'], $cfg['filters'])
            ];
        }
        outJson([
            'success' => true,
            'modulos' => $list,
            'escritura_habilitada' => $WRITE_MODULES,
            'acciones_escritura' => ['crear', 'actualizar'],
            'busqueda_persona' => [
                'query' => '/ecommerce/api/sistema.php?persona=1&q=juan',
                'detalle' => '/ecommerce/api/sistema.php?persona=1&origen=empleado&persona_id=3'
            ]
        ]);
    }

    $personaMode = !empty($_GET['persona']) || (($_GET['modulo'] ?? '') === 'persona');
    if ($personaMode) {
        $q = trim((string)($_GET['q'] ?? $_GET['nombre'] ?? ''));
        $origen = trim((string)($_GET['origen'] ?? ''));
        $personaId = (int)($_GET['persona_id'] ?? 0);

        if ($personaId > 0 && in_array($origen, ['empleado', 'usuario', 'cliente'], true)) {
            $info = personProfile($pdo, $origen, $personaId);
            $perfil = $info['perfil'] ? sanitizeRow($info['perfil']) : null;

            $rel = [];
            foreach ($info['relaciones'] as $k => $rows) {
                $tmp = [];
                foreach ($rows as $row) {
                    $tmp[] = sanitizeRow($row);
                }
                $rel[$k] = $tmp;
            }

            outJson([
                'success' => true,
                'modo' => 'persona_detalle',
                'origen' => $origen,
                'persona_id' => $personaId,
                'perfil' => $perfil,
                'relaciones' => $rel
            ]);
        }

        if ($q === '') {
            outJson(['success' => false, 'message' => 'Debe indicar q o nombre para buscar persona'], 400);
        }

        $matches = personMatches($pdo, $q, 20);
        outJson([
            'success' => true,
            'modo' => 'persona_busqueda',
            'q' => $q,
            'total' => count($matches),
            'personas' => $matches
        ]);
    }

    $modulo = trim((string)($_GET['modulo'] ?? ''));
    if ($modulo === '') {
        outJson([
            'success' => false,
            'message' => 'Debe indicar el parámetro modulo. Use modulos=1 para ver opciones.'
        ], 400);
    }

    if (!isset($MODULES[$modulo])) {
        outJson(['success' => false, 'message' => 'Módulo no soportado'], 400);
    }

    $cfg = $MODULES[$modulo];
    $table = $cfg['table'];

    $columns = columnsOf($pdo, $table);
    if (empty($columns)) {
        outJson(['success' => false, 'message' => 'No se pudo leer la estructura del módulo'], 500);
    }

    $selectColumns = $columns;
    $camposRaw = trim((string)($_GET['campos'] ?? ''));
    if ($camposRaw !== '') {
        $requested = array_values(array_filter(array_map('trim', explode(',', $camposRaw))));
        $requestedValid = array_values(array_intersect($requested, $columns));
        if (!empty($requestedValid)) {
            $selectColumns = $requestedValid;
        }
    }

    $where = ['1=1'];
    $params = [];
    $appliedFilters = [];

    if (in_array('id', $columns, true)) {
        $id = getIntParam('id');
        if ($id > 0) {
            $where[] = 'id = ?';
            $params[] = $id;
            $appliedFilters['id'] = $id;
        }
    }

    foreach ($cfg['filters'] as $field) {
        if (!in_array($field, $columns, true)) {
            continue;
        }
        if (!isset($_GET[$field]) || $_GET[$field] === '') {
            continue;
        }
        $value = trim((string)$_GET[$field]);
        $where[] = "{$field} = ?";
        $params[] = $value;
        $appliedFilters[$field] = $value;
    }

    $q = trim((string)($_GET['q'] ?? ''));
    if ($q !== '') {
        $searchable = [];
        foreach ($cfg['search'] as $field) {
            if (in_array($field, $columns, true)) {
                $searchable[] = $field;
            }
        }
        if (!empty($searchable)) {
            $qWhere = [];
            foreach ($searchable as $field) {
                $qWhere[] = "{$field} LIKE ?";
                $params[] = "%{$q}%";
            }
            $where[] = '(' . implode(' OR ', $qWhere) . ')';
            $appliedFilters['q'] = $q;
        }
    }

    $dateField = firstExisting($cfg['date_fields'], $columns);
    $mes = trim((string)($_GET['mes'] ?? ''));
    if ($mes !== '' && $dateField !== null) {
        $where[] = "DATE_FORMAT({$dateField}, '%Y-%m') = ?";
        $params[] = $mes;
        $appliedFilters['mes'] = $mes;
    }

    $desde = trim((string)($_GET['desde'] ?? ''));
    if ($desde !== '' && $dateField !== null) {
        $where[] = "DATE({$dateField}) >= ?";
        $params[] = $desde;
        $appliedFilters['desde'] = $desde;
    }

    $hasta = trim((string)($_GET['hasta'] ?? ''));
    if ($hasta !== '' && $dateField !== null) {
        $where[] = "DATE({$dateField}) <= ?";
        $params[] = $hasta;
        $appliedFilters['hasta'] = $hasta;
    }

    $page = max(1, getIntParam('page', 1));
    $perPage = getIntParam('per_page', 50);
    if ($perPage <= 0) {
        $perPage = 50;
    }
    $perPage = min($perPage, 200);

    $offset = ($page - 1) * $perPage;

    $orderBy = 'id DESC';
    if (!in_array('id', $columns, true)) {
        $orderBy = $columns[0] . ' DESC';
    }
    if ($dateField !== null) {
        $orderBy = $dateField . ' DESC';
    }

    $selectExpr = implode(', ', $selectColumns);
    $whereExpr = implode(' AND ', $where);

    $sqlCount = "SELECT COUNT(*) FROM {$table} WHERE {$whereExpr}";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    $sql = "SELECT {$selectExpr} FROM {$table} WHERE {$whereExpr} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    foreach ($rows as $row) {
        $items[] = sanitizeRow($row);
    }

    $pages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

    outJson([
        'success' => true,
        'modulo' => $modulo,
        'tabla' => $table,
        'filtros_aplicados' => $appliedFilters,
        'paginacion' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'pages' => $pages
        ],
        'items' => $items
    ]);
} catch (PDOException $e) {
    error_log('sistema_api PDO error: ' . $e->getMessage());
    outJson(['success' => false, 'message' => 'Error interno'], 500);
} catch (Exception $e) {
    error_log('sistema_api error: ' . $e->getMessage());
    outJson(['success' => false, 'message' => 'Error interno'], 500);
}
