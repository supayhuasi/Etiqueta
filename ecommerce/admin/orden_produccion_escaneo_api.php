<?php
/**
 * API para Control de Producción mediante Escaneo
 * Maneja las acciones de búsqueda y cambio de estado de items
 */

require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

session_start();
$usuario_id = $_SESSION['user_id'] ?? null;

if (!$usuario_id) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    $accion = $data['accion'] ?? '';
    
    if ($accion === 'buscar') {
        // Buscar item por código
        $codigo = $data['codigo'] ?? '';
        
        if (empty($codigo)) {
            throw new Exception('Código no proporcionado');
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                pib.*,
                pi.producto_id,
                pi.cantidad as cantidad_total,
                pr.nombre as producto_nombre,
                op.pedido_id,
                p.numero_pedido,
                u_inicio.nombre as usuario_inicio_nombre,
                u_termino.nombre as usuario_termino_nombre
            FROM ecommerce_produccion_items_barcode pib
            JOIN ecommerce_pedido_items pi ON pib.pedido_item_id = pi.id
            JOIN ecommerce_productos pr ON pi.producto_id = pr.id
            JOIN ecommerce_ordenes_produccion op ON pib.orden_produccion_id = op.id
            JOIN ecommerce_pedidos p ON op.pedido_id = p.id
            LEFT JOIN usuarios u_inicio ON pib.usuario_inicio = u_inicio.id
            LEFT JOIN usuarios u_termino ON pib.usuario_termino = u_termino.id
            WHERE pib.codigo_barcode = ?
        ");
        $stmt->execute([$codigo]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            throw new Exception('Código no encontrado en el sistema');
        }
        
        echo json_encode([
            'success' => true,
            'item' => $item
        ]);
        
    } elseif ($accion === 'iniciar') {
        // Iniciar producción de un item
        $item_id = $data['item_id'] ?? 0;
        
        if ($item_id <= 0) {
            throw new Exception('ID de item inválido');
        }
        
        // Verificar que esté en estado pendiente
        $stmt = $pdo->prepare("SELECT estado FROM ecommerce_produccion_items_barcode WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            throw new Exception('Item no encontrado');
        }
        
        if ($item['estado'] !== 'pendiente') {
            throw new Exception('Este item ya fue iniciado');
        }
        
        // Actualizar a en_proceso
        $stmt = $pdo->prepare("
            UPDATE ecommerce_produccion_items_barcode 
            SET estado = 'en_proceso',
                usuario_inicio = ?,
                fecha_inicio = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$usuario_id, $item_id]);
        
        echo json_encode([
            'success' => true,
            'message' => '✅ Producción iniciada correctamente'
        ]);
        
    } elseif ($accion === 'terminar') {
        // Terminar producción de un item
        $item_id = $data['item_id'] ?? 0;
        
        if ($item_id <= 0) {
            throw new Exception('ID de item inválido');
        }
        
        // Verificar que esté en proceso
        $stmt = $pdo->prepare("SELECT estado FROM ecommerce_produccion_items_barcode WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            throw new Exception('Item no encontrado');
        }
        
        if ($item['estado'] !== 'en_proceso') {
            throw new Exception('Este item no está en proceso');
        }
        
        // Actualizar a terminado
        $stmt = $pdo->prepare("
            UPDATE ecommerce_produccion_items_barcode 
            SET estado = 'terminado',
                usuario_termino = ?,
                fecha_termino = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$usuario_id, $item_id]);
        
        // Verificar si todos los items de la orden están terminados
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN estado = 'terminado' THEN 1 ELSE 0 END) as terminados
            FROM ecommerce_produccion_items_barcode
            WHERE orden_produccion_id = (
                SELECT orden_produccion_id 
                FROM ecommerce_produccion_items_barcode 
                WHERE id = ?
            )
        ");
        $stmt->execute([$item_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $mensaje = '✅ Item terminado correctamente';
        
        if ($stats['total'] == $stats['terminados']) {
            // Todos los items terminados - actualizar orden
            $stmt = $pdo->prepare("
                UPDATE ecommerce_ordenes_produccion 
                SET estado = 'terminado'
                WHERE id = (
                    SELECT orden_produccion_id 
                    FROM ecommerce_produccion_items_barcode 
                    WHERE id = ?
                )
            ");
            $stmt->execute([$item_id]);
            
            $mensaje = '🎉 Item terminado. ¡Orden de producción completada!';
        }
        
        echo json_encode([
            'success' => true,
            'message' => $mensaje
        ]);
        
    } elseif ($accion === 'rechazar') {
        // Rechazar un item
        $item_id = $data['item_id'] ?? 0;
        $observaciones = $data['observaciones'] ?? 'Rechazado por operario';
        
        if ($item_id <= 0) {
            throw new Exception('ID de item inválido');
        }
        
        // Actualizar a rechazado
        $stmt = $pdo->prepare("
            UPDATE ecommerce_produccion_items_barcode 
            SET estado = 'rechazado',
                observaciones = ?,
                usuario_termino = ?,
                fecha_termino = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$observaciones, $usuario_id, $item_id]);
        
        echo json_encode([
            'success' => true,
            'message' => '❌ Item rechazado. Motivo registrado.'
        ]);
        
    } else {
        throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
