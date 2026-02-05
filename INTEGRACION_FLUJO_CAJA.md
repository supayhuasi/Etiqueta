# Integración del Módulo Flujo de Caja

Para que el módulo sea accesible desde el menú principal, agrega estos enlaces en tu `includes/header.php` o donde tengas el menú:

## 1. Opción Simple - Agregar al Menú Existente

Si tienes un menú lateral, agrega:

```html
<!-- En la sección de Finanzas o Administración -->
<li class="nav-item">
    <a class="nav-link" href="/flujo_caja.php">
        <i class="bi bi-cash-flow"></i> Flujo de Caja
    </a>
</li>

<li class="nav-item ms-2">
    <a class="nav-link" href="/flujo_caja_reportes.php">
        <i class="bi bi-graph-up"></i> Reportes
    </a>
</li>

<li class="nav-item ms-2">
    <a class="nav-link" href="/pagos_sueldos_parciales.php">
        <i class="bi bi-wallet2"></i> Pagos de Sueldos
    </a>
</li>
```

## 2. Estructura de Menú Recomendada

Si usas Bootstrap o similar:

```html
<!-- Dropdown Finanzas -->
<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="#" id="financesDropdown" role="button" data-bs-toggle="dropdown">
        <i class="bi bi-graph-up"></i> Finanzas
    </a>
    <ul class="dropdown-menu" aria-labelledby="financesDropdown">
        <li><a class="dropdown-item" href="/flujo_caja.php">📊 Flujo de Caja</a></li>
        <li><a class="dropdown-item" href="/flujo_caja_ingreso.php">➕ Nuevo Ingreso</a></li>
        <li><a class="dropdown-item" href="/flujo_caja_egreso.php">➖ Nuevo Egreso</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="/pagos_sueldos_parciales.php">👨‍💼 Pagos de Sueldos</a></li>
        <li><a class="dropdown-item" href="/flujo_caja_reportes.php">📈 Reportes</a></li>
    </ul>
</li>
```

## 3. Permisos y Roles

Si tienes sistema de permisos, agrega:

```php
// En tu tabla de permisos o roles:
$permisos = [
    'flujo_caja.ver' => 'Ver flujo de caja',
    'flujo_caja.crear_ingreso' => 'Crear ingresos',
    'flujo_caja.crear_egreso' => 'Crear egresos',
    'flujo_caja.editar' => 'Editar transacciones',
    'flujo_caja.eliminar' => 'Eliminar transacciones',
    'flujo_caja.reportes' => 'Ver reportes',
];
```

## 4. Rutas Recomendadas

Si usas un router:

```php
// Rutas del módulo de flujo de caja
Route::group(['middleware' => 'auth'], function() {
    // Dashboard
    Route::get('/flujo_caja', 'FlujoCajaController@index');
    
    // Ingresos
    Route::get('/flujo_caja_ingreso', 'FlujoCajaController@ingresoForm');
    Route::post('/flujo_caja_ingreso', 'FlujoCajaController@guardarIngreso');
    
    // Egresos
    Route::get('/flujo_caja_egreso', 'FlujoCajaController@egresoForm');
    Route::post('/flujo_caja_egreso', 'FlujoCajaController@guardarEgreso');
    
    // Edición
    Route::get('/flujo_caja_editar/:id', 'FlujoCajaController@editarForm');
    Route::post('/flujo_caja_editar/:id', 'FlujoCajaController@guardarEdicion');
    
    // Eliminación
    Route::get('/flujo_caja_eliminar/:id', 'FlujoCajaController@eliminarForm');
    Route::post('/flujo_caja_eliminar/:id', 'FlujoCajaController@eliminar');
    
    // Reportes
    Route::get('/flujo_caja_reportes', 'FlujoCajaController@reportes');
    
    // Pagos parciales
    Route::get('/pagos_sueldos_parciales', 'FlujoCajaController@pagosParciales');
});
```

## 5. Integración con Otros Módulos

### Desde pedidos.php
Cuando se registre un pago de pedido, puedes crear automáticamente un ingreso:

```php
// Después de guardar el pago
if ($monto_pagado > 0) {
    $stmt = $pdo->prepare("
        INSERT INTO flujo_caja 
        (fecha, tipo, categoria, descripcion, monto, referencia, id_referencia, usuario_id)
        VALUES (NOW(), 'ingreso', 'Pago Pedido', ?, 'Pedido ' . ?, ?, ?)
    ");
    $stmt->execute([
        'Pago pedido #' . $numero_pedido,
        $numero_pedido,
        $pedido_id,
        $_SESSION['user_id']
    ]);
}
```

### Desde sueldos.php
Cuando se registre un pago de sueldo:

```php
// Ya está integrado - flujo_caja_egreso.php lo maneja automáticamente
// Solo asegúrate de usar pagos_sueldos_parciales en lugar de pagos_sueldos
```

### Desde gastos.php
Cuando se apruebe un gasto:

```php
// Crear egreso en flujo de caja
if ($estado_nuevo === 'aprobado') {
    $stmt = $pdo->prepare("
        INSERT INTO flujo_caja 
        (fecha, tipo, categoria, descripcion, monto, referencia, id_referencia, usuario_id)
        VALUES (?, 'egreso', ?, ?, ?, 'Gasto ' . ?, ?)
    ");
    $stmt->execute([
        $fecha,
        $tipo_gasto,
        $descripcion,
        $monto,
        $numero_gasto,
        $gasto_id,
        $_SESSION['user_id']
    ]);
}
```

## 6. Instalación Completa

```bash
# 1. Copiar archivos (ya están creados)
cd /ruta/del/proyecto

# 2. Ejecutar setup (accede por navegador)
# http://tu-sitio.com/setup_flujo_caja.php

# 3. Agregar menú en header.php (ver punto 2 arriba)

# 4. Acceder al módulo
# http://tu-sitio.com/flujo_caja.php
```

## 7. Verificación de Instalación

```php
// script de prueba - flujo_caja_test.php
<?php
require 'config.php';

// Verificar tablas
$tablas = ['flujo_caja', 'pagos_sueldos_parciales', 'flujo_caja_resumen'];
foreach ($tablas as $tabla) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$tabla'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Tabla $tabla existe<br>";
    } else {
        echo "✗ Tabla $tabla NO existe<br>";
    }
}

// Verificar archivos
$archivos = [
    'flujo_caja.php',
    'flujo_caja_ingreso.php',
    'flujo_caja_egreso.php',
    'flujo_caja_editar.php',
    'flujo_caja_eliminar.php',
    'flujo_caja_reportes.php',
    'pagos_sueldos_parciales.php'
];

foreach ($archivos as $archivo) {
    if (file_exists($archivo)) {
        echo "✓ Archivo $archivo existe<br>";
    } else {
        echo "✗ Archivo $archivo NO existe<br>";
    }
}
?>
```

## 8. Problemas Comunes y Soluciones

### Error: "Tabla no existe"
→ Ejecutar `setup_flujo_caja.php` nuevamente

### Error: "Usuario no autenticado"
→ Verificar que `auth/check.php` esté correctamente configurado

### Montos no muestran bien
→ Verificar formato de `number_format()` en tu timezone

### No aparece el menú
→ Agregar los enlaces en `includes/header.php`

## 9. Próximas Mejoras Sugeridas

- [ ] Integración automática de pagos desde Mercado Pago
- [ ] Integración automática de gastos desde módulo de gastos
- [ ] Presupuestos vs. Real
- [ ] Proyecciones de flujo futuro
- [ ] Exportar a Excel
- [ ] Gráficos de ingresos/egresos
- [ ] Alertas de saldo bajo
- [ ] Arqueo de caja
