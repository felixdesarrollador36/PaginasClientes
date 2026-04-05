<?php
/**
 * PayPal Webhook Handler
 * 
 * Este archivo recibe las notificaciones de PayPal
 * Configura la URL del webhook en: https://developer.paypal.com/apps
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/paypal.php';
require_once __DIR__ . '/controllers/PayPalController.php';

header('Content-Type: application/json');

// Verificar que es una solicitud de PayPal
$paypalDebug = file_get_contents('php://input');
$headers = getallheaders();

// Verificar firma del webhook (en producción)
// En desarrollo, simplemente procesamos el evento

$data = json_decode($paypalDebug, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

// Log del webhook para debugging
$logFile = __DIR__ . '/../logs/paypal_webhook.log';
if (!is_dir(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}
file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . print_r($data, true) . "\n", FILE_APPEND);

// Procesar el webhook
$paypal = new PayPalController();
$result = $paypal->handleWebhook($data);

echo json_encode($result);
