<?php

/**
 * Helper para migración y relación usuario-empleado
 */

if (!function_exists('usuarios_empleados_asegurar_migracion')) {
    function usuarios_empleados_asegurar_migracion(PDO $pdo): bool
    {
        try {
            // Verificar si la tabla usuarios existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'usuarios'");
            $stmt->execute();
            if ((int)$stmt->fetchColumn() === 0) {
                return false; // Tabla usuarios no existe
            }

            // Verificar si la tabla empleados existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'empleados'");
            $stmt->execute();
            if ((int)$stmt->fetchColumn() === 0) {
                return false; // Tabla empleados no existe
            }

            // Verificar si la columna empleado_id existe en usuarios
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'empleado_id'");
            $stmt->execute();
            $existe_columna = (int)$stmt->fetchColumn() > 0;

            if (!$existe_columna) {
                // Crear la columna
                $pdo->exec("ALTER TABLE usuarios ADD COLUMN empleado_id INT NULL AFTER rol_id");
                
                // Crear índice único para no tener múltiples usuarios con el mismo empleado
                $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_usuarios_empleado_id ON usuarios(empleado_id) WHERE empleado_id IS NOT NULL");
            }

            return true;
        } catch (Throwable $e) {
            error_log('usuarios_empleados_asegurar_migracion error: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('usuarios_empleados_vincular')) {
    function usuarios_empleados_vincular(PDO $pdo, int $usuario_id, ?int $empleado_id): array
    {
        try {
            if (!usuarios_empleados_asegurar_migracion($pdo)) {
                return ['ok' => false, 'error' => 'Migraciones no disponibles'];
            }

            // Validar usuario existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario_id]);
            if (!$stmt->fetch()) {
                return ['ok' => false, 'error' => 'Usuario no encontrado'];
            }

            // Si empleado_id es null, solo desvincula
            if ($empleado_id === null) {
                $stmt = $pdo->prepare("UPDATE usuarios SET empleado_id = NULL WHERE id = ?");
                $stmt->execute([$usuario_id]);
                return ['ok' => true, 'message' => 'Usuario desvinculado'];
            }

            // Validar empleado existe
            $stmt = $pdo->prepare("SELECT id FROM empleados WHERE id = ?");
            $stmt->execute([$empleado_id]);
            if (!$stmt->fetch()) {
                return ['ok' => false, 'error' => 'Empleado no encontrado'];
            }

            // Verificar que el empleado no esté vinculado a otro usuario
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE empleado_id = ? AND id != ?");
            $stmt->execute([$empleado_id, $usuario_id]);
            if ($stmt->fetch()) {
                return ['ok' => false, 'error' => 'Este empleado ya está vinculado a otro usuario'];
            }

            // Vincular
            $stmt = $pdo->prepare("UPDATE usuarios SET empleado_id = ? WHERE id = ?");
            $stmt->execute([$empleado_id, $usuario_id]);

            return ['ok' => true, 'message' => 'Usuario vinculado a empleado'];
        } catch (Throwable $e) {
            error_log('usuarios_empleados_vincular error: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('usuarios_empleados_obtener_disponibles')) {
    function usuarios_empleados_obtener_disponibles(PDO $pdo, ?int $usuario_id = null): array
    {
        try {
            if (!usuarios_empleados_asegurar_migracion($pdo)) {
                return [];
            }

            $sql = "
                SELECT e.id, e.nombre, e.documento, e.puesto
                FROM empleados e
                LEFT JOIN usuarios u ON u.empleado_id = e.id
                WHERE COALESCE(e.activo, 1) = 1
                  AND (u.id IS NULL OR u.id = ?)
                ORDER BY e.nombre ASC
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$usuario_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('usuarios_empleados_obtener_disponibles error: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('usuario_empleados_obtener_relacion')) {
    function usuario_empleados_obtener_relacion(PDO $pdo, int $usuario_id): ?array
    {
        try {
            if (!usuarios_empleados_asegurar_migracion($pdo)) {
                return null;
            }

            $stmt = $pdo->prepare("
                SELECT u.id as usuario_id, u.usuario, u.nombre as usuario_nombre,
                       e.id as empleado_id, e.nombre as empleado_nombre, e.documento, e.puesto, e.departamento
                FROM usuarios u
                LEFT JOIN empleados e ON u.empleado_id = e.id
                WHERE u.id = ?
            ");
            $stmt->execute([$usuario_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('usuario_empleados_obtener_relacion error: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('empleado_usuarios_obtener_relacionado')) {
    function empleado_usuarios_obtener_relacionado(PDO $pdo, int $empleado_id): ?array
    {
        try {
            if (!usuarios_empleados_asegurar_migracion($pdo)) {
                return null;
            }

            $stmt = $pdo->prepare("
                SELECT u.id as usuario_id, u.usuario, u.nombre, u.activo, r.nombre as rol_nombre
                FROM usuarios u
                LEFT JOIN roles r ON u.rol_id = r.id
                WHERE u.empleado_id = ?
                LIMIT 1
            ");
            $stmt->execute([$empleado_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('empleado_usuarios_obtener_relacionado error: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('usuarios_listar_con_empleados')) {
    function usuarios_listar_con_empleados(PDO $pdo): array
    {
        try {
            if (!usuarios_empleados_asegurar_migracion($pdo)) {
                // Retornar sin empleados si la migración falla
                $stmt = $pdo->query("
                    SELECT u.id, u.usuario, u.nombre, u.activo, r.nombre as rol_nombre,
                           NULL as empleado_id, NULL as empleado_nombre
                    FROM usuarios u
                    LEFT JOIN roles r ON u.rol_id = r.id
                    ORDER BY u.usuario
                ");
                return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

            $stmt = $pdo->query("
                SELECT u.id, u.usuario, u.nombre, u.activo, r.nombre as rol_nombre,
                       u.empleado_id, e.nombre as empleado_nombre
                FROM usuarios u
                LEFT JOIN roles r ON u.rol_id = r.id
                LEFT JOIN empleados e ON u.empleado_id = e.id
                ORDER BY u.usuario
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('usuarios_listar_con_empleados error: ' . $e->getMessage());
            return [];
        }
    }
}
