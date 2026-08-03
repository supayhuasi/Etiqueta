# Panel de Administración Integrado - STUL

Este es el panel de administración integrado que incluye todos los módulos de la empresa:

## 📁 Estructura de Módulos

### 🛍️ **CATÁLOGO**
- **Categorías** - Gestionar categorías de productos
- **Productos** - Administrar productos del ecommerce
- **Matriz de Precios** - Configurar matriz de precios para productos variables
- **Listas de Precios** - Crear y gestionar listas de precios
- **Precios Ecommerce** - Configurar precios específicos para ecommerce

### 🏢 **EMPRESA**
- **Información** - Datos generales de la empresa
- **Mercado Pago** - Configuración de integración de pago
- **Inventario** - Gestionar stock
- **Pedidos** - Administrar pedidos de ecommerce
- **Órdenes de Producción** - Crear y gestionar órdenes
- **Facturación** - Gestionar facturación
- **Cotizaciones** - Crear cotizaciones para clientes

### 🛒 **COMPRAS**
- **Proveedores** - Gestionar proveedores
- **Compras** - Registrar compras
- **Ajustes de Inventario** - Ajustar stock

---

## 👥 **RECURSOS HUMANOS**

### 💰 [Sueldos](sueldos/sueldos.php)
Gestiona el pago de sueldos a empleados:
- Crear/editar sueldos
- Generar recibos
- Gestionar conceptos de pago
- Crear plantillas de sueldo

**Archivos:** `sueldos/sueldos.php`, `sueldos/plantillas.php`, `sueldos/sueldo_editar.php`

### 📋 [Asistencias](asistencias/asistencias.php)
Registra y controla la asistencia de empleados:
- Cargar asistencias diarias
- Gestionar horarios
- Generar reportes
- Editar asistencias

**Archivos:** `asistencias/asistencias.php`, `asistencias/asistencias_horarios.php`

---

## 💳 **FINANZAS**

### 🏦 [Cheques](cheques/cheques.php)
Gestiona cheques de la empresa:
- Crear/editar cheques
- Cambiar estado de cheques
- Registrar pagos
- Filtrar por mes y estado

**Archivos:** `cheques/cheques.php`, `cheques/cheques_crear.php`, `cheques/cheques_pagar.php`

### 💸 [Gastos](gastos/gastos.php)
Registra y controla gastos operativos:
- Crear/editar gastos
- Categorizar gastos
- Cambiar estado de gastos
- Generar reportes
- Gestionar tipos de gastos

**Archivos:** `gastos/gastos.php`, `gastos/gastos_crear.php`, `gastos/tipos_gastos.php`

---

## 🔧 Setup / Instalación

Cada módulo tiene un archivo `setup_*.php` para inicializar las tablas de base de datos:

- `asistencias/setup_asistencias.php` - Crear tabla de asistencias
- `sueldos/setup_sueldos.php` - Crear tabla de sueldos
- `cheques/setup_cheques.php` - Crear tabla de cheques
- `gastos/setup_gastos.php` - Crear tabla de gastos

Si necesitas (re)inicializar un módulo, accede al archivo de setup correspondiente.

---

## 🚀 Acceso Rápido

Desde el menú lateral del admin, puedes acceder directamente a:
- **Sueldos** → `sueldos/sueldos.php`
- **Plantillas** → `sueldos/plantillas.php`
- **Asistencias** → `asistencias/asistencias.php`
- **Cheques** → `cheques/cheques.php`
- **Gastos** → `gastos/gastos.php`

---

## 📝 Notas Técnicas

Todos los módulos utilizan:
- **Header integrado:** `../includes/header.php` - Proporciona autenticación y navbar
- **Config centralizada:** `../../config.php` - Conexión a base de datos
- **Bootstrap 5** - Framework CSS para diseño responsive
- **PDO** - Para consultas a base de datos

Los módulos están completamente integrados con el sistema principal de autenticación.

---

## 🔒 Permisos

- **Admin** - Acceso total a todos los módulos
- **Usuario normal** - Acceso limitado (según rol)

Verifica el header para confirmar el nivel de acceso requerido para cada módulo.
