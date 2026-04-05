<?php
/**
 * PayPal Configuration
 * 
 * Obtén tus credenciales en: https://developer.paypal.com
 */

// Cargar variables de entorno
require_once __DIR__ . '/env-loader.php';

define('PAYPAL_MODE', env('PAYPAL_MODE', 'sandbox'));

// PayPal Client ID y Secret - desde variables de entorno
define('PAYPAL_CLIENT_ID', env('PAYPAL_CLIENT_ID', ''));
define('PAYPAL_CLIENT_SECRET', env('PAYPAL_CLIENT_SECRET', ''));

// URLs de PayPal
if (PAYPAL_MODE === 'sandbox') {
    define('PAYPAL_API_URL', 'https://api-m.sandbox.paypal.com');
    define('PAYPAL_WEBHOOK_ID', env('PAYPAL_WEBHOOK_ID', 'TU_WEBHOOK_ID_SANDBOX'));
} else {
    define('PAYPAL_API_URL', 'https://api-m.paypal.com');
    define('PAYPAL_WEBHOOK_ID', env('PAYPAL_WEBHOOK_ID', 'TU_WEBHOOK_ID_LIVE'));
}

// URL de tu sitio para webhooks
define('PAYPAL_BASE_URL', 'https://ligawocdominicana.com');
