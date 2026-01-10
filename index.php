<?php
/**
 * SHALOM FACTURA - Punto de Entrada
 * Sistema de Facturación Electrónica
 * Desarrollado por Shalom - Soluciones Digitales con Propósito
 */

require_once __DIR__ . '/bootstrap.php';

// Redirigir según estado de autenticación
if (auth()->check()) {
    redirect(url('dashboard.php'));
} else {
    redirect(url('login.php'));
}
