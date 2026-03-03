<?php
/**
 * API REST para consultar precios de productos.
 *
 * Autenticación: session PHP activa O header X-API-KEY con la clave configurada.
 *
 * ── Consultar un producto por ID ──────────────────────────────────────────
 * GET /ecommerce/api/consultar_precios.php?producto_id=5
 * GET /ecommerce/api/consultar_precios.php?producto_id=5&alto=120&ancho=200
 *
 * ── Consultar un producto por código ─────────────────────────────────────
 * GET /ecommerce/api/consultar_precios.php?codigo=ETQ-001
 * GET /ecommerce/api/consultar_precios.php?codigo=ETQ-001&alto=120&ancho=200
 *
 * ── Listar todos los productos activos con precios ────────────────────────
 * GET /ecommerce/api/consultar_precios.php?todos=1
 *
 * Respuesta de producto único:
 * {
 *   "producto_id": 5,
 *   "codigo": "ETQ-001",
 *   "nombre": "Cortina Roller",
 *   "categoria_id": 2,
 *   "categoria": "Cortinas",
 *   "tipo_precio": "variable",
 *   "precio_base": 1500.00,
 *   "precio_final": 1275.00,
 *   "descuento_pct": 15.0,
 *   "moneda": "ARS",
 *   "alto": 120,
 *   "ancho": 200,
 *   "medidas_usadas": { "alto_cm": 120, "ancho_cm": 200 }
 * }
 *
 * Respuesta lista completa:
 * {
 *   "productos": [ { ...mismo esquema... }, ... ],
 *   "total": 12
 * }
 *
 * Errores posibles:
 *   400 – Parámetros inválidos o faltantes
 *   403 – Autenticación fallida
 *   404 – Producto no encontrado
 *   500 – Error interno
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/precios_publico.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// ── Autenticación ──────────────────────────────────────────────────────────
session_start();
$apiKey   = $robot_api_key ?? (getenv('GASTOS_API_KEY') ?: 'cambia_esta_clave');
$provided = $_SERVER['HTTP_X_API_KEY'] ?? '';
$hasSession = !empty($_SESSION['user']['id']) || !empty($_SESSION['user_id']);

if (!$hasSession && $provided !== $apiKey) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

// ── Parámetros ─────────────────────────────────────────────────────────────
$producto_id = isset($_GET['producto_id']) ? intval($_GET['producto_id']) : 0;
$codigo      = isset($_GET['codigo'])      ? trim($_GET['codigo'])         : '';
$alto        = isset($_GET['alto'])        ? intval($_GET['alto'])         : 0;
$ancho       = isset($_GET['ancho'])       ? intval($_GET['ancho'])        : 0;
$todos       = !empty($_GET['todos']);

try {
    // Cargar la lista de precios pública (descuentos, etc.)
    $listaId = obtener_lista_precio_publica($pdo);
    $mapas   = cargar_mapas_lista_publica($pdo, $listaId);

    // ── Modo: listar todos ────────────────────────────────────────────────
    if ($todos) {
        $stmt = $pdo->query("
            SELECT p.id, p.codigo, p.nombre, p.tipo_precio, p.precio_base,
                   p.categoria_id, c.nombre AS categoria_nombre
            FROM ecommerce_productos p
            LEFT JOIN ecommerce_categorias c ON c.id = p.categoria_id
            WHERE p.activo = 1
            ORDER BY c.nombre, p.nombre
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $productos = [];
        foreach ($rows as $row) {
            $info = calcular_precio_publico(
                (int)$row['id'],
                $row['categoria_id'] !== null ? (int)$row['categoria_id'] : null,
                (float)$row['precio_base'],
                $listaId,
                $mapas['items'],
                $mapas['categorias']
            );
            $productos[] = [
                'producto_id'   => (int)$row['id'],
                'codigo'        => $row['codigo'],
                'nombre'        => $row['nombre'],
                'categoria_id'  => $row['categoria_id'] !== null ? (int)$row['categoria_id'] : null,
                'categoria'     => $row['categoria_nombre'],
                'tipo_precio'   => $row['tipo_precio'],
                'precio_base'   => (float)$row['precio_base'],
                'precio_final'  => round((float)$info['precio'], 2),
                'descuento_pct' => (float)$info['descuento_pct'],
                'moneda'        => 'ARS',
            ];
        }

        echo json_encode(['productos' => $productos, 'total' => count($productos)]);
        exit;
    }

    // ── Modo: producto único ──────────────────────────────────────────────
    if ($producto_id <= 0 && $codigo === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Debe indicar producto_id, codigo, o todos=1']);
        exit;
    }

    if ($producto_id > 0) {
        $stmt = $pdo->prepare("
            SELECT p.id, p.codigo, p.nombre, p.tipo_precio, p.precio_base,
                   p.categoria_id, c.nombre AS categoria_nombre
            FROM ecommerce_productos p
            LEFT JOIN ecommerce_categorias c ON c.id = p.categoria_id
            WHERE p.id = ? AND p.activo = 1
        ");
        $stmt->execute([$producto_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT p.id, p.codigo, p.nombre, p.tipo_precio, p.precio_base,
                   p.categoria_id, c.nombre AS categoria_nombre
            FROM ecommerce_productos p
            LEFT JOIN ecommerce_categorias c ON c.id = p.categoria_id
            WHERE p.codigo = ? AND p.activo = 1
        ");
        $stmt->execute([$codigo]);
    }

    $producto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$producto) {
        http_response_code(404);
        echo json_encode(['error' => 'Producto no encontrado']);
        exit;
    }

    $precio_base  = (float)$producto['precio_base'];
    $precio_final = $precio_base;
    $medidas_usadas = null;

    // Precio variable: buscar en la matriz de precios
    if ($producto['tipo_precio'] === 'variable') {
        if ($alto <= 0 || $ancho <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Debe especificar alto y ancho para productos con precio variable']);
            exit;
        }

        $rango = 50; // cm de tolerancia inicial para acotar el escaneo
        $stmt2 = $pdo->prepare("
            SELECT alto_cm, ancho_cm, precio
            FROM ecommerce_matriz_precios
            WHERE producto_id = ?
              AND alto_cm  BETWEEN ? AND ?
              AND ancho_cm BETWEEN ? AND ?
            ORDER BY ABS(alto_cm - ?) + ABS(ancho_cm - ?)
            LIMIT 1
        ");
        $stmt2->execute([
            (int)$producto['id'],
            max(0, $alto  - $rango), $alto  + $rango,
            max(0, $ancho - $rango), $ancho + $rango,
            $alto, $ancho,
        ]);
        // Si no hubo coincidencia dentro del rango, buscar el más cercano global
        if (!$stmt2->rowCount()) {
            $stmt2 = $pdo->prepare("
                SELECT alto_cm, ancho_cm, precio
                FROM ecommerce_matriz_precios
                WHERE producto_id = ?
                ORDER BY ABS(alto_cm - ?) + ABS(ancho_cm - ?)
                LIMIT 1
            ");
            $stmt2->execute([(int)$producto['id'], $alto, $ancho]);
        }
        $row = $stmt2->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'No hay precios disponibles para esas medidas']);
            exit;
        }

        $precio_base  = (float)$row['precio'];
        $precio_final = $precio_base;
        $medidas_usadas = ['alto_cm' => (int)$row['alto_cm'], 'ancho_cm' => (int)$row['ancho_cm']];
    }

    // Aplicar lista de precios pública (descuentos por producto o categoría)
    $info = calcular_precio_publico(
        (int)$producto['id'],
        $producto['categoria_id'] !== null ? (int)$producto['categoria_id'] : null,
        $precio_base,
        $listaId,
        $mapas['items'],
        $mapas['categorias']
    );
    $precio_final = round((float)$info['precio'], 2);

    echo json_encode([
        'producto_id'   => (int)$producto['id'],
        'codigo'        => $producto['codigo'],
        'nombre'        => $producto['nombre'],
        'categoria_id'  => $producto['categoria_id'] !== null ? (int)$producto['categoria_id'] : null,
        'categoria'     => $producto['categoria_nombre'],
        'tipo_precio'   => $producto['tipo_precio'],
        'precio_base'   => $precio_base,
        'precio_final'  => $precio_final,
        'descuento_pct' => (float)$info['descuento_pct'],
        'moneda'        => 'ARS',
        'alto'          => $alto > 0 ? $alto : null,
        'ancho'         => $ancho > 0 ? $ancho : null,
        'medidas_usadas' => $medidas_usadas,
    ]);

} catch (Exception $e) {
    error_log('consultar_precios_api: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno']);
}
