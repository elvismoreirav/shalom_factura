<?php
/**
 * SHALOM FACTURA - Logout
 */

require_once __DIR__ . '/bootstrap.php';

auth()->logout();

flash('success', 'Ha cerrado sesión correctamente');
redirect(url('login.php'));
