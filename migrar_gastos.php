<?php
require 'config.php';

// Alterar tabla gastos - cambiar beneficiario a empleado_id
try {
    // Backup del campo anterior
    $pdo->exec("ALTER TABLE gastos ADD COLUMN beneficiario_nombre VARCHAR(255) AFTER beneficiario");
    
    // Copiar datos
    $pdo->exec("UPDATE gastos SET beneficiario_nombre = beneficiario WHERE beneficiario IS NOT NULL");
    
    // Eliminar la columna antigua de beneficiario
    $pdo->exec("ALTER TABLE gastos DROP COLUMN beneficiario");
    
    // Renombrar la nueva columna
    $pdo->exec("ALTER TABLE gastos CHANGE beneficiario_nombre beneficiario VARCHAR(255)");
    
    // Agregar nueva columna con foreign key
    $pdo->exec("
        ALTER TABLE gastos 
        ADD COLUMN empleado_id INT AFTER tipo_gasto_id,
        ADD FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE SET NULL
    ");
    
    echo "✓ Tabla de gastos actualizada correctamente";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
