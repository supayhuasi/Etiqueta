<?php
require 'includes/header.php';

function tabla_existe($pdo, $tabla) {
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$tabla]);
        if ($stmt->fetchColumn()) {
            return true;
        }
    } catch (Exception $e) {
    }

    try {
        $pdo->query("SELECT 1 FROM {$tabla} LIMIT 1");
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function columna_existe($pdo, $tabla, $columna) {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM {$tabla} LIKE ?");
        $stmt->execute([$columna]);
        if ($stmt->fetchColumn()) {
            return true;
        }
    } catch (Exception $e) {
    }

    try {
        $pdo->query("SELECT {$columna} FROM {$tabla} LIMIT 1");
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function asegurar_estructura_minima_instalaciones($pdo) {
    $mensajes = [];

    try {
        if (!tabla_existe($pdo, 'ecommerce_clientes')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_clientes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(150) NULL,
                email VARCHAR(150) NULL,
                telefono VARCHAR(50) NULL,
                activo TINYINT(1) DEFAULT 1,
                fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $mensajes[] = 'Se creó tabla ecommerce_clientes (mínima).';
        }

        if (!tabla_existe($pdo, 'ecommerce_pedidos')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_pedidos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                numero_pedido VARCHAR(50) NULL,
                cliente_id INT NULL,
                envio_nombre VARCHAR(150) NULL,
                envio_telefono VARCHAR(50) NULL,
                envio_direccion VARCHAR(255) NULL,
                envio_localidad VARCHAR(120) NULL,
                envio_provincia VARCHAR(120) NULL,
                envio_codigo_postal VARCHAR(20) NULL,
                estado VARCHAR(30) DEFAULT 'pendiente',
                total DECIMAL(12,2) DEFAULT 0,
                fecha_pedido DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_cliente_id (cliente_id),
                INDEX idx_fecha_pedido (fecha_pedido)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $mensajes[] = 'Se creó tabla ecommerce_pedidos (mínima).';
        }

        if (!tabla_existe($pdo, 'ecommerce_ordenes_produccion')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_ordenes_produccion (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pedido_id INT NOT NULL,
                estado VARCHAR(30) DEFAULT 'pendiente',
                notas TEXT NULL,
                fecha_entrega DATE NULL,
                fecha_instalacion DATE NULL,
                materiales_descontados TINYINT DEFAULT 0,
                fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
                fecha_actualizacion DATETIME NULL,
                INDEX idx_pedido_id (pedido_id),
                INDEX idx_estado (estado),
                INDEX idx_fecha_instalacion (fecha_instalacion)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $mensajes[] = 'Se creó tabla ecommerce_ordenes_produccion (mínima).';
        }

        if (tabla_existe($pdo, 'ecommerce_ordenes_produccion') && !columna_existe($pdo, 'ecommerce_ordenes_produccion', 'fecha_instalacion')) {
            $pdo->exec("ALTER TABLE ecommerce_ordenes_produccion ADD COLUMN fecha_instalacion DATE NULL AFTER fecha_entrega");
            $mensajes[] = 'Se agregó columna fecha_instalacion.';
        }

        if (tabla_existe($pdo, 'ecommerce_ordenes_produccion') && !columna_existe($pdo, 'ecommerce_ordenes_produccion', 'notas_instalacion')) {
            $pdo->exec("ALTER TABLE ecommerce_ordenes_produccion ADD COLUMN notas_instalacion TEXT NULL AFTER fecha_instalacion");
            $mensajes[] = 'Se agregó columna notas_instalacion.';
        }

        if (tabla_existe($pdo, 'ecommerce_ordenes_produccion') && !columna_existe($pdo, 'ecommerce_ordenes_produccion', 'orden_visual')) {
            $pdo->exec("ALTER TABLE ecommerce_ordenes_produccion ADD COLUMN orden_visual INT NOT NULL DEFAULT 0 AFTER notas_instalacion");
            $mensajes[] = 'Se agregó columna orden_visual en órdenes de producción.';
        }

        if (!tabla_existe($pdo, 'ecommerce_instalaciones_manuales')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_instalaciones_manuales (
                id INT AUTO_INCREMENT PRIMARY KEY,
                titulo VARCHAR(180) NOT NULL,
                cliente VARCHAR(150) NULL,
                telefono VARCHAR(50) NULL,
                direccion VARCHAR(255) NULL,
                localidad VARCHAR(120) NULL,
                provincia VARCHAR(120) NULL,
                codigo_postal VARCHAR(20) NULL,
                fecha_instalacion DATE NULL,
                estado VARCHAR(30) DEFAULT 'pendiente',
                notas TEXT NULL,
                fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
                fecha_actualizacion DATETIME NULL,
                INDEX idx_fecha_instalacion (fecha_instalacion),
                INDEX idx_estado (estado)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $mensajes[] = 'Se creó tabla ecommerce_instalaciones_manuales.';
        }

        if (tabla_existe($pdo, 'ecommerce_instalaciones_manuales') && !columna_existe($pdo, 'ecommerce_instalaciones_manuales', 'orden_visual')) {
            $pdo->exec("ALTER TABLE ecommerce_instalaciones_manuales ADD COLUMN orden_visual INT NOT NULL DEFAULT 0 AFTER fecha_instalacion");
            $mensajes[] = 'Se agregó columna orden_visual en instalaciones manuales.';
        }

        if (!tabla_existe($pdo, 'ecommerce_visitas')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_visitas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                titulo VARCHAR(180) NOT NULL,
                descripcion TEXT NULL,
                cliente_nombre VARCHAR(150) NULL,
                telefono VARCHAR(60) NULL,
                direccion VARCHAR(255) NULL,
                fecha_visita DATE NOT NULL,
                hora_visita TIME NULL,
                estado ENUM('pendiente','en_proceso','completada','cancelada') NOT NULL DEFAULT 'pendiente',
                orden_visual INT NOT NULL DEFAULT 0,
                creado_por INT NULL,
                fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                fecha_actualizacion DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_fecha_visita (fecha_visita),
                INDEX idx_estado (estado)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $mensajes[] = 'Se creó tabla ecommerce_visitas.';
        }

        if (tabla_existe($pdo, 'ecommerce_visitas') && !columna_existe($pdo, 'ecommerce_visitas', 'hora_visita')) {
            $pdo->exec("ALTER TABLE ecommerce_visitas ADD COLUMN hora_visita TIME NULL AFTER fecha_visita");
            $mensajes[] = 'Se agregó columna hora_visita en visitas.';
        }

        if (tabla_existe($pdo, 'ecommerce_visitas') && !columna_existe($pdo, 'ecommerce_visitas', 'orden_visual')) {
            $pdo->exec("ALTER TABLE ecommerce_visitas ADD COLUMN orden_visual INT NOT NULL DEFAULT 0 AFTER estado");
            $mensajes[] = 'Se agregó columna orden_visual en visitas.';
        }
    } catch (Exception $e) {
        error_log('asegurar_estructura_minima_instalaciones: ' . $e->getMessage());
        $mensajes[] = 'No se pudo completar auto-reparación de estructura: ' . $e->getMessage();
    }

    return $mensajes;
}

function texto_dia($fecha) {
    if (!$fecha) {
        return 'Sin fecha';
    }

    $dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    $ts = strtotime($fecha);
    return $dias[(int)date('w', $ts)] . ' ' . date('d/m', $ts);
}

function valor_fecha_valido($valor) {
    if ($valor === '' || $valor === null) {
        return true;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $valor);
    return $dt && $dt->format('Y-m-d') === $valor;
}

function proximo_orden_visual($pdo, $tipo, $fecha) {
    $map = [
        'orden' => ['tabla' => 'ecommerce_ordenes_produccion', 'campo_fecha' => 'fecha_instalacion'],
        'manual' => ['tabla' => 'ecommerce_instalaciones_manuales', 'campo_fecha' => 'fecha_instalacion'],
        'visita' => ['tabla' => 'ecommerce_visitas', 'campo_fecha' => 'fecha_visita'],
    ];

    if (!isset($map[$tipo])) {
        return 10;
    }

    $tabla = $map[$tipo]['tabla'];
    $campo_fecha = $map[$tipo]['campo_fecha'];

    if (!tabla_existe($pdo, $tabla) || !columna_existe($pdo, $tabla, 'orden_visual')) {
        return 10;
    }

    if ($fecha === '' || $fecha === null) {
        $stmt = $pdo->query("SELECT COALESCE(MAX(orden_visual), 0) + 10 AS prox FROM {$tabla} WHERE {$campo_fecha} IS NULL");
    } else {
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(orden_visual), 0) + 10 AS prox FROM {$tabla} WHERE {$campo_fecha} = ?");
        $stmt->execute([$fecha]);
    }

    if (!isset($stmt)) {
        return 10;
    }

    $prox = (int)$stmt->fetchColumn();
    return $prox > 0 ? $prox : 10;
}

$hoy = date('Y-m-d');
$en_7_dias = date('Y-m-d', strtotime('+6 days'));

$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$instalacion_desde = $_GET['instalacion_desde'] ?? $hoy;
$instalacion_hasta = $_GET['instalacion_hasta'] ?? $en_7_dias;
$incluir_entregados = !empty($_GET['incluir_entregados']);

$items_instalacion = [];
$error_pagina = '';
$auto_setup_msg = '';
$ok_msg = '';

$mensajes_setup = asegurar_estructura_minima_instalaciones($pdo);
if (!empty($mensajes_setup)) {
    $auto_setup_msg = implode(' ', $mensajes_setup);
}

$tablas_base_ok = tabla_existe($pdo, 'ecommerce_ordenes_produccion') && tabla_existe($pdo, 'ecommerce_pedidos');
$tiene_clientes = tabla_existe($pdo, 'ecommerce_clientes');
$tiene_fecha_instalacion = $tablas_base_ok && columna_existe($pdo, 'ecommerce_ordenes_produccion', 'fecha_instalacion');
$tiene_instalaciones_manuales = tabla_existe($pdo, 'ecommerce_instalaciones_manuales');
$tiene_visitas = tabla_existe($pdo, 'ecommerce_visitas');
$estados_visita_validos = ['pendiente', 'en_proceso', 'completada', 'cancelada'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));

        if ($action === 'eliminar_visita') {
            header('Content-Type: application/json; charset=utf-8');
            $item_id = (int)($_POST['item_id'] ?? 0);
            if (!$tiene_visitas || $item_id <= 0) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'msg' => 'Datos inválidos para eliminar la visita']);
                exit;
            }
            try {
                $stmt = $pdo->prepare("DELETE FROM ecommerce_visitas WHERE id = ? LIMIT 1");
                $stmt->execute([$item_id]);
                if ($stmt->rowCount() < 1) {
                    http_response_code(404);
                    echo json_encode(['ok' => false, 'msg' => 'La visita no existe']);
                    exit;
                }
                echo json_encode(['ok' => true, 'item_id' => $item_id]);
                exit;
            } catch (Exception $e) {
                error_log('eliminar_visita: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['ok' => false, 'msg' => 'No se pudo eliminar la visita']);
                exit;
            }
        }

        if ($action === 'eliminar_orden') {
            header('Content-Type: application/json; charset=utf-8');
            $item_id = (int)($_POST['item_id'] ?? 0);
            if (!$tablas_base_ok || $item_id <= 0) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'msg' => 'Datos inválidos para eliminar la orden']);
                exit;
            }
            try {
                $stmt = $pdo->prepare("DELETE FROM ecommerce_ordenes_produccion WHERE id = ? LIMIT 1");
                $stmt->execute([$item_id]);
                if ($stmt->rowCount() < 1) {
                    http_response_code(404);
                    echo json_encode(['ok' => false, 'msg' => 'La orden no existe']);
                    exit;
                }
                echo json_encode(['ok' => true, 'item_id' => $item_id]);
                exit;
            } catch (Exception $e) {
                error_log('eliminar_orden: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['ok' => false, 'msg' => 'No se pudo eliminar la orden']);
                exit;
            }
        }

    if ($action === 'crear_instalacion_manual') {
        $titulo = trim($_POST['titulo'] ?? '');
        $cliente = trim($_POST['cliente'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $localidad = trim($_POST['localidad'] ?? '');
        $provincia = trim($_POST['provincia'] ?? '');
        $codigo_postal = trim($_POST['codigo_postal'] ?? '');
        $fecha_instalacion = trim($_POST['fecha_instalacion'] ?? '');
        $notas = trim($_POST['notas'] ?? '');

        if (!$tiene_instalaciones_manuales) {
            $ok_msg = 'No existe la tabla de instalaciones manuales.';
        } elseif ($titulo === '') {
            $ok_msg = 'El título de la instalación es obligatorio.';
        } elseif (!valor_fecha_valido($fecha_instalacion)) {
            $ok_msg = 'La fecha de instalación no es válida.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO ecommerce_instalaciones_manuales
                    (titulo, cliente, telefono, direccion, localidad, provincia, codigo_postal, fecha_instalacion, orden_visual, notas)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $orden_visual = proximo_orden_visual($pdo, 'manual', $fecha_instalacion !== '' ? $fecha_instalacion : null);
                $stmt->execute([
                    $titulo,
                    $cliente !== '' ? $cliente : null,
                    $telefono !== '' ? $telefono : null,
                    $direccion !== '' ? $direccion : null,
                    $localidad !== '' ? $localidad : null,
                    $provincia !== '' ? $provincia : null,
                    $codigo_postal !== '' ? $codigo_postal : null,
                    $fecha_instalacion !== '' ? $fecha_instalacion : null,
                    $orden_visual,
                    $notas !== '' ? $notas : null,
                ]);
                $ok_msg = 'Instalación manual creada correctamente.';
            } catch (Exception $e) {
                error_log('crear_instalacion_manual: ' . $e->getMessage());
                $ok_msg = 'No se pudo crear la instalación manual.';
            }
        }

        header('Content-Type: application/json; charset=utf-8');
        if ($ok_msg === 'Instalación manual creada correctamente.') {
            echo json_encode(['ok' => true, 'msg' => $ok_msg]);
        } else {
            http_response_code(500);
            echo json_encode(['ok' => false, 'msg' => $ok_msg]);
        }
        exit;
    }

    if ($action === 'crear_visita') {
        $titulo = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $cliente_nombre = trim($_POST['cliente_nombre'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $fecha_visita = trim($_POST['fecha_visita'] ?? '');
        $hora_visita = trim($_POST['hora_visita'] ?? '');
        $estado = trim($_POST['estado'] ?? 'pendiente');

        if (!$tiene_visitas) {
            $ok_msg = 'No existe la tabla de visitas.';
        } elseif ($titulo === '' || !valor_fecha_valido($fecha_visita) || $fecha_visita === '') {
            $ok_msg = 'El título y la fecha de visita son obligatorios.';
        } elseif ($hora_visita !== '' && !preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $hora_visita)) {
            $ok_msg = 'La hora de visita no es válida.';
        } elseif (!in_array($estado, $estados_visita_validos, true)) {
            $ok_msg = 'El estado de visita no es válido.';
        } else {
            try {
                $orden_visual = proximo_orden_visual($pdo, 'visita', $fecha_visita);
                $hora_visita_sql = $hora_visita !== '' ? ($hora_visita . ':00') : null;
                $creado_por = isset($_SESSION['user']['id']) && is_numeric($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;

                $stmt = $pdo->prepare("INSERT INTO ecommerce_visitas
                    (titulo, descripcion, cliente_nombre, telefono, direccion, fecha_visita, hora_visita, estado, orden_visual, creado_por)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $titulo,
                    $descripcion !== '' ? $descripcion : null,
                    $cliente_nombre !== '' ? $cliente_nombre : null,
                    $telefono !== '' ? $telefono : null,
                    $direccion !== '' ? $direccion : null,
                    $fecha_visita,
                    $hora_visita_sql,
                    $estado,
                    $orden_visual,
                    $creado_por,
                ]);
                $ok_msg = 'Visita cargada correctamente.';
            } catch (Exception $e) {
                error_log('crear_visita_instalaciones: ' . $e->getMessage());
                $ok_msg = 'No se pudo crear la visita.';
            }
        }

        header('Content-Type: application/json; charset=utf-8');
        if ($ok_msg === 'Visita cargada correctamente.') {
            echo json_encode(['ok' => true, 'msg' => $ok_msg]);
        } else {
            http_response_code(500);
            echo json_encode(['ok' => false, 'msg' => $ok_msg]);
        }
        exit;
    }

    if ($action === 'mover_instalacion') {
        header('Content-Type: application/json; charset=utf-8');
        if (empty($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'msg' => 'Sesión expirada. Por favor, vuelve a iniciar sesión.']);
            exit;
        }

        $tipo = $_POST['tipo'] ?? '';
        $item_id = (int)($_POST['item_id'] ?? 0);
        $fecha_destino = trim($_POST['fecha_destino'] ?? '');

        if (!in_array($tipo, ['orden', 'manual', 'visita'], true) || $item_id <= 0 || !valor_fecha_valido($fecha_destino)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'Datos inválidos']);
            exit;
        }

        $fecha_sql = $fecha_destino !== '' ? $fecha_destino : null;

        try {
            if ($tipo === 'orden') {
                if (!$tiene_fecha_instalacion) {
                    throw new RuntimeException('No se puede mover: falta columna fecha_instalacion.');
                }
                $orden_visual = proximo_orden_visual($pdo, 'orden', $fecha_sql);
                $stmt = $pdo->prepare("UPDATE ecommerce_ordenes_produccion SET fecha_instalacion = ?, orden_visual = ?, fecha_actualizacion = NOW() WHERE id = ?");
                $stmt->execute([$fecha_sql, $orden_visual, $item_id]);
            } elseif ($tipo === 'manual') {
                if (!$tiene_instalaciones_manuales) {
                    throw new RuntimeException('No existe la tabla de instalaciones manuales.');
                }
                $orden_visual = proximo_orden_visual($pdo, 'manual', $fecha_sql);
                $stmt = $pdo->prepare("UPDATE ecommerce_instalaciones_manuales SET fecha_instalacion = ?, orden_visual = ?, fecha_actualizacion = NOW() WHERE id = ?");
                $stmt->execute([$fecha_sql, $orden_visual, $item_id]);
            } else {
                if (!$tiene_visitas) {
                    throw new RuntimeException('No existe la tabla de visitas.');
                }
                $orden_visual = proximo_orden_visual($pdo, 'visita', $fecha_sql);
                $stmt = $pdo->prepare("UPDATE ecommerce_visitas SET fecha_visita = ?, orden_visual = ?, fecha_actualizacion = NOW() WHERE id = ?");
                $stmt->execute([$fecha_sql, $orden_visual, $item_id]);
            }

            echo json_encode(['ok' => true]);
            exit;
        } catch (Exception $e) {
            error_log('mover_instalacion: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'msg' => 'No se pudo mover la instalación']);
            exit;
        }
    }

    if ($action === 'editar_instalacion_manual') {
        header('Content-Type: application/json; charset=utf-8');
        if (empty($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'msg' => 'Sesión expirada. Por favor, vuelve a iniciar sesión.']);
            exit;
        }

        $item_id = (int)($_POST['item_id'] ?? 0);
        $titulo = trim($_POST['titulo'] ?? '');
        $cliente = trim($_POST['cliente'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $localidad = trim($_POST['localidad'] ?? '');
        $provincia = trim($_POST['provincia'] ?? '');
        $codigo_postal = trim($_POST['codigo_postal'] ?? '');
        $fecha_instalacion = trim($_POST['fecha_instalacion'] ?? '');
        $notas = trim($_POST['notas'] ?? '');

        if (!$tiene_instalaciones_manuales || $item_id <= 0 || $titulo === '' || !valor_fecha_valido($fecha_instalacion)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'Datos inválidos para actualizar la instalación manual']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE ecommerce_instalaciones_manuales
                SET titulo = ?, cliente = ?, telefono = ?, direccion = ?, localidad = ?, provincia = ?, codigo_postal = ?,
                    fecha_instalacion = ?, notas = ?, fecha_actualizacion = NOW()
                WHERE id = ?");
            $stmt->execute([
                $titulo,
                $cliente !== '' ? $cliente : null,
                $telefono !== '' ? $telefono : null,
                $direccion !== '' ? $direccion : null,
                $localidad !== '' ? $localidad : null,
                $provincia !== '' ? $provincia : null,
                $codigo_postal !== '' ? $codigo_postal : null,
                $fecha_instalacion !== '' ? $fecha_instalacion : null,
                $notas !== '' ? $notas : null,
                $item_id,
            ]);

            echo json_encode([
                'ok' => true,
                'data' => [
                    'item_id' => $item_id,
                    'titulo' => $titulo,
                    'cliente' => $cliente,
                    'telefono' => $telefono,
                    'direccion' => $direccion,
                    'localidad' => $localidad,
                    'provincia' => $provincia,
                    'codigo_postal' => $codigo_postal,
                    'fecha_instalacion' => $fecha_instalacion,
                    'notas' => $notas,
                ],
            ]);
            exit;
        } catch (Exception $e) {
            error_log('editar_instalacion_manual: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'msg' => 'No se pudo actualizar la instalación manual']);
            exit;
        }
    }

    if ($action === 'eliminar_instalacion_manual') {
        header('Content-Type: application/json; charset=utf-8');
        if (empty($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'msg' => 'Sesión expirada. Por favor, vuelve a iniciar sesión.']);
            exit;
        }

        $item_id = (int)($_POST['item_id'] ?? 0);

        if (!$tiene_instalaciones_manuales || $item_id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'Datos inválidos para eliminar la instalación manual']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM ecommerce_instalaciones_manuales WHERE id = ? LIMIT 1");
            $stmt->execute([$item_id]);

            if ($stmt->rowCount() < 1) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'msg' => 'La instalación manual no existe']);
                exit;
            }

            echo json_encode(['ok' => true, 'item_id' => $item_id]);
            exit;
        } catch (Exception $e) {
            error_log('eliminar_instalacion_manual: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'msg' => 'No se pudo eliminar la instalación manual']);
            exit;
        }
    }

    if ($action === 'guardar_texto_tarjeta') {
        header('Content-Type: application/json; charset=utf-8');

        $tipo = trim($_POST['tipo'] ?? '');
        $item_id = (int)($_POST['item_id'] ?? 0);
        $texto = trim($_POST['texto'] ?? '');

        if (!in_array($tipo, ['orden', 'manual', 'visita'], true) || $item_id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'Datos inválidos para guardar texto']);
            exit;
        }

        try {
            if ($tipo === 'orden') {
                $stmt = $pdo->prepare("UPDATE ecommerce_ordenes_produccion SET notas_instalacion = ?, fecha_actualizacion = NOW() WHERE id = ?");
            } elseif ($tipo === 'manual') {
                $stmt = $pdo->prepare("UPDATE ecommerce_instalaciones_manuales SET notas = ?, fecha_actualizacion = NOW() WHERE id = ?");
            } else {
                $stmt = $pdo->prepare("UPDATE ecommerce_visitas SET descripcion = ?, fecha_actualizacion = NOW() WHERE id = ?");
            }

            $stmt->execute([$texto !== '' ? $texto : null, $item_id]);
            echo json_encode(['ok' => true]);
            exit;
        } catch (Exception $e) {
            error_log('guardar_texto_tarjeta: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'msg' => 'No se pudo guardar el texto de la tarjeta']);
            exit;
        }
    }

    if ($action === 'guardar_orden_tarjetas') {
        header('Content-Type: application/json; charset=utf-8');

        $fecha_columna = trim($_POST['fecha_columna'] ?? '');
        $orden_json = trim($_POST['orden'] ?? '[]');

        if (!valor_fecha_valido($fecha_columna)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'Fecha de columna inválida']);
            exit;
        }

        $orden = json_decode($orden_json, true);
        if (!is_array($orden)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'Orden inválido']);
            exit;
        }

        $fecha_sql = $fecha_columna !== '' ? $fecha_columna : null;

        try {
            $pdo->beginTransaction();

            $pos = 10;
            foreach ($orden as $token) {
                if (!is_string($token) || strpos($token, ':') === false) {
                    continue;
                }

                [$tipo, $idTxt] = explode(':', $token, 2);
                $item_id = (int)$idTxt;
                if ($item_id <= 0 || !in_array($tipo, ['orden', 'manual', 'visita'], true)) {
                    continue;
                }

                if ($tipo === 'orden') {
                    $stmt = $pdo->prepare("UPDATE ecommerce_ordenes_produccion SET fecha_instalacion = ?, orden_visual = ?, fecha_actualizacion = NOW() WHERE id = ?");
                } elseif ($tipo === 'manual') {
                    $stmt = $pdo->prepare("UPDATE ecommerce_instalaciones_manuales SET fecha_instalacion = ?, orden_visual = ?, fecha_actualizacion = NOW() WHERE id = ?");
                } else {
                    $stmt = $pdo->prepare("UPDATE ecommerce_visitas SET fecha_visita = ?, orden_visual = ?, fecha_actualizacion = NOW() WHERE id = ?");
                }

                $stmt->execute([$fecha_sql, $pos, $item_id]);
                $pos += 10;
            }

            $pdo->commit();
            echo json_encode(['ok' => true]);
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('guardar_orden_tarjetas: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'msg' => 'No se pudo guardar el orden de tarjetas']);
            exit;
        }
    }
}

$ok_msg = trim($_GET['msg'] ?? $ok_msg);

$dias_tablero = [];
if (!valor_fecha_valido($instalacion_desde) || !valor_fecha_valido($instalacion_hasta) || $instalacion_desde > $instalacion_hasta) {
    $instalacion_desde = $hoy;
    $instalacion_hasta = $en_7_dias;
}

$tablero_desde = $hoy;
$tablero_hasta = $en_7_dias;

if ($instalacion_desde < $tablero_desde) {
    $tablero_desde = $instalacion_desde;
}
if ($instalacion_hasta > $tablero_hasta) {
    $tablero_hasta = $instalacion_hasta;
}

$filtro_instalacion_desde = $tablero_desde;
$filtro_instalacion_hasta = $tablero_hasta;

$cursor = new DateTime($tablero_desde);
$hasta_dt = new DateTime($tablero_hasta);
while ($cursor <= $hasta_dt) {
    $dias_tablero[] = $cursor->format('Y-m-d');
    $cursor->modify('+1 day');
}

$items_por_columna = ['sin_fecha' => []];
foreach ($dias_tablero as $d) {
    $items_por_columna[$d] = [];
}

$total_ordenes = 0;
$total_manuales = 0;
$total_visitas = 0;

try {
    if (!$tablas_base_ok) {
        throw new RuntimeException('Faltan tablas base requeridas (ecommerce_ordenes_produccion / ecommerce_pedidos).');
    }

    $select_cliente = $tiene_clientes ? 'c.nombre AS cliente_nombre' : 'NULL AS cliente_nombre';
    $join_cliente = $tiene_clientes ? 'LEFT JOIN ecommerce_clientes c ON p.cliente_id = c.id' : '';

    $sql_ordenes = "
        SELECT
            op.id AS orden_id,
            op.pedido_id,
            op.estado AS estado_produccion,
            op.fecha_instalacion,
            op.notas_instalacion,
            op.orden_visual,
            p.numero_pedido,
            p.envio_nombre,
            p.envio_telefono,
            p.envio_direccion,
            p.envio_localidad,
            p.envio_provincia,
            p.fecha_pedido AS fecha_creacion,
            {$select_cliente}
        FROM ecommerce_ordenes_produccion op
        JOIN ecommerce_pedidos p ON op.pedido_id = p.id
        {$join_cliente}
        WHERE " . ($incluir_entregados ? "op.estado IN ('terminado','entregado')" : "op.estado = 'terminado'");

    $params_ordenes = [];

    if ($fecha_desde !== '' && valor_fecha_valido($fecha_desde)) {
        $sql_ordenes .= " AND DATE(p.fecha_pedido) >= ?";
        $params_ordenes[] = $fecha_desde;
    }
    if ($fecha_hasta !== '' && valor_fecha_valido($fecha_hasta)) {
        $sql_ordenes .= " AND DATE(p.fecha_pedido) <= ?";
        $params_ordenes[] = $fecha_hasta;
    }

    if ($filtro_instalacion_desde !== '') {
        $sql_ordenes .= " AND (op.fecha_instalacion IS NULL OR op.fecha_instalacion >= ?)";
        $params_ordenes[] = $filtro_instalacion_desde;
    }
    if ($filtro_instalacion_hasta !== '') {
        $sql_ordenes .= " AND (op.fecha_instalacion IS NULL OR op.fecha_instalacion <= ?)";
        $params_ordenes[] = $filtro_instalacion_hasta;
    }

    $sql_ordenes .= " ORDER BY op.fecha_instalacion IS NULL, op.fecha_instalacion ASC, op.orden_visual ASC, p.fecha_pedido DESC, op.id DESC";

    $stmt = $pdo->prepare($sql_ordenes);
    $stmt->execute($params_ordenes);
    $ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($ordenes as $row) {
        $nombre = trim($row['envio_nombre'] ?? '') ?: ($row['cliente_nombre'] ?? 'Sin nombre');
        $localidad = trim($row['envio_localidad'] ?? '');
        if (!empty($row['envio_provincia'])) {
            $localidad .= ($localidad ? ', ' : '') . $row['envio_provincia'];
        }

        $item = [
            'tipo' => 'orden',
            'item_id' => (int)$row['orden_id'],
            'pedido_id' => (int)$row['pedido_id'],
            'titulo' => 'Pedido ' . ($row['numero_pedido'] ?: ('#' . (int)$row['pedido_id'])),
            'subtitulo' => $nombre,
            'telefono' => trim($row['envio_telefono'] ?? ''),
            'direccion' => trim($row['envio_direccion'] ?? ''),
            'localidad' => $localidad,
            'fecha_instalacion' => $row['fecha_instalacion'] ?: '',
            'fecha_creacion' => $row['fecha_creacion'] ?: '',
            'detalle_url' => 'orden_produccion_detalle.php?pedido_id=' . (int)$row['pedido_id'],
            'texto_tarjeta' => trim($row['notas_instalacion'] ?? ''),
            'orden_visual' => (int)($row['orden_visual'] ?? 0),
        ];

        $clave = (!empty($item['fecha_instalacion']) && isset($items_por_columna[$item['fecha_instalacion']]))
            ? $item['fecha_instalacion']
            : 'sin_fecha';

        $items_por_columna[$clave][] = $item;
        $items_instalacion[] = $item;
        $total_ordenes++;
    }

    if ($tiene_instalaciones_manuales) {
        $sql_manuales = "
            SELECT
                im.id,
                im.titulo,
                im.cliente,
                im.telefono,
                im.direccion,
                im.localidad,
                im.provincia,
                im.codigo_postal,
                im.fecha_instalacion,
                im.orden_visual,
                im.fecha_creacion,
                im.notas
            FROM ecommerce_instalaciones_manuales im
            WHERE 1=1
        ";

        $params_manuales = [];

        if ($filtro_instalacion_desde !== '') {
            $sql_manuales .= " AND (im.fecha_instalacion IS NULL OR im.fecha_instalacion >= ?)";
            $params_manuales[] = $filtro_instalacion_desde;
        }
        if ($filtro_instalacion_hasta !== '') {
            $sql_manuales .= " AND (im.fecha_instalacion IS NULL OR im.fecha_instalacion <= ?)";
            $params_manuales[] = $filtro_instalacion_hasta;
        }

        $sql_manuales .= " ORDER BY im.fecha_instalacion IS NULL, im.fecha_instalacion ASC, im.orden_visual ASC, im.fecha_creacion DESC, im.id DESC";

        $stmtm = $pdo->prepare($sql_manuales);
        $stmtm->execute($params_manuales);
        $manuales = $stmtm->fetchAll(PDO::FETCH_ASSOC);

        foreach ($manuales as $row) {
            $localidad = trim($row['localidad'] ?? '');
            if (!empty($row['provincia'])) {
                $localidad .= ($localidad ? ', ' : '') . $row['provincia'];
            }

            $item = [
                'tipo' => 'manual',
                'item_id' => (int)$row['id'],
                'pedido_id' => 0,
                'titulo' => trim($row['titulo'] ?? 'Instalación manual'),
                'subtitulo' => trim($row['cliente'] ?? ''),
                'telefono' => trim($row['telefono'] ?? ''),
                'direccion' => trim($row['direccion'] ?? ''),
                'localidad' => $localidad,
                'provincia' => trim($row['provincia'] ?? ''),
                'codigo_postal' => trim($row['codigo_postal'] ?? ''),
                'fecha_instalacion' => $row['fecha_instalacion'] ?: '',
                'fecha_creacion' => $row['fecha_creacion'] ?: '',
                'detalle_url' => '',
                'notas' => trim($row['notas'] ?? ''),
                'texto_tarjeta' => trim($row['notas'] ?? ''),
                'orden_visual' => (int)($row['orden_visual'] ?? 0),
            ];

            $clave = (!empty($item['fecha_instalacion']) && isset($items_por_columna[$item['fecha_instalacion']]))
                ? $item['fecha_instalacion']
                : 'sin_fecha';

            $items_por_columna[$clave][] = $item;
            $items_instalacion[] = $item;
            $total_manuales++;
        }
    }

    if ($tiene_visitas) {
        $sql_visitas = "
            SELECT
                v.id,
                v.titulo,
                v.descripcion,
                v.cliente_nombre,
                v.telefono,
                v.direccion,
                v.fecha_visita,
                v.hora_visita,
                v.estado,
                v.orden_visual,
                v.fecha_creacion
            FROM ecommerce_visitas v
            WHERE 1=1
        ";

        $params_visitas = [];

        if ($filtro_instalacion_desde !== '') {
            $sql_visitas .= " AND v.fecha_visita >= ?";
            $params_visitas[] = $filtro_instalacion_desde;
        }
        if ($filtro_instalacion_hasta !== '') {
            $sql_visitas .= " AND v.fecha_visita <= ?";
            $params_visitas[] = $filtro_instalacion_hasta;
        }

        $sql_visitas .= " ORDER BY v.fecha_visita ASC, v.orden_visual ASC, COALESCE(v.hora_visita, '23:59:59') ASC, v.id DESC";

        $stmtv = $pdo->prepare($sql_visitas);
        $stmtv->execute($params_visitas);
        $visitas = $stmtv->fetchAll(PDO::FETCH_ASSOC);

        foreach ($visitas as $row) {
            $item = [
                'tipo' => 'visita',
                'item_id' => (int)$row['id'],
                'pedido_id' => 0,
                'titulo' => trim($row['titulo'] ?? 'Visita'),
                'subtitulo' => trim($row['cliente_nombre'] ?? ''),
                'telefono' => trim($row['telefono'] ?? ''),
                'direccion' => trim($row['direccion'] ?? ''),
                'localidad' => '',
                'provincia' => '',
                'codigo_postal' => '',
                'fecha_instalacion' => $row['fecha_visita'] ?: '',
                'fecha_creacion' => $row['fecha_creacion'] ?: '',
                'detalle_url' => '',
                'notas' => trim($row['descripcion'] ?? ''),
                'texto_tarjeta' => trim($row['descripcion'] ?? ''),
                'orden_visual' => (int)($row['orden_visual'] ?? 0),
                'estado_visita' => trim($row['estado'] ?? 'pendiente'),
                'hora_visita' => trim($row['hora_visita'] ?? ''),
            ];

            $clave = (!empty($item['fecha_instalacion']) && isset($items_por_columna[$item['fecha_instalacion']]))
                ? $item['fecha_instalacion']
                : 'sin_fecha';

            $items_por_columna[$clave][] = $item;
            $items_instalacion[] = $item;
            $total_visitas++;
        }
    }

    foreach ($items_por_columna as $claveCol => $itemsColumna) {
        usort($itemsColumna, function ($a, $b) {
            $oa = (int)($a['orden_visual'] ?? 0);
            $ob = (int)($b['orden_visual'] ?? 0);
            if ($oa === $ob) {
                return ((int)$a['item_id']) <=> ((int)$b['item_id']);
            }
            return $oa <=> $ob;
        });
        $items_por_columna[$claveCol] = $itemsColumna;
    }
} catch (Exception $e) {
    $error_pagina = $e->getMessage();
}

$qs = http_build_query(array_filter([
    'fecha_desde' => $fecha_desde,
    'fecha_hasta' => $fecha_hasta,
    'instalacion_desde' => $instalacion_desde,
    'instalacion_hasta' => $instalacion_hasta,
    'incluir_entregados' => $incluir_entregados ? '1' : null,
]));

function render_tarjeta_instalacion($item) {
    $tipo = (string)($item['tipo'] ?? '');
    $itemId = (int)($item['item_id'] ?? 0);
    $titulo = (string)($item['titulo'] ?? '');
    $subtitulo = (string)($item['subtitulo'] ?? '');
    $direccion = (string)($item['direccion'] ?? '');
    $localidad = (string)($item['localidad'] ?? '');
    $detalleUrl = (string)($item['detalle_url'] ?? '');
    $fechaCreacion = (string)($item['fecha_creacion'] ?? '');
    $textoTarjeta = (string)($item['texto_tarjeta'] ?? '');

    $badgeClass = 'bg-secondary';
    $badgeText = 'Item';
    if ($tipo === 'manual') {
        $badgeClass = 'bg-dark';
        $badgeText = 'Manual';
    } elseif ($tipo === 'orden') {
        $badgeClass = 'bg-primary';
        $badgeText = 'OP';
    } elseif ($tipo === 'visita') {
        $badgeClass = 'bg-warning text-dark';
        $badgeText = 'Visita';
    }

    ob_start();
    ?>
    <div class="inst-card" draggable="true" data-tipo="<?= htmlspecialchars($tipo) ?>" data-id="<?= $itemId ?>"
        <?php if ($tipo === 'manual'): ?>
            data-manual-titulo="<?= htmlspecialchars($item['titulo']) ?>"
            data-manual-cliente="<?= htmlspecialchars($item['subtitulo']) ?>"
            data-manual-telefono="<?= htmlspecialchars($item['telefono']) ?>"
            data-manual-direccion="<?= htmlspecialchars($item['direccion']) ?>"
            data-manual-localidad="<?= htmlspecialchars($item['localidad']) ?>"
            data-manual-provincia="<?= htmlspecialchars($item['provincia'] ?? '') ?>"
            data-manual-codigo-postal="<?= htmlspecialchars($item['codigo_postal'] ?? '') ?>"
            data-manual-fecha="<?= htmlspecialchars($item['fecha_instalacion']) ?>"
            data-manual-notas="<?= htmlspecialchars($item['notas'] ?? '') ?>"
        <?php endif; ?>
    >
        <div class="d-flex justify-content-between align-items-start mb-1 gap-2">
            <strong class="inst-card-title"><?= htmlspecialchars($titulo) ?></strong>
            <span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span>
        </div>
        <?php if ($tipo === 'visita' && !empty($item['estado_visita'])): ?>
            <div class="small text-muted mb-1">Estado: <?= htmlspecialchars(str_replace('_', ' ', ucfirst($item['estado_visita']))) ?></div>
        <?php endif; ?>
        <?php if (!empty($item['hora_visita'])): ?>
            <div class="small text-muted mb-1">Hora: <?= htmlspecialchars(date('H:i', strtotime($item['hora_visita']))) ?> hs</div>
        <?php endif; ?>
        <?php if ($subtitulo !== ''): ?><div class="small inst-card-subtitle"><?= htmlspecialchars($subtitulo) ?></div><?php endif; ?>
        <?php if ($direccion !== ''): ?><div class="small text-muted inst-card-address"><?= htmlspecialchars($direccion) ?></div><?php endif; ?>
        <?php if ($localidad !== ''): ?><div class="small text-muted inst-card-locality"><?= htmlspecialchars($localidad) ?></div><?php endif; ?>

        <div class="mt-2">
            <label class="form-label small mb-1">Texto tarjeta</label>
            <textarea class="form-control form-control-sm inst-card-texto" rows="2" placeholder="Agregar texto..." data-original="<?= htmlspecialchars($textoTarjeta) ?>"><?= htmlspecialchars($textoTarjeta) ?></textarea>
            <div class="d-flex gap-1 mt-1">
                <button type="button" class="btn btn-sm btn-outline-primary inst-btn-guardar-texto">Guardar texto</button>
                <button type="button" class="btn btn-sm btn-outline-secondary inst-btn-subir" title="Subir">↑</button>
                <button type="button" class="btn btn-sm btn-outline-secondary inst-btn-bajar" title="Bajar">↓</button>
                <button type="button" class="btn btn-sm btn-outline-danger inst-btn-eliminar" title="Eliminar">Eliminar</button>
            </div>
        </div>

        <div class="small mt-2 d-flex justify-content-between align-items-center">
            <span><?= $fechaCreacion !== '' ? date('d/m', strtotime($fechaCreacion)) : '-' ?></span>
            <div class="d-flex gap-1">
                <?php if ($detalleUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($detalleUrl) ?>" class="btn btn-sm btn-outline-primary py-0 px-2">Ver</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return (string)ob_get_clean();
}
?>

<style>
.inst-dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1rem;
}
.inst-col {
    border: 1px solid #e9ecef;
    border-radius: .75rem;
    background: #fff;
    min-height: 260px;
    display: flex;
    flex-direction: column;
}
.inst-col-header {
    border-bottom: 1px solid #f1f3f5;
    padding: .75rem .9rem;
    background: #f8f9fa;
    border-top-left-radius: .75rem;
    border-top-right-radius: .75rem;
}
.inst-dropzone {
    padding: .75rem;
    flex: 1;
    min-height: 180px;
}
.inst-dropzone.drag-over {
    background: #eef6ff;
}
.inst-card {
    border: 1px solid #dee2e6;
    border-radius: .65rem;
    padding: .65rem;
    background: #fff;
    margin-bottom: .55rem;
    cursor: grab;
}
.inst-card:last-child {
    margin-bottom: 0;
}
.inst-card.dragging {
    opacity: .6;
}
.inst-card .badge {
    font-size: .68rem;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="mb-1">Instalaciones y visitas</h1>
        <p class="text-muted mb-0">Tablero único para programar, ordenar y documentar tarjetas por día</p>
    </div>
    <a href="ordenes_produccion.php" class="btn btn-outline-secondary">Órdenes de Producción</a>
</div>

<?php if ($error_pagina !== ''): ?>
    <div class="alert alert-danger">
        <strong>Error al cargar:</strong> <?= htmlspecialchars($error_pagina) ?>
    </div>
<?php endif; ?>

<?php if ($auto_setup_msg !== ''): ?>
    <div class="alert alert-info"><?= htmlspecialchars($auto_setup_msg) ?></div>
<?php endif; ?>

<?php if ($ok_msg !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($ok_msg) ?></div>
<?php endif; ?>

<?php if (!$tiene_clientes): ?>
    <div class="alert alert-warning">No existe <code>ecommerce_clientes</code>. Se usa la información de envío de los pedidos.</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">Nueva instalación manual</h5></div>
            <div class="card-body">
                <form method="POST" class="row g-2">
                    <input type="hidden" name="action" value="crear_instalacion_manual">
                    <div class="col-12">
                        <label class="form-label mb-1">Título *</label>
                        <input type="text" name="titulo" class="form-control" required placeholder="Ej: Cambio de cortinas oficina">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label mb-1">Cliente</label>
                        <input type="text" name="cliente" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label mb-1">Teléfono</label>
                        <input type="text" name="telefono" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label mb-1">Dirección</label>
                        <input type="text" name="direccion" class="form-control">
                    </div>
                    <div class="col-md-7">
                        <label class="form-label mb-1">Localidad</label>
                        <input type="text" name="localidad" class="form-control">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label mb-1">Provincia</label>
                        <input type="text" name="provincia" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label mb-1">Código postal</label>
                        <input type="text" name="codigo_postal" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label mb-1">Fecha instalación</label>
                        <input type="date" name="fecha_instalacion" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label mb-1">Notas</label>
                        <textarea name="notas" rows="2" class="form-control"></textarea>
                    </div>
                    <div class="col-12 mt-2">
                        <button type="submit" class="btn btn-primary w-100">Agregar instalación manual</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">Nueva visita</h5></div>
            <div class="card-body">
                <form method="POST" class="row g-2">
                    <input type="hidden" name="action" value="crear_visita">
                    <div class="col-12">
                        <label class="form-label mb-1">Título *</label>
                        <input type="text" name="titulo" class="form-control" required maxlength="180" placeholder="Ej: Relevamiento en cliente">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label mb-1">Fecha visita *</label>
                        <input type="date" name="fecha_visita" class="form-control" value="<?= htmlspecialchars($hoy) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label mb-1">Hora</label>
                        <input type="time" name="hora_visita" class="form-control">
                    </div>
                    <div class="col-md-7">
                        <label class="form-label mb-1">Cliente</label>
                        <input type="text" name="cliente_nombre" class="form-control" maxlength="150">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label mb-1">Teléfono</label>
                        <input type="text" name="telefono" class="form-control" maxlength="60">
                    </div>
                    <div class="col-12">
                        <label class="form-label mb-1">Dirección</label>
                        <input type="text" name="direccion" class="form-control" maxlength="255">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label mb-1">Estado</label>
                        <select name="estado" class="form-select">
                            <option value="pendiente">Pendiente</option>
                            <option value="en_proceso">En proceso</option>
                            <option value="completada">Completada</option>
                            <option value="cancelada">Cancelada</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label mb-1">Texto / descripción</label>
                        <textarea name="descripcion" rows="2" class="form-control"></textarea>
                    </div>
                    <div class="col-12 mt-2">
                        <button type="submit" class="btn btn-warning w-100">Agregar visita</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">Filtros y resumen</h5></div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Pedido desde</label>
                        <input type="date" name="fecha_desde" class="form-control" value="<?= htmlspecialchars($fecha_desde) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Pedido hasta</label>
                        <input type="date" name="fecha_hasta" class="form-control" value="<?= htmlspecialchars($fecha_hasta) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tablero desde</label>
                        <input type="date" name="instalacion_desde" class="form-control" value="<?= htmlspecialchars($instalacion_desde) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tablero hasta</label>
                        <input type="date" name="instalacion_hasta" class="form-control" value="<?= htmlspecialchars($instalacion_hasta) ?>">
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="incluir_entregados" value="1" id="incluir_entregados" <?= $incluir_entregados ? 'checked' : '' ?>>
                            <label class="form-check-label" for="incluir_entregados">Incluir entregados</label>
                        </div>
                    </div>
                    <div class="col-md-8 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Aplicar filtros</button>
                        <a href="instalaciones.php" class="btn btn-outline-secondary">Limpiar</a>
                        <a href="instalaciones_reporte_direcciones.php?<?= $qs ?>" class="btn btn-outline-dark" target="_blank">Reporte direcciones</a>
                        <a href="instalaciones_reporte_productos.php?<?= $qs ?>" class="btn btn-outline-dark" target="_blank">Reporte productos</a>
                    </div>
                </form>

                <div class="row g-2">
                    <div class="col-6">
                        <div class="border rounded p-2 text-center">
                            <div class="text-muted small">Total instalaciones</div>
                            <div class="h4 mb-0"><?= count($items_instalacion) ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-2 text-center">
                            <div class="text-muted small">Órdenes de producción</div>
                            <div class="h4 mb-0"><?= (int)$total_ordenes ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-2 text-center">
                            <div class="text-muted small">Instalaciones manuales</div>
                            <div class="h4 mb-0"><?= (int)$total_manuales ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-2 text-center">
                            <div class="text-muted small">Visitas</div>
                            <div class="h4 mb-0"><?= (int)$total_visitas ?></div>
                        </div>
                    </div>
                </div>
                <div class="small text-muted mt-2">Arrastrá o usá ↑/↓ para reordenar. Cada tarjeta permite guardar texto propio.</div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">Dashboard por días</h5>
    </div>
    <div class="card-body">
        <div class="inst-dashboard-grid">
            <div class="inst-col">
                <div class="inst-col-header d-flex justify-content-between align-items-center">
                    <strong>Sin fecha</strong>
                    <span class="badge bg-secondary"><?= count($items_por_columna['sin_fecha']) ?></span>
                </div>
                <div class="inst-dropzone" data-fecha="" id="drop-sin-fecha">
                    <?php foreach ($items_por_columna['sin_fecha'] as $item): ?>
                        <?= render_tarjeta_instalacion($item) ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php foreach ($dias_tablero as $fecha_col): ?>
                <div class="inst-col">
                    <div class="inst-col-header d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= htmlspecialchars(texto_dia($fecha_col)) ?></strong>
                            <div class="small text-muted"><?= htmlspecialchars(date('d/m/Y', strtotime($fecha_col))) ?></div>
                        </div>
                        <span class="badge bg-primary"><?= count($items_por_columna[$fecha_col]) ?></span>
                    </div>
                    <div class="inst-dropzone" data-fecha="<?= htmlspecialchars($fecha_col) ?>" id="drop-<?= htmlspecialchars($fecha_col) ?>">
                        <?php foreach ($items_por_columna[$fecha_col] as $item): ?>
                            <?= render_tarjeta_instalacion($item) ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Listado unificado</h5></div>
    <div class="table-responsive">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Título</th>
                    <th>Cliente</th>
                    <th>Dirección</th>
                    <th>Localidad</th>
                    <th>Fecha instalación</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items_instalacion)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No hay instalaciones para el rango actual.</td></tr>
                <?php else: ?>
                    <?php foreach ($items_instalacion as $item): ?>
                        <tr <?= $item['tipo'] === 'manual' ? 'data-manual-row-id="' . (int)$item['item_id'] . '"' : '' ?>>
                            <td>
                                <?php if ($item['tipo'] === 'manual'): ?>
                                    <span class="badge bg-dark">Manual</span>
                                <?php elseif ($item['tipo'] === 'visita'): ?>
                                    <span class="badge bg-warning text-dark">Visita</span>
                                <?php else: ?>
                                    <span class="badge bg-primary">Orden</span>
                                <?php endif; ?>
                            </td>
                            <td class="cell-title"><?= htmlspecialchars($item['titulo']) ?></td>
                            <td class="cell-cliente"><?= htmlspecialchars($item['subtitulo'] ?: '-') ?></td>
                            <td class="cell-direccion"><?= htmlspecialchars($item['direccion'] ?: '-') ?></td>
                            <td class="cell-localidad"><?= htmlspecialchars($item['localidad'] ?: '-') ?></td>
                            <td class="cell-fecha" data-fecha="<?= htmlspecialchars($item['fecha_instalacion'] ?: '') ?>"><?= $item['fecha_instalacion'] ? htmlspecialchars(date('d/m/Y', strtotime($item['fecha_instalacion']))) : '-' ?></td>
                            <td>
                                <?php if (!empty($item['detalle_url'])): ?>
                                    <a href="<?= htmlspecialchars($item['detalle_url']) ?>" class="btn btn-sm btn-outline-primary">Ver detalle</a>
                                <?php elseif ($item['tipo'] === 'manual'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-dark btn-editar-manual-listado" data-id="<?= (int)$item['item_id'] ?>">Editar</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modal-editar-manual" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="form-editar-manual">
                <div class="modal-header">
                    <h5 class="modal-title">Editar instalación manual</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="item_id" id="edit-item-id">
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label mb-1">Título *</label>
                            <input type="text" name="titulo" id="edit-titulo" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-1">Cliente</label>
                            <input type="text" name="cliente" id="edit-cliente" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-1">Teléfono</label>
                            <input type="text" name="telefono" id="edit-telefono" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label mb-1">Dirección</label>
                            <input type="text" name="direccion" id="edit-direccion" class="form-control">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label mb-1">Localidad</label>
                            <input type="text" name="localidad" id="edit-localidad" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1">Provincia</label>
                            <input type="text" name="provincia" id="edit-provincia" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1">Cód. postal</label>
                            <input type="text" name="codigo_postal" id="edit-codigo-postal" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-1">Fecha instalación</label>
                            <input type="date" name="fecha_instalacion" id="edit-fecha" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label mb-1">Notas</label>
                            <textarea name="notas" id="edit-notas" rows="3" class="form-control"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger me-auto" id="btn-eliminar-manual">Eliminar</button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var draggedCard = null;
    var dropZones = Array.prototype.slice.call(document.querySelectorAll('.inst-dropzone'));
    var editModalEl = document.getElementById('modal-editar-manual');
    var editForm = document.getElementById('form-editar-manual');
    var btnEliminarManual = document.getElementById('btn-eliminar-manual');
    var editModal = (editModalEl && window.bootstrap) ? new bootstrap.Modal(editModalEl) : null;
    var activeManualId = null;

    function actualizarBadgeColumna(dropzone) {
        var cardCount = dropzone.querySelectorAll('.inst-card').length;
        var col = dropzone.closest('.inst-col');
        if (!col) return;
        var badge = col.querySelector('.inst-col-header .badge');
        if (badge) {
            badge.textContent = cardCount;
        }
    }

    function actualizarBadgesTodas() {
        dropZones.forEach(actualizarBadgeColumna);
    }

    function actualizarResumenInstalaciones() {
        var total = document.querySelectorAll('.inst-card').length;
        var totalManuales = document.querySelectorAll('.inst-card[data-tipo="manual"]').length;
        var totalOrdenes = document.querySelectorAll('.inst-card[data-tipo="orden"]').length;
        var totalVisitas = document.querySelectorAll('.inst-card[data-tipo="visita"]').length;

        var h4s = document.querySelectorAll('.card-body .row.g-2 .h4.mb-0');
        if (h4s.length >= 4) {
            h4s[0].textContent = total;
            h4s[1].textContent = totalOrdenes;
            h4s[2].textContent = totalManuales;
            h4s[3].textContent = totalVisitas;
        }
    }

    function serializarColumna(dropzone) {
        return Array.prototype.slice.call(dropzone.querySelectorAll('.inst-card')).map(function (card) {
            return card.getAttribute('data-tipo') + ':' + card.getAttribute('data-id');
        });
    }

    function guardarOrdenColumna(dropzone, onRollback) {
        if (!dropzone) {
            return;
        }

        var fd = new FormData();
        fd.append('action', 'guardar_orden_tarjetas');
        fd.append('fecha_columna', dropzone.getAttribute('data-fecha') || '');
        fd.append('orden', JSON.stringify(serializarColumna(dropzone)));

        fetch('instalaciones.php?<?= htmlspecialchars($qs) ?>', {
            method: 'POST',
            body: fd,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res || !res.ok) {
                throw new Error((res && res.msg) ? res.msg : 'No se pudo guardar el orden');
            }
        })
        .catch(function (err) {
            if (typeof onRollback === 'function') {
                onRollback();
            }
            alert(err.message || 'No se pudo guardar el orden');
        });
    }

    function moverEnServidor(card, fechaDestino, onRollback) {
        var fd = new FormData();
        fd.append('action', 'mover_instalacion');
        fd.append('tipo', card.getAttribute('data-tipo'));
        fd.append('item_id', card.getAttribute('data-id'));
        fd.append('fecha_destino', fechaDestino || '');

        fetch('instalaciones.php?<?= htmlspecialchars($qs) ?>', {
            method: 'POST',
            body: fd,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res || !res.ok) {
                throw new Error((res && res.msg) ? res.msg : 'Error moviendo instalación');
            }
            if (card.getAttribute('data-tipo') === 'manual') {
                card.setAttribute('data-manual-fecha', fechaDestino || '');
                var row = document.querySelector('tr[data-manual-row-id="' + card.getAttribute('data-id') + '"]');
                if (row) {
                    var cellFecha = row.querySelector('.cell-fecha');
                    if (cellFecha) {
                        cellFecha.setAttribute('data-fecha', fechaDestino || '');
                        if (fechaDestino) {
                            var d = new Date(fechaDestino + 'T00:00:00');
                            var dd = String(d.getDate()).padStart(2, '0');
                            var mm = String(d.getMonth() + 1).padStart(2, '0');
                            var yyyy = d.getFullYear();
                            cellFecha.textContent = dd + '/' + mm + '/' + yyyy;
                        } else {
                            cellFecha.textContent = '-';
                        }
                    }
                }
            }

            var zona = card.closest('.inst-dropzone');
            guardarOrdenColumna(zona);
        })
        .catch(function (err) {
            if (typeof onRollback === 'function') {
                onRollback();
            }
            alert(err.message || 'No se pudo guardar el cambio');
        });
    }

    function setOrCreateText(card, selector, text, muted) {
        var el = card.querySelector(selector);
        if (text) {
            if (!el) {
                el = document.createElement('div');
                el.className = 'small' + (muted ? ' text-muted' : '') + ' ' + selector.replace('.', '');
                var footer = card.querySelector('.small.mt-1');
                if (footer && footer.parentNode) {
                    footer.parentNode.insertBefore(el, footer);
                } else {
                    card.appendChild(el);
                }
            }
            el.textContent = text;
        } else if (el) {
            el.remove();
        }
    }

    function abrirModalManualDesdeCard(card) {
        if (!card || card.getAttribute('data-tipo') !== 'manual' || !editModal) {
            return;
        }

        activeManualId = card.getAttribute('data-id');
        document.getElementById('edit-item-id').value = activeManualId || '';
        document.getElementById('edit-titulo').value = card.getAttribute('data-manual-titulo') || '';
        document.getElementById('edit-cliente').value = card.getAttribute('data-manual-cliente') || '';
        document.getElementById('edit-telefono').value = card.getAttribute('data-manual-telefono') || '';
        document.getElementById('edit-direccion').value = card.getAttribute('data-manual-direccion') || '';
        document.getElementById('edit-localidad').value = card.getAttribute('data-manual-localidad') || '';
        document.getElementById('edit-provincia').value = card.getAttribute('data-manual-provincia') || '';
        document.getElementById('edit-codigo-postal').value = card.getAttribute('data-manual-codigo-postal') || '';
        document.getElementById('edit-fecha').value = card.getAttribute('data-manual-fecha') || '';
        document.getElementById('edit-notas').value = card.getAttribute('data-manual-notas') || '';

        editModal.show();
    }

    function syncManualUI(data) {
        var cards = document.querySelectorAll('.inst-card[data-tipo="manual"][data-id="' + data.item_id + '"]');
        cards.forEach(function (card) {
            card.setAttribute('data-manual-titulo', data.titulo || '');
            card.setAttribute('data-manual-cliente', data.cliente || '');
            card.setAttribute('data-manual-telefono', data.telefono || '');
            card.setAttribute('data-manual-direccion', data.direccion || '');
            card.setAttribute('data-manual-localidad', ((data.localidad || '') + ((data.provincia || '') ? ((data.localidad || '') ? ', ' : '') + data.provincia : '')));
            card.setAttribute('data-manual-provincia', data.provincia || '');
            card.setAttribute('data-manual-codigo-postal', data.codigo_postal || '');
            card.setAttribute('data-manual-fecha', data.fecha_instalacion || '');
            card.setAttribute('data-manual-notas', data.notas || '');

            var localCompuesta = ((data.localidad || '') + ((data.provincia || '') ? ((data.localidad || '') ? ', ' : '') + data.provincia : ''));
            var titleEl = card.querySelector('.inst-card-title');
            if (titleEl) titleEl.textContent = data.titulo || '';

            setOrCreateText(card, '.inst-card-subtitle', data.cliente || '', false);
            setOrCreateText(card, '.inst-card-address', data.direccion || '', true);
            setOrCreateText(card, '.inst-card-locality', localCompuesta, true);
            var ta = card.querySelector('.inst-card-texto');
            if (ta) {
                ta.value = data.notas || '';
                ta.setAttribute('data-original', data.notas || '');
            }
        });

        var row = document.querySelector('tr[data-manual-row-id="' + data.item_id + '"]');
        if (row) {
            var localCompuestaRow = ((data.localidad || '') + ((data.provincia || '') ? ((data.localidad || '') ? ', ' : '') + data.provincia : ''));
            var cTitle = row.querySelector('.cell-title');
            var cCliente = row.querySelector('.cell-cliente');
            var cDir = row.querySelector('.cell-direccion');
            var cLoc = row.querySelector('.cell-localidad');
            var cFecha = row.querySelector('.cell-fecha');

            if (cTitle) cTitle.textContent = data.titulo || '-';
            if (cCliente) cCliente.textContent = data.cliente || '-';
            if (cDir) cDir.textContent = data.direccion || '-';
            if (cLoc) cLoc.textContent = localCompuestaRow || '-';
            if (cFecha) {
                cFecha.setAttribute('data-fecha', data.fecha_instalacion || '');
                if (data.fecha_instalacion) {
                    var d = new Date(data.fecha_instalacion + 'T00:00:00');
                    var dd = String(d.getDate()).padStart(2, '0');
                    var mm = String(d.getMonth() + 1).padStart(2, '0');
                    var yyyy = d.getFullYear();
                    cFecha.textContent = dd + '/' + mm + '/' + yyyy;
                } else {
                    cFecha.textContent = '-';
                }
            }
        }
    }

    function eliminarManualUI(itemId) {
        var cards = document.querySelectorAll('.inst-card[data-tipo="manual"][data-id="' + itemId + '"]');
        cards.forEach(function (card) {
            var parent = card.parentNode;
            card.remove();
            if (parent && parent.classList.contains('inst-dropzone')) {
                actualizarBadgeColumna(parent);
            }
        });

        var row = document.querySelector('tr[data-manual-row-id="' + itemId + '"]');
        if (row) {
            row.remove();
        }

        var tbody = document.querySelector('.table-responsive table tbody');
        if (tbody && !tbody.querySelector('tr')) {
            var emptyRow = document.createElement('tr');
            emptyRow.innerHTML = '<td colspan="7" class="text-center text-muted py-4">No hay instalaciones para el rango actual.</td>';
            tbody.appendChild(emptyRow);
        }

        actualizarResumenInstalaciones();
    }

    document.querySelectorAll('.inst-card').forEach(function (card) {
        card.addEventListener('dragstart', function () {
            draggedCard = card;
            card.classList.add('dragging');
        });

        card.addEventListener('dragend', function () {
            card.classList.remove('dragging');
            draggedCard = null;
        });

        card.addEventListener('click', function (e) {
            if (e.target.closest('a') || e.target.closest('button') || e.target.closest('textarea') || e.target.closest('input') || e.target.closest('select') || e.target.closest('label')) {
                return;
            }
            if (card.getAttribute('data-tipo') === 'manual') {
                abrirModalManualDesdeCard(card);
            }
        });

        var btnGuardarTexto = card.querySelector('.inst-btn-guardar-texto');
        if (btnGuardarTexto) {
            btnGuardarTexto.addEventListener('click', function () {
                var textarea = card.querySelector('.inst-card-texto');
                if (!textarea) {
                    return;
                }

                var texto = textarea.value || '';
                var fd = new FormData();
                fd.append('action', 'guardar_texto_tarjeta');
                fd.append('tipo', card.getAttribute('data-tipo'));
                fd.append('item_id', card.getAttribute('data-id'));
                fd.append('texto', texto);

                fetch('instalaciones.php?<?= htmlspecialchars($qs) ?>', {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res || !res.ok) {
                        throw new Error((res && res.msg) ? res.msg : 'No se pudo guardar el texto');
                    }
                    textarea.setAttribute('data-original', texto);
                    if (card.getAttribute('data-tipo') === 'manual') {
                        card.setAttribute('data-manual-notas', texto);
                    }
                })
                .catch(function (err) {
                    alert(err.message || 'No se pudo guardar el texto');
                });
            });
        }

        var btnEliminar = card.querySelector('.inst-btn-eliminar');
        if (btnEliminar) {
            btnEliminar.addEventListener('click', function () {
                var tipo = card.getAttribute('data-tipo');
                var itemId = card.getAttribute('data-id');
                if (!itemId) {
                    alert('No hay item seleccionado para eliminar.');
                    return;
                }
                if (!confirm('¿Seguro que querés eliminar este item? Esta acción no se puede deshacer.')) {
                    return;
                }
                var fd = new FormData();
                if (tipo === 'manual') {
                    fd.append('action', 'eliminar_instalacion_manual');
                } else if (tipo === 'visita') {
                    fd.append('action', 'eliminar_visita');
                } else {
                    fd.append('action', 'eliminar_orden');
                }
                fd.append('item_id', itemId);
                fetch('instalaciones.php?<?= htmlspecialchars($qs) ?>', {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(function (r) {
                    try { return r.json(); } catch (e) { throw new Error('Respuesta inválida del servidor'); }
                })
                .then(function (res) {
                    if (!res || !res.ok) {
                        throw new Error((res && res.msg) ? res.msg : 'No se pudo eliminar');
                    }
                    card.remove();
                    actualizarBadgeColumna(card.parentNode);
                    actualizarResumenInstalaciones();
                })
                .catch(function (err) {
                    alert(err.message || 'Error al eliminar');
                });
            });
        }

        function moverDentroColumna(cardMover, direccion) {
            var parent = cardMover.parentNode;
            if (!parent || !parent.classList.contains('inst-dropzone')) {
                return;
            }

            var sibling = direccion === 'up' ? cardMover.previousElementSibling : cardMover.nextElementSibling;
            if (!sibling || !sibling.classList.contains('inst-card')) {
                return;
            }

            if (direccion === 'up') {
                parent.insertBefore(cardMover, sibling);
            } else {
                parent.insertBefore(sibling, cardMover);
            }

            guardarOrdenColumna(parent, function () {
                alert('Error al guardar el orden. Puede que la sesión haya expirado o el servidor devolvió una respuesta inválida.');
            });
        }

        var btnSubir = card.querySelector('.inst-btn-subir');
        if (btnSubir) {
            btnSubir.addEventListener('click', function () {
                moverDentroColumna(card, 'up');
            });
        }

        var btnBajar = card.querySelector('.inst-btn-bajar');
        if (btnBajar) {
            btnBajar.addEventListener('click', function () {
                moverDentroColumna(card, 'down');
            });
        }
    });

    document.querySelectorAll('.btn-editar-manual-listado').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-id');
            var card = document.querySelector('.inst-card[data-tipo="manual"][data-id="' + id + '"]');
            if (card) {
                abrirModalManualDesdeCard(card);
                return;
            }

            if (!editModal) {
                return;
            }

            activeManualId = id;
            document.getElementById('edit-item-id').value = id || '';
            document.getElementById('edit-titulo').value = '';
            document.getElementById('edit-cliente').value = '';
            document.getElementById('edit-telefono').value = '';
            document.getElementById('edit-direccion').value = '';
            document.getElementById('edit-localidad').value = '';
            document.getElementById('edit-provincia').value = '';
            document.getElementById('edit-codigo-postal').value = '';
            document.getElementById('edit-fecha').value = '';
            document.getElementById('edit-notas').value = '';
            editModal.show();
        });
    });

    if (editForm) {
        editForm.addEventListener('submit', function (e) {
            e.preventDefault();

            var fd = new FormData(editForm);
            fd.append('action', 'editar_instalacion_manual');

            fetch('instalaciones.php?<?= htmlspecialchars($qs) ?>', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.ok) {
                    throw new Error((res && res.msg) ? res.msg : 'No se pudo guardar');
                }

                syncManualUI(res.data || {});
                if (editModal) {
                    editModal.hide();
                }
            })
            .catch(function (err) {
                alert(err.message || 'Error al guardar');
            });
        });
    }

    if (btnEliminarManual) {
        btnEliminarManual.addEventListener('click', function () {
            var itemId = document.getElementById('edit-item-id').value || activeManualId;
            if (!itemId) {
                alert('No hay una instalación manual seleccionada.');
                return;
            }

            if (!confirm('¿Seguro que querés eliminar esta instalación manual? Esta acción no se puede deshacer.')) {
                return;
            }

            var fd = new FormData();
            fd.append('action', 'eliminar_instalacion_manual');
            fd.append('item_id', itemId);

            fetch('instalaciones.php?<?= htmlspecialchars($qs) ?>', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.ok) {
                    throw new Error((res && res.msg) ? res.msg : 'No se pudo eliminar');
                }

                eliminarManualUI(String(res.item_id || itemId));
                if (editModal) {
                    editModal.hide();
                }
                activeManualId = null;
            })
            .catch(function (err) {
                alert(err.message || 'Error al eliminar');
            });
        });
    }

    dropZones.forEach(function (zone) {
        zone.addEventListener('dragover', function (e) {
            e.preventDefault();
            zone.classList.add('drag-over');
        });

        zone.addEventListener('dragleave', function () {
            zone.classList.remove('drag-over');
        });

        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            zone.classList.remove('drag-over');

            if (!draggedCard) {
                return;
            }

            var origen = draggedCard.parentNode;
            if (!origen || zone === origen) {
                return;
            }

            zone.appendChild(draggedCard);
            actualizarBadgeColumna(origen);
            actualizarBadgeColumna(zone);

            var fechaDestino = zone.getAttribute('data-fecha') || '';
            moverEnServidor(draggedCard, fechaDestino, function () {
                origen.appendChild(draggedCard);
                actualizarBadgesTodas();
                guardarOrdenColumna(origen);
            });
        });
    });
});
</script>

<?php require 'includes/footer.php'; ?>
