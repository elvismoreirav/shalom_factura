-- =====================================================
-- SHALOM FACTURA - Sistema de Facturación Electrónica
-- Base de Datos MySQL 8.0+
-- Schema Unificado v2.0
-- Desarrollado por Shalom - Soluciones Digitales con Propósito
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS shalom_factura 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE shalom_factura;

-- =====================================================
-- TABLAS DE CONFIGURACIÓN DEL SISTEMA
-- =====================================================

CREATE TABLE sys_parametros (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    categoria VARCHAR(50) NOT NULL,
    clave VARCHAR(100) NOT NULL,
    valor TEXT,
    tipo ENUM('string', 'int', 'float', 'bool', 'json') DEFAULT 'string',
    descripcion VARCHAR(255),
    editable TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_categoria_clave (categoria, clave),
    INDEX idx_categoria (categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cat_tipos_identificacion (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(2) NOT NULL UNIQUE COMMENT 'Código SRI: 04=RUC, 05=Cédula, 06=Pasaporte, 07=Consumidor Final, 08=Exterior',
    nombre VARCHAR(50) NOT NULL,
    longitud INT,
    patron_validacion VARCHAR(100),
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cat_tipos_comprobante (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(2) NOT NULL UNIQUE COMMENT 'Código SRI: 01=Factura, 04=Nota Crédito, 05=Nota Débito, 06=Guía Remisión, 07=Retención',
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cat_impuestos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(4) NOT NULL COMMENT 'Código SRI',
    codigo_porcentaje VARCHAR(4) NOT NULL COMMENT 'Código porcentaje SRI',
    nombre VARCHAR(100) NOT NULL,
    porcentaje DECIMAL(5,2) NOT NULL,
    tipo ENUM('IVA', 'ICE', 'IRBPNR', 'IR') NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    fecha_inicio DATE,
    fecha_fin DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_codigo_porcentaje (codigo, codigo_porcentaje),
    INDEX idx_tipo (tipo),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cat_formas_pago (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(2) NOT NULL UNIQUE COMMENT 'Código SRI',
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cat_monedas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(3) NOT NULL UNIQUE COMMENT 'Código ISO 4217',
    nombre VARCHAR(50) NOT NULL,
    simbolo VARCHAR(5) NOT NULL,
    es_principal TINYINT(1) DEFAULT 0,
    tasa_cambio DECIMAL(12,6) DEFAULT 1.000000,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cat_codigos_retencion (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('RENTA', 'IVA') NOT NULL,
    codigo VARCHAR(10) NOT NULL,
    descripcion VARCHAR(300) NOT NULL,
    porcentaje DECIMAL(5,2) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tipo_codigo (tipo, codigo),
    INDEX idx_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLAS DE EMPRESAS (MULTI-TENANT)
-- =====================================================

CREATE TABLE empresas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    ruc VARCHAR(13) NOT NULL UNIQUE,
    razon_social VARCHAR(300) NOT NULL,
    nombre_comercial VARCHAR(300),
    direccion_matriz TEXT NOT NULL,
    email VARCHAR(255),
    telefono VARCHAR(20),
    website VARCHAR(255),
    logo_path VARCHAR(500),
    obligado_contabilidad ENUM('SI', 'NO') DEFAULT 'NO',
    contribuyente_especial VARCHAR(20),
    agente_retencion VARCHAR(20),
    tipo_contribuyente ENUM('PERSONA_NATURAL', 'SOCIEDAD', 'RIMPE_EMPRENDEDOR', 'RIMPE_NEGOCIO_POPULAR') DEFAULT 'PERSONA_NATURAL',
    regimen_microempresa TINYINT(1) DEFAULT 0,
    ambiente_sri ENUM('1', '2') DEFAULT '1' COMMENT '1=Pruebas, 2=Producción',
    tipo_emision ENUM('1', '2') DEFAULT '1' COMMENT '1=Normal, 2=Indisponibilidad',
    firma_electronica_path VARCHAR(500),
    firma_electronica_password VARCHAR(255),
    firma_electronica_vencimiento DATE,
    estado ENUM('activo', 'suspendido', 'inactivo') DEFAULT 'activo',
    fecha_constitucion DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_ruc (ruc),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE establecimientos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    codigo VARCHAR(3) NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    direccion TEXT NOT NULL,
    email VARCHAR(255),
    telefono VARCHAR(20),
    es_matriz TINYINT(1) DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    UNIQUE KEY uk_empresa_codigo (empresa_id, codigo),
    INDEX idx_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE puntos_emision (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    establecimiento_id INT UNSIGNED NOT NULL,
    codigo VARCHAR(3) NOT NULL,
    descripcion VARCHAR(200),
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (establecimiento_id) REFERENCES establecimientos(id) ON DELETE CASCADE,
    UNIQUE KEY uk_establecimiento_codigo (establecimiento_id, codigo),
    INDEX idx_establecimiento (establecimiento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE secuenciales (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    punto_emision_id INT UNSIGNED NOT NULL,
    tipo_comprobante_id INT UNSIGNED NOT NULL,
    secuencial_actual INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (punto_emision_id) REFERENCES puntos_emision(id) ON DELETE CASCADE,
    FOREIGN KEY (tipo_comprobante_id) REFERENCES cat_tipos_comprobante(id),
    UNIQUE KEY uk_punto_tipo (punto_emision_id, tipo_comprobante_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLAS DE USUARIOS Y PERMISOS
-- =====================================================

CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NULL,
    nombre VARCHAR(50) NOT NULL,
    slug VARCHAR(50) NOT NULL,
    descripcion TEXT,
    es_sistema TINYINT(1) DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    INDEX idx_empresa (empresa_id),
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permisos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    modulo VARCHAR(50) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_modulo (modulo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rol_permisos (
    rol_id INT UNSIGNED NOT NULL,
    permiso_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (rol_id, permiso_id),
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    empresa_id INT UNSIGNED NULL,
    rol_id INT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    avatar_path VARCHAR(500),
    idioma VARCHAR(5) DEFAULT 'es',
    zona_horaria VARCHAR(50) DEFAULT 'America/Guayaquil',
    tema ENUM('light', 'dark', 'auto') DEFAULT 'light',
    email_verificado_at TIMESTAMP NULL,
    ultimo_login TIMESTAMP NULL,
    intentos_fallidos INT DEFAULT 0,
    bloqueado_hasta TIMESTAMP NULL,
    token_recordar VARCHAR(100),
    estado ENUM('activo', 'inactivo', 'suspendido') DEFAULT 'activo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE SET NULL,
    FOREIGN KEY (rol_id) REFERENCES roles(id),
    INDEX idx_empresa (empresa_id),
    INDEX idx_email (email),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    tipo ENUM('password_reset', 'email_verify', 'api_token') NOT NULL,
    token VARCHAR(255) NOT NULL,
    expira_at TIMESTAMP NOT NULL,
    usado_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT,
    last_activity INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLAS DE CLIENTES
-- =====================================================

CREATE TABLE clientes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    empresa_id INT UNSIGNED NOT NULL,
    tipo_identificacion_id INT UNSIGNED NOT NULL,
    identificacion VARCHAR(20) NOT NULL,
    razon_social VARCHAR(300) NOT NULL,
    nombre_comercial VARCHAR(300),
    email VARCHAR(255),
    telefono VARCHAR(20),
    celular VARCHAR(20),
    direccion TEXT,
    ciudad VARCHAR(100),
    provincia VARCHAR(100),
    es_contribuyente_especial TINYINT(1) DEFAULT 0,
    aplica_retencion TINYINT(1) DEFAULT 0,
    porcentaje_descuento DECIMAL(5,2) DEFAULT 0.00,
    dias_credito INT DEFAULT 0,
    limite_credito DECIMAL(12,2) DEFAULT 0.00,
    notas TEXT,
    fecha_nacimiento DATE,
    contacto_nombre VARCHAR(200),
    contacto_cargo VARCHAR(100),
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    created_by INT UNSIGNED,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (tipo_identificacion_id) REFERENCES cat_tipos_identificacion(id),
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY uk_empresa_identificacion (empresa_id, identificacion),
    INDEX idx_empresa (empresa_id),
    INDEX idx_identificacion (identificacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLAS DE SERVICIOS/PRODUCTOS
-- =====================================================

CREATE TABLE categorias_servicio (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    color VARCHAR(7) DEFAULT '#1e4d39',
    icono VARCHAR(50),
    orden INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    UNIQUE KEY uk_empresa_nombre (empresa_id, nombre),
    INDEX idx_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE servicios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    empresa_id INT UNSIGNED NOT NULL,
    categoria_id INT UNSIGNED,
    codigo VARCHAR(50) NOT NULL,
    codigo_auxiliar VARCHAR(50),
    nombre VARCHAR(300) NOT NULL,
    descripcion TEXT,
    precio_unitario DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    costo DECIMAL(12,4) DEFAULT 0.0000,
    impuesto_id INT UNSIGNED NOT NULL,
    aplica_ice TINYINT(1) DEFAULT 0,
    ice_id INT UNSIGNED,
    tipo ENUM('servicio', 'producto', 'paquete') DEFAULT 'servicio',
    unidad_medida VARCHAR(50) DEFAULT 'UNIDAD',
    es_recurrente TINYINT(1) DEFAULT 0,
    periodo_recurrencia ENUM('diario', 'semanal', 'quincenal', 'mensual', 'trimestral', 'semestral', 'anual') NULL,
    controla_stock TINYINT(1) DEFAULT 0,
    stock_actual DECIMAL(12,4) DEFAULT 0.0000,
    stock_minimo DECIMAL(12,4) DEFAULT 0.0000,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    created_by INT UNSIGNED,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES categorias_servicio(id) ON DELETE SET NULL,
    FOREIGN KEY (impuesto_id) REFERENCES cat_impuestos(id),
    FOREIGN KEY (ice_id) REFERENCES cat_impuestos(id),
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY uk_empresa_codigo (empresa_id, codigo),
    INDEX idx_empresa (empresa_id),
    INDEX idx_categoria (categoria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE paquete_servicios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    paquete_id INT UNSIGNED NOT NULL,
    servicio_id INT UNSIGNED NOT NULL,
    cantidad DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
    precio_especial DECIMAL(12,4) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (paquete_id) REFERENCES servicios(id) ON DELETE CASCADE,
    FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE CASCADE,
    UNIQUE KEY uk_paquete_servicio (paquete_id, servicio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLAS DE COTIZACIONES
-- =====================================================

CREATE TABLE cotizaciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    empresa_id INT UNSIGNED NOT NULL,
    cliente_id INT UNSIGNED NOT NULL,
    numero VARCHAR(20) NOT NULL,
    fecha DATE NOT NULL,
    fecha_validez DATE,
    asunto VARCHAR(255),
    introduccion TEXT,
    condiciones TEXT,
    notas TEXT,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_descuento DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    subtotal_sin_impuestos DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_iva DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    estado ENUM('borrador', 'enviada', 'aceptada', 'rechazada', 'vencida', 'facturada') DEFAULT 'borrador',
    enviado_at TIMESTAMP NULL,
    enviado_por INT UNSIGNED,
    aceptado_at TIMESTAMP NULL,
    rechazado_at TIMESTAMP NULL,
    motivo_rechazo TEXT,
    factura_id INT UNSIGNED NULL,
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (enviado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY uk_empresa_numero (empresa_id, numero),
    INDEX idx_empresa (empresa_id),
    INDEX idx_cliente (cliente_id),
    INDEX idx_fecha (fecha),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cotizacion_detalles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cotizacion_id INT UNSIGNED NOT NULL,
    servicio_id INT UNSIGNED NULL,
    codigo VARCHAR(50),
    descripcion VARCHAR(500) NOT NULL,
    cantidad DECIMAL(14,6) NOT NULL,
    precio_unitario DECIMAL(14,6) NOT NULL,
    descuento DECIMAL(14,2) DEFAULT 0.00,
    subtotal DECIMAL(14,2) NOT NULL,
    porcentaje_iva DECIMAL(5,2) DEFAULT 15.00,
    valor_iva DECIMAL(14,2) DEFAULT 0.00,
    total DECIMAL(14,2) NOT NULL,
    orden INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE CASCADE,
    FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE SET NULL,
    INDEX idx_cotizacion (cotizacion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLAS DE FACTURACIÓN ELECTRÓNICA
-- =====================================================

CREATE TABLE facturas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    empresa_id INT UNSIGNED NOT NULL,
    establecimiento_id INT UNSIGNED NOT NULL,
    punto_emision_id INT UNSIGNED NOT NULL,
    cliente_id INT UNSIGNED NOT NULL,
    tipo_comprobante_id INT UNSIGNED NOT NULL,
    cotizacion_id INT UNSIGNED NULL,
    secuencial INT UNSIGNED NOT NULL,
    clave_acceso VARCHAR(49),
    numero_autorizacion VARCHAR(49),
    fecha_autorizacion DATETIME,
    fecha_emision DATE NOT NULL,
    fecha_vencimiento DATE,
    subtotal_sin_impuestos DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_descuento DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    subtotal_iva DECIMAL(14,2) DEFAULT 0.00,
    subtotal_iva_0 DECIMAL(14,2) DEFAULT 0.00,
    total_iva DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_ice DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    propina DECIMAL(14,2) DEFAULT 0.00,
    total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    guia_remision VARCHAR(20),
    xml_path VARCHAR(500),
    xml_firmado_path VARCHAR(500),
    pdf_path VARCHAR(500),
    estado_sri ENUM('pendiente', 'enviado', 'recibida', 'autorizada', 'rechazada', 'no_autorizada', 'devuelta') DEFAULT 'pendiente',
    mensaje_sri TEXT,
    intentos_envio INT DEFAULT 0,
    estado ENUM('borrador', 'emitida', 'anulada') DEFAULT 'borrador',
    estado_pago ENUM('pendiente', 'parcial', 'pagado', 'vencido') DEFAULT 'pendiente',
    documento_referencia_id INT UNSIGNED NULL,
    motivo_modificacion VARCHAR(300),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    created_by INT UNSIGNED,
    anulado_by INT UNSIGNED,
    anulado_at TIMESTAMP NULL,
    motivo_anulacion TEXT,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (establecimiento_id) REFERENCES establecimientos(id),
    FOREIGN KEY (punto_emision_id) REFERENCES puntos_emision(id),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (tipo_comprobante_id) REFERENCES cat_tipos_comprobante(id),
    FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE SET NULL,
    FOREIGN KEY (documento_referencia_id) REFERENCES facturas(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (anulado_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY uk_empresa_tipo_secuencial (empresa_id, establecimiento_id, punto_emision_id, tipo_comprobante_id, secuencial),
    INDEX idx_empresa (empresa_id),
    INDEX idx_cliente (cliente_id),
    INDEX idx_fecha (fecha_emision),
    INDEX idx_estado (estado),
    INDEX idx_estado_sri (estado_sri),
    INDEX idx_clave_acceso (clave_acceso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE factura_detalles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    factura_id INT UNSIGNED NOT NULL,
    servicio_id INT UNSIGNED NULL,
    codigo_principal VARCHAR(25) NOT NULL,
    codigo_auxiliar VARCHAR(25),
    descripcion VARCHAR(300) NOT NULL,
    cantidad DECIMAL(14,6) NOT NULL,
    precio_unitario DECIMAL(14,6) NOT NULL,
    descuento DECIMAL(14,2) DEFAULT 0.00,
    precio_total_sin_impuesto DECIMAL(14,2) NOT NULL,
    orden INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (factura_id) REFERENCES facturas(id) ON DELETE CASCADE,
    FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE SET NULL,
    INDEX idx_factura (factura_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE factura_detalle_impuestos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    factura_detalle_id INT UNSIGNED NOT NULL,
    impuesto_id INT UNSIGNED NOT NULL,
    codigo VARCHAR(4) NOT NULL,
    codigo_porcentaje VARCHAR(4) NOT NULL,
    tarifa DECIMAL(5,2) NOT NULL,
    base_imponible DECIMAL(14,2) NOT NULL,
    valor DECIMAL(14,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (factura_detalle_id) REFERENCES factura_detalles(id) ON DELETE CASCADE,
    FOREIGN KEY (impuesto_id) REFERENCES cat_impuestos(id),
    INDEX idx_detalle (factura_detalle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE factura_impuestos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    factura_id INT UNSIGNED NOT NULL,
    impuesto_id INT UNSIGNED NOT NULL,
    codigo VARCHAR(4) NOT NULL,
    codigo_porcentaje VARCHAR(4) NOT NULL,
    base_imponible DECIMAL(14,2) NOT NULL,
    valor DECIMAL(14,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (factura_id) REFERENCES facturas(id) ON DELETE CASCADE,
    FOREIGN KEY (impuesto_id) REFERENCES cat_impuestos(id),
    INDEX idx_factura (factura_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE factura_pagos_forma (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    factura_id INT UNSIGNED NOT NULL,
    forma_pago_id INT UNSIGNED NOT NULL,
    total DECIMAL(14,2) NOT NULL,
    plazo INT DEFAULT 0,
    unidad_tiempo VARCHAR(20) DEFAULT 'dias',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (factura_id) REFERENCES facturas(id) ON DELETE CASCADE,
    FOREIGN KEY (forma_pago_id) REFERENCES cat_formas_pago(id),
    INDEX idx_factura (factura_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE factura_info_adicional (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    factura_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(300) NOT NULL,
    valor VARCHAR(300) NOT NULL,
    orden INT DEFAULT 0,
    FOREIGN KEY (factura_id) REFERENCES facturas(id) ON DELETE CASCADE,
    INDEX idx_factura (factura_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLAS DE NOTAS DE CRÉDITO
-- =====================================================

CREATE TABLE notas_credito (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    empresa_id INT UNSIGNED NOT NULL,
    establecimiento_id INT UNSIGNED NOT NULL,
    punto_emision_id INT UNSIGNED NOT NULL,
    factura_id INT UNSIGNED NOT NULL,
    cliente_id INT UNSIGNED NOT NULL,
    tipo_comprobante_id INT UNSIGNED NOT NULL,
    secuencial INT UNSIGNED NOT NULL,
    clave_acceso VARCHAR(49),
    numero_autorizacion VARCHAR(49),
    fecha_autorizacion DATETIME,
    fecha_emision DATE NOT NULL,
    subtotal_sin_impuestos DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_descuento DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    subtotal_iva DECIMAL(14,2) DEFAULT 0.00,
    subtotal_iva_0 DECIMAL(14,2) DEFAULT 0.00,
    total_iva DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_ice DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    motivo VARCHAR(300) NOT NULL,
    xml_path VARCHAR(500),
    xml_firmado_path VARCHAR(500),
    estado_sri ENUM('pendiente', 'enviado', 'recibida', 'autorizada', 'rechazada', 'no_autorizada', 'devuelta') DEFAULT 'pendiente',
    mensaje_sri TEXT,
    intentos_envio INT DEFAULT 0,
    estado ENUM('borrador', 'emitida', 'anulada') DEFAULT 'borrador',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    created_by INT UNSIGNED,
    anulado_by INT UNSIGNED,
    anulado_at TIMESTAMP NULL,
    motivo_anulacion TEXT,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (establecimiento_id) REFERENCES establecimientos(id),
    FOREIGN KEY (punto_emision_id) REFERENCES puntos_emision(id),
    FOREIGN KEY (factura_id) REFERENCES facturas(id),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (tipo_comprobante_id) REFERENCES cat_tipos_comprobante(id),
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (anulado_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY uk_empresa_tipo_secuencial (empresa_id, establecimiento_id, punto_emision_id, tipo_comprobante_id, secuencial),
    INDEX idx_empresa (empresa_id),
    INDEX idx_cliente (cliente_id),
    INDEX idx_fecha (fecha_emision),
    INDEX idx_estado (estado),
    INDEX idx_estado_sri (estado_sri),
    INDEX idx_clave_acceso (clave_acceso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE nota_credito_detalles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nota_credito_id INT UNSIGNED NOT NULL,
    servicio_id INT UNSIGNED NULL,
    codigo_principal VARCHAR(25) NOT NULL,
    codigo_auxiliar VARCHAR(25),
    descripcion VARCHAR(300) NOT NULL,
    cantidad DECIMAL(14,6) NOT NULL,
    precio_unitario DECIMAL(14,6) NOT NULL,
    descuento DECIMAL(14,2) DEFAULT 0.00,
    precio_total_sin_impuesto DECIMAL(14,2) NOT NULL,
    orden INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (nota_credito_id) REFERENCES notas_credito(id) ON DELETE CASCADE,
    FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE SET NULL,
    INDEX idx_nota (nota_credito_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE nota_credito_detalle_impuestos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nota_credito_detalle_id INT UNSIGNED NOT NULL,
    impuesto_id INT UNSIGNED NOT NULL,
    codigo VARCHAR(4) NOT NULL,
    codigo_porcentaje VARCHAR(4) NOT NULL,
    tarifa DECIMAL(5,2) NOT NULL,
    base_imponible DECIMAL(14,2) NOT NULL,
    valor DECIMAL(14,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (nota_credito_detalle_id) REFERENCES nota_credito_detalles(id) ON DELETE CASCADE,
    FOREIGN KEY (impuesto_id) REFERENCES cat_impuestos(id),
    INDEX idx_detalle (nota_credito_detalle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE nota_credito_impuestos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nota_credito_id INT UNSIGNED NOT NULL,
    impuesto_id INT UNSIGNED NOT NULL,
    codigo VARCHAR(4) NOT NULL,
    codigo_porcentaje VARCHAR(4) NOT NULL,
    base_imponible DECIMAL(14,2) NOT NULL,
    valor DECIMAL(14,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (nota_credito_id) REFERENCES notas_credito(id) ON DELETE CASCADE,
    FOREIGN KEY (impuesto_id) REFERENCES cat_impuestos(id),
    INDEX idx_nota (nota_credito_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE nota_credito_info_adicional (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nota_credito_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(300) NOT NULL,
    valor VARCHAR(300) NOT NULL,
    orden INT DEFAULT 0,
    FOREIGN KEY (nota_credito_id) REFERENCES notas_credito(id) ON DELETE CASCADE,
    INDEX idx_nota (nota_credito_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLAS DE RETENCIONES
-- =====================================================

CREATE TABLE retenciones_recibidas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    empresa_id INT UNSIGNED NOT NULL,
    factura_id INT UNSIGNED,
    cliente_id INT UNSIGNED NOT NULL,
    numero_comprobante VARCHAR(20) NOT NULL,
    fecha_emision DATE NOT NULL,
    numero_autorizacion VARCHAR(49),
    fecha_autorizacion TIMESTAMP NULL,
    total_retenido_renta DECIMAL(14,2) DEFAULT 0.00,
    total_retenido_iva DECIMAL(14,2) DEFAULT 0.00,
    total_retenido DECIMAL(14,2) DEFAULT 0.00,
    estado ENUM('registrada', 'aplicada', 'anulada') DEFAULT 'registrada',
    observaciones TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    created_by INT UNSIGNED,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (factura_id) REFERENCES facturas(id) ON DELETE SET NULL,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_empresa (empresa_id),
    INDEX idx_factura (factura_id),
    INDEX idx_cliente (cliente_id),
    INDEX idx_fecha (fecha_emision)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE retencion_detalles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    retencion_id INT UNSIGNED NOT NULL,
    codigo_retencion_id INT UNSIGNED NOT NULL,
    base_imponible DECIMAL(14,2) NOT NULL,
    porcentaje DECIMAL(5,2) NOT NULL,
    valor_retenido DECIMAL(14,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (retencion_id) REFERENCES retenciones_recibidas(id) ON DELETE CASCADE,
    FOREIGN KEY (codigo_retencion_id) REFERENCES cat_codigos_retencion(id),
    INDEX idx_retencion (retencion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLAS DE PAGOS
-- =====================================================

CREATE TABLE pagos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    empresa_id INT UNSIGNED NOT NULL,
    cliente_id INT UNSIGNED NOT NULL,
    numero_recibo VARCHAR(20) NOT NULL,
    fecha DATE NOT NULL,
    forma_pago_id INT UNSIGNED NOT NULL,
    monto DECIMAL(14,2) NOT NULL,
    referencia VARCHAR(100),
    banco VARCHAR(100),
    numero_cheque VARCHAR(50),
    observaciones TEXT,
    estado ENUM('confirmado', 'anulado') DEFAULT 'confirmado',
    anulado_at TIMESTAMP NULL,
    anulado_por INT UNSIGNED,
    motivo_anulacion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    created_by INT UNSIGNED,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (forma_pago_id) REFERENCES cat_formas_pago(id),
    FOREIGN KEY (anulado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY uk_empresa_numero (empresa_id, numero_recibo),
    INDEX idx_empresa (empresa_id),
    INDEX idx_cliente (cliente_id),
    INDEX idx_fecha (fecha),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pago_facturas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pago_id INT UNSIGNED NOT NULL,
    factura_id INT UNSIGNED NOT NULL,
    monto DECIMAL(14,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pago_id) REFERENCES pagos(id) ON DELETE CASCADE,
    FOREIGN KEY (factura_id) REFERENCES facturas(id),
    UNIQUE KEY uk_pago_factura (pago_id, factura_id),
    INDEX idx_pago (pago_id),
    INDEX idx_factura (factura_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLAS DE AUDITORÍA Y LOGS
-- =====================================================

CREATE TABLE audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED,
    usuario_id INT UNSIGNED,
    tabla VARCHAR(100) NOT NULL,
    registro_id INT UNSIGNED,
    accion ENUM('crear', 'actualizar', 'eliminar', 'ver', 'login', 'logout', 'otro') NOT NULL,
    datos_anteriores JSON,
    datos_nuevos JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_empresa (empresa_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_tabla (tabla),
    INDEX idx_fecha (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sri_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    tipo_documento VARCHAR(20) NOT NULL,
    documento_id INT UNSIGNED NOT NULL,
    tipo_operacion ENUM('recepcion', 'autorizacion', 'consulta') NOT NULL,
    ambiente ENUM('1', '2') NOT NULL,
    xml_enviado LONGTEXT,
    xml_respuesta LONGTEXT,
    respuesta_json JSON,
    estado VARCHAR(50),
    codigo_error VARCHAR(10),
    mensaje TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    INDEX idx_empresa (empresa_id),
    INDEX idx_documento (tipo_documento, documento_id),
    INDEX idx_fecha (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLAS DE NOTIFICACIONES
-- =====================================================

CREATE TABLE notificaciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    empresa_id INT UNSIGNED,
    usuario_id INT UNSIGNED,
    tipo VARCHAR(50) NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    mensaje TEXT NOT NULL,
    datos JSON,
    leida TINYINT(1) DEFAULT 0,
    leida_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_leida (leida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_queue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED,
    destinatario VARCHAR(255) NOT NULL,
    cc VARCHAR(500),
    asunto VARCHAR(300) NOT NULL,
    cuerpo LONGTEXT NOT NULL,
    adjuntos JSON,
    intentos INT DEFAULT 0,
    max_intentos INT DEFAULT 3,
    estado ENUM('pendiente', 'enviado', 'fallido') DEFAULT 'pendiente',
    error_mensaje TEXT,
    enviado_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DATOS INICIALES
-- =====================================================

INSERT INTO cat_tipos_identificacion (codigo, nombre, longitud, patron_validacion) VALUES
('04', 'RUC', 13, '^[0-9]{13}$'),
('05', 'Cédula', 10, '^[0-9]{10}$'),
('06', 'Pasaporte', NULL, NULL),
('07', 'Consumidor Final', 13, '^9999999999999$'),
('08', 'Identificación del Exterior', NULL, NULL);

INSERT INTO cat_tipos_comprobante (codigo, nombre, descripcion) VALUES
('01', 'Factura', 'Factura electrónica'),
('04', 'Nota de Crédito', 'Nota de crédito electrónica'),
('05', 'Nota de Débito', 'Nota de débito electrónica'),
('06', 'Guía de Remisión', 'Guía de remisión electrónica'),
('07', 'Comprobante de Retención', 'Comprobante de retención electrónico');

INSERT INTO cat_impuestos (codigo, codigo_porcentaje, nombre, porcentaje, tipo, fecha_inicio) VALUES
('2', '0', 'IVA 0%', 0.00, 'IVA', '2024-01-01'),
('2', '2', 'IVA 12%', 12.00, 'IVA', '2024-01-01'),
('2', '3', 'IVA 14%', 14.00, 'IVA', '2024-01-01'),
('2', '4', 'IVA 15%', 15.00, 'IVA', '2024-04-01'),
('2', '5', 'IVA 5%', 5.00, 'IVA', '2024-01-01'),
('2', '6', 'IVA No Objeto', 0.00, 'IVA', '2024-01-01'),
('2', '7', 'IVA Exento', 0.00, 'IVA', '2024-01-01'),
('2', '8', 'IVA Diferenciado', 8.00, 'IVA', '2024-01-01');

INSERT INTO cat_formas_pago (codigo, nombre) VALUES
('01', 'Sin utilización del sistema financiero'),
('15', 'Compensación de deudas'),
('16', 'Tarjeta de débito'),
('17', 'Dinero electrónico'),
('18', 'Tarjeta prepago'),
('19', 'Tarjeta de crédito'),
('20', 'Otros con utilización del sistema financiero'),
('21', 'Endoso de títulos');

INSERT INTO cat_monedas (codigo, nombre, simbolo, es_principal, tasa_cambio) VALUES
('USD', 'Dólar Estadounidense', '$', 1, 1.000000),
('EUR', 'Euro', '€', 0, 1.100000);

INSERT INTO roles (nombre, slug, descripcion, es_sistema) VALUES
('Super Administrador', 'superadmin', 'Acceso total al sistema', 1),
('Administrador', 'admin', 'Administrador de empresa', 1),
('Contador', 'contador', 'Acceso a módulos contables', 1),
('Facturador', 'facturador', 'Emisión de comprobantes', 1),
('Consulta', 'consulta', 'Solo lectura', 1);

INSERT INTO permisos (modulo, nombre, slug) VALUES
('dashboard', 'Ver Dashboard', 'dashboard.ver'),
('empresa', 'Ver Configuración Empresa', 'empresa.ver'),
('empresa', 'Editar Configuración Empresa', 'empresa.editar'),
('empresa', 'Gestionar Establecimientos', 'empresa.establecimientos'),
('empresa', 'Gestionar Puntos Emisión', 'empresa.puntos_emision'),
('clientes', 'Ver Clientes', 'clientes.ver'),
('clientes', 'Crear Clientes', 'clientes.crear'),
('clientes', 'Editar Clientes', 'clientes.editar'),
('clientes', 'Eliminar Clientes', 'clientes.eliminar'),
('servicios', 'Ver Servicios', 'servicios.ver'),
('servicios', 'Crear Servicios', 'servicios.crear'),
('servicios', 'Editar Servicios', 'servicios.editar'),
('servicios', 'Eliminar Servicios', 'servicios.eliminar'),
('cotizaciones', 'Ver Cotizaciones', 'cotizaciones.ver'),
('cotizaciones', 'Crear Cotizaciones', 'cotizaciones.crear'),
('cotizaciones', 'Editar Cotizaciones', 'cotizaciones.editar'),
('cotizaciones', 'Eliminar Cotizaciones', 'cotizaciones.eliminar'),
('cotizaciones', 'Convertir a Factura', 'cotizaciones.convertir'),
('facturas', 'Ver Facturas', 'facturas.ver'),
('facturas', 'Crear Facturas', 'facturas.crear'),
('facturas', 'Anular Facturas', 'facturas.anular'),
('facturas', 'Reenviar al SRI', 'facturas.reenviar'),
('notas_credito', 'Ver Notas de Crédito', 'notas_credito.ver'),
('notas_credito', 'Crear Notas de Crédito', 'notas_credito.crear'),
('notas_credito', 'Anular Notas de Crédito', 'notas_credito.anular'),
('pagos', 'Ver Pagos', 'pagos.ver'),
('pagos', 'Registrar Pagos', 'pagos.crear'),
('pagos', 'Anular Pagos', 'pagos.anular'),
('retenciones', 'Ver Retenciones', 'retenciones.ver'),
('retenciones', 'Registrar Retenciones', 'retenciones.crear'),
('reportes', 'Ver Reportes', 'reportes.ver'),
('reportes', 'Exportar Reportes', 'reportes.exportar'),
('usuarios', 'Ver Usuarios', 'usuarios.ver'),
('usuarios', 'Crear Usuarios', 'usuarios.crear'),
('usuarios', 'Editar Usuarios', 'usuarios.editar'),
('usuarios', 'Eliminar Usuarios', 'usuarios.eliminar'),
('configuracion', 'Ver Configuración', 'configuracion.ver'),
('configuracion', 'Editar Configuración', 'configuracion.editar'),
('auditoria', 'Ver Log de Auditoría', 'auditoria.ver');

INSERT INTO cat_codigos_retencion (tipo, codigo, descripcion, porcentaje) VALUES
('RENTA', '303', 'Honorarios profesionales', 10.00),
('RENTA', '304', 'Predomina el intelecto', 8.00),
('RENTA', '307', 'Predomina mano de obra', 2.00),
('RENTA', '308', 'Entre sociedades', 0.00),
('RENTA', '309', 'Publicidad y comunicación', 1.75),
('RENTA', '310', 'Transporte privado', 1.00),
('RENTA', '312', 'Transferencia de bienes muebles', 1.75),
('RENTA', '319', 'Arrendamiento inmuebles', 8.00),
('RENTA', '320', 'Arrendamiento bienes muebles', 8.00),
('RENTA', '322', 'Seguros y reaseguros', 1.75),
('RENTA', '323', 'Rendimientos financieros', 2.00),
('RENTA', '332', 'Otras compras bienes y servicios no sujetas', 0.00),
('RENTA', '340', 'Otras retenciones aplicables 1%', 1.00),
('RENTA', '341', 'Otras retenciones aplicables 2%', 2.00),
('RENTA', '342', 'Otras retenciones aplicables 8%', 8.00),
('RENTA', '343', 'Otras retenciones aplicables 25%', 25.00),
('IVA', '1', 'Retención 10% IVA', 10.00),
('IVA', '2', 'Retención 20% IVA', 20.00),
('IVA', '3', 'Retención 30% IVA Bienes', 30.00),
('IVA', '4', 'Retención 70% IVA Servicios', 70.00),
('IVA', '5', 'Retención 100% IVA', 100.00);

INSERT INTO sys_parametros (categoria, clave, valor, tipo, descripcion) VALUES
('sistema', 'nombre', 'Shalom Factura', 'string', 'Nombre del sistema'),
('sistema', 'version', '2.0.0', 'string', 'Versión del sistema'),
('sistema', 'moneda_defecto', 'USD', 'string', 'Moneda por defecto'),
('sistema', 'zona_horaria', 'America/Guayaquil', 'string', 'Zona horaria'),
('sistema', 'formato_fecha', 'd/m/Y', 'string', 'Formato de fecha'),
('sistema', 'decimales_cantidad', '4', 'int', 'Decimales en cantidades'),
('sistema', 'decimales_precio', '4', 'int', 'Decimales en precios'),
('sistema', 'decimales_total', '2', 'int', 'Decimales en totales'),
('email', 'smtp_host', '', 'string', 'Servidor SMTP'),
('email', 'smtp_port', '587', 'int', 'Puerto SMTP'),
('email', 'smtp_user', '', 'string', 'Usuario SMTP'),
('email', 'smtp_password', '', 'string', 'Contraseña SMTP'),
('email', 'smtp_encryption', 'tls', 'string', 'Encriptación SMTP'),
('email', 'from_name', 'Shalom Factura', 'string', 'Nombre remitente'),
('email', 'from_email', '', 'string', 'Email remitente'),
('sri', 'wsdl_recepcion_pruebas', 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl', 'string', 'WSDL Recepción Pruebas'),
('sri', 'wsdl_autorizacion_pruebas', 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl', 'string', 'WSDL Autorización Pruebas'),
('sri', 'wsdl_recepcion_produccion', 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl', 'string', 'WSDL Recepción Producción'),
('sri', 'wsdl_autorizacion_produccion', 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl', 'string', 'WSDL Autorización Producción');

SET FOREIGN_KEY_CHECKS = 1;
