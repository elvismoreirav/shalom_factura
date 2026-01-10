# 🌿 Shalom Factura

**Sistema de Facturación Electrónica para Ecuador**

Desarrollado por **Shalom - Soluciones Digitales con Propósito**

---

## 📋 Descripción

Shalom Factura es un sistema completo de facturación electrónica diseñado para profesionales y empresas en Ecuador. Cumple con todas las normativas del SRI y permite la emisión de comprobantes electrónicos de manera sencilla y eficiente.

## ✨ Características

### Facturación Electrónica
- ✅ Emisión de Facturas Electrónicas
- ✅ Notas de Crédito y Débito
- ✅ Comprobantes de Retención
- ✅ Guías de Remisión
- ✅ Integración con Web Services del SRI
- ✅ Firma electrónica de documentos

### Gestión Comercial
- 📊 Dashboard con estadísticas en tiempo real
- 👥 Gestión completa de clientes
- 📦 Catálogo de servicios y productos
- 📝 Cotizaciones y proformas
- 💰 Control de pagos y cuentas por cobrar
- 📈 Reportes detallados

### Características Técnicas
- 🔒 Sistema multi-empresa (multi-tenant)
- 👤 Control de usuarios y roles
- 📱 Diseño responsive
- 🌐 Interfaz moderna con Tailwind CSS
- 📊 Grillas de datos con Handsontable
- 🔐 Autenticación segura

## 🛠️ Requisitos del Sistema

- **PHP** 8.1 o superior
- **MySQL** 8.0 o superior
- **Apache** 2.4+ con mod_rewrite
- **Extensiones PHP:**
  - PDO MySQL
  - OpenSSL
  - cURL
  - SOAP
  - mbstring
  - json

## 📁 Estructura del Proyecto

```
shalom-factura/
├── api/                    # APIs RESTful
│   ├── clientes/
│   ├── facturas/
│   └── servicios/
├── assets/                 # Recursos estáticos
│   ├── css/
│   ├── js/
│   └── img/
├── clientes/              # Módulo de clientes
├── config/                # Configuración
├── core/                  # Clases del núcleo
│   ├── Auth.php
│   ├── Database.php
│   └── Helpers.php
├── database/              # Scripts SQL
├── empresa/               # Configuración empresa
├── facturas/              # Módulo de facturas
├── logs/                  # Logs del sistema
├── modules/               # Modelos de datos
├── servicios/             # Módulo de servicios
├── templates/             # Plantillas HTML
├── uploads/               # Archivos subidos
├── bootstrap.php          # Inicialización
├── index.php              # Punto de entrada
└── login.php              # Autenticación
```

## 🚀 Instalación

### 1. Clonar o descargar el proyecto

```bash
git clone https://github.com/shalom/shalom-factura.git
cd shalom-factura
```

### 2. Configurar la base de datos

```bash
# Crear base de datos
mysql -u root -p -e "CREATE DATABASE shalom_factura CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Importar esquema
mysql -u root -p shalom_factura < database/schema.sql
```

### 3. Configurar el entorno

Editar `config/config.php` con los datos de conexión:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'shalom_factura');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_contraseña');
```

### 4. Configurar permisos

```bash
chmod -R 755 uploads/
chmod -R 755 logs/
```

### 5. Configurar Apache

Asegúrese de que mod_rewrite esté habilitado y que el archivo `.htaccess` sea procesado.

### 6. Crear usuario administrador

```sql
INSERT INTO usuarios (uuid, empresa_id, rol_id, email, password, nombre, apellido, estado)
VALUES (
    UUID(),
    1,
    1,
    'admin@ejemplo.com',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password
    'Administrador',
    'Sistema',
    'activo'
);
```

## 🎨 Paleta de Colores

| Color | Hex | Uso |
|-------|-----|-----|
| Verde Primario | `#1e4d39` | Color principal |
| Blanco Marfil | `#f9f8f4` | Fondo |
| Verde Oliva | `#A3B7A5` | Acentos |
| Gris Cálido | `#73796F` | Texto secundario |
| Dorado Premium | `#D6C29A` | Destacados |

## 📚 Módulos

### Dashboard
Vista general con KPIs, gráficos de ventas y accesos rápidos.

### Clientes
- Registro completo de clientes
- Validación de cédula y RUC
- Historial de facturación
- Condiciones comerciales

### Servicios
- Catálogo de servicios y productos
- Categorías personalizables
- Configuración de IVA
- Servicios recurrentes

### Facturación
- Emisión de facturas con Handsontable
- Búsqueda rápida de clientes y servicios
- Cálculo automático de impuestos
- Envío al SRI

### Reportes
- Ventas por período
- Declaración de impuestos
- Cuentas por cobrar
- Exportación a Excel y PDF

## 🔒 Seguridad

- Contraseñas hasheadas con bcrypt
- Protección CSRF
- Validación de sesiones
- Control de acceso por roles
- Log de auditoría

## 📞 Soporte

Para soporte técnico o consultas:

- **Email:** soporte@shalom.ec
- **Web:** https://shalom.ec

## 📄 Licencia

Copyright © 2024 Shalom - Soluciones Digitales con Propósito

Todos los derechos reservados.

---

**Shalom** - *Soluciones Digitales con Propósito* 🌿
