<?php

if (!function_exists('asistencia_codigo_secret')) {
    function asistencia_codigo_secret(): string
    {
        static $secret = null;

        if ($secret !== null) {
            return $secret;
        }

        $envSecret = getenv('ASISTENCIAS_CODIGO_SECRET') ?: getenv('APP_KEY');
        if (is_string($envSecret) && trim($envSecret) !== '') {
            $secret = trim($envSecret);
            return $secret;
        }

        $fallback = '';
        if (isset($GLOBALS['robot_api_key']) && is_string($GLOBALS['robot_api_key'])) {
            $fallback = $GLOBALS['robot_api_key'];
        }

        if ($fallback === '') {
            $fallback = 'asistencias-default-secret';
        }

        $secret = hash('sha256', $fallback . '|tucuroller|asistencias');
        return $secret;
    }
}

if (!function_exists('normalizar_codigo_asistencia')) {
    function normalizar_codigo_asistencia(string $codigo): string
    {
        $codigo = strtoupper(trim($codigo));
        return preg_replace('/\s+/', '', $codigo);
    }
}

if (!function_exists('generar_codigo_asistencia')) {
    function generar_codigo_asistencia(int $empleadoId): string
    {
        if ($empleadoId <= 0) {
            throw new InvalidArgumentException('El ID de empleado debe ser mayor que 0');
        }

        $hash = hash_hmac('sha256', 'EMP:' . $empleadoId, asistencia_codigo_secret());
        return 'EMP' . strtoupper(substr($hash, 0, 12));
    }
}

if (!function_exists('asistencia_column_exists')) {
    function asistencia_column_exists(PDO $pdo, string $table, string $column): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            return false;
        }

        $quotedColumn = $pdo->quote($column);
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE {$quotedColumn}");
        return $stmt ? (bool)$stmt->fetchColumn() : false;
    }
}

if (!function_exists('resolver_empleado_id_desde_codigo_asistencia')) {
    function resolver_empleado_id_desde_codigo_asistencia(PDO $pdo, string $codigo): ?int
    {
        $codigo = normalizar_codigo_asistencia($codigo);

        // Formato ofuscado actual: EMP + 12 hex
        if (!preg_match('/^EMP[0-9A-F]{12}$/', $codigo)) {
            return null;
        }

        $sql = 'SELECT id FROM empleados';
        if (asistencia_column_exists($pdo, 'empleados', 'activo')) {
            $sql .= ' WHERE activo = 1';
        }

        $stmt = $pdo->query($sql);
        $empleados = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        foreach ($empleados as $empleado) {
            $id = (int)($empleado['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            if (hash_equals(generar_codigo_asistencia($id), $codigo)) {
                return $id;
            }
        }

        return null;
    }
}
