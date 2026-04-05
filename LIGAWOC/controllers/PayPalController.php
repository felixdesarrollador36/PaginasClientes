<?php
/**
 * PayPal Controller - Integración de pagos con PayPal
 */

require_once __DIR__ . '/../config/paypal.php';

class PayPalController {
    private $accessToken;
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Obtiene el token de acceso a la API de PayPal
     */
    private function getAccessToken() {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        if (trim((string) PAYPAL_CLIENT_ID) === '' || trim((string) PAYPAL_CLIENT_SECRET) === '') {
            return null;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, PAYPAL_API_URL . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        
        if (isset($data['access_token'])) {
            $this->accessToken = $data['access_token'];
            return $this->accessToken;
        }

        return null;
    }

    /**
     * Crea una orden de pago en PayPal
     */
    public function createOrder($packageId, $amount, $currency = 'USD') {
        $package = $this->db->fetch("SELECT * FROM coin_packages WHERE id = ?", [$packageId]);
        
        if (!$package) {
            return ['success' => false, 'error' => 'Paquete no encontrado'];
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['success' => false, 'error' => 'Error al conectar con PayPal'];
        }

        $total = number_format($package['price_usd'], 2, '.', '');
        
        $data = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => 'WOC_COINS_' . $packageId . '_' . time(),
                    'description' => 'WOC Coins - Paquete ' . $package['coins'] . ' monedas',
                    'soft_descriptor' => 'WOC Coins',
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => $total,
                        'breakdown' => [
                            'item_total' => [
                                'currency_code' => $currency,
                                'value' => $total
                            ]
                        ]
                    ],
                    'items' => [
                        [
                            'name' => 'WOC Coins - Paquete ' . $package['coins'],
                            'description' => $package['coins'] . ' WOC Coins (+' . $package['bonus_coins'] . ' bonus)',
                            'sku' => 'WOC_PACKAGE_' . $packageId,
                            'unit_amount' => [
                                'currency_code' => $currency,
                                'value' => $total
                            ],
                            'quantity' => '1'
                        ]
                    ]
                ]
            ],
            'application_context' => [
                'brand_name' => 'Liga WOC',
                'landing_page' => 'BILLING',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
                'return_url' => PAYPAL_BASE_URL . '/buy-coins/success',
                'cancel_url' => PAYPAL_BASE_URL . '/buy-coins/cancel'
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, PAYPAL_API_URL . '/v2/checkout/orders');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode === 201 || $httpCode === 200) {
            // Guardar orden en la base de datos
            $this->db->insert(
                "INSERT INTO paypal_orders (user_id, package_id, paypal_order_id, amount, status, created_at) VALUES (?, ?, ?, ?, 'created', NOW())",
                [$_SESSION['user_id'], $packageId, $result['id'], $total]
            );
            
            return [
                'success' => true,
                'orderId' => $result['id'],
                'approveUrl' => $result['links'][1]['href'] // Approve link
            ];
        }

        return ['success' => false, 'error' => 'Error al crear orden: ' . ($result['message'] ?? 'Unknown error')];
    }

    /**
     * Captura un pago de PayPal
     */
    public function captureOrder($orderId) {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['success' => false, 'error' => 'Error al conectar con PayPal'];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, PAYPAL_API_URL . '/v2/checkout/orders/' . $orderId . '/capture');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode === 201 || $httpCode === 200) {
            // Actualizar orden en la base de datos
            $this->db->update(
                "UPDATE paypal_orders SET status = 'completed', paypal_capture_id = ?, updated_at = NOW() WHERE paypal_order_id = ?",
                [$result['purchase_units'][0]['payments']['captures'][0]['id'], $orderId]
            );

            // Obtener información de la orden
            $order = $this->db->fetch("SELECT * FROM paypal_orders WHERE paypal_order_id = ?", [$orderId]);
            
            if ($order) {
                $package = $this->db->fetch("SELECT * FROM coin_packages WHERE id = ?", [$order['package_id']]);
                
                if ($package) {
                    $totalCoins = $package['coins'] + $package['bonus_coins'];
                    
                    // Agregar monedas al usuario
                    $this->db->update(
                        "INSERT INTO user_coins (user_id, coins, lifetime_coins) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE coins = coins + ?, lifetime_coins = lifetime_coins + ?",
                        [$order['user_id'], $totalCoins, $totalCoins, $totalCoins, $totalCoins]
                    );

                    // Registrar compra
                    $this->db->insert(
                        "INSERT INTO coin_purchases (user_id, package_id, amount, coins_purchased, bonus_coins, payment_method, transaction_id, status) VALUES (?, ?, ?, ?, ?, 'paypal', ?, 'completed')",
                        [$order['user_id'], $order['package_id'], $order['amount'], $package['coins'], $package['bonus_coins'], $result['purchase_units'][0]['payments']['captures'][0]['id']]
                    );

                    return [
                        'success' => true,
                        'coins' => $totalCoins,
                        'user_id' => $order['user_id']
                    ];
                }
            }
        }

        // Si falla, marcar como fallida
        $this->db->update(
            "UPDATE paypal_orders SET status = 'failed', updated_at = NOW() WHERE paypal_order_id = ?",
            [$orderId]
        );

        return ['success' => false, 'error' => 'Error al capturar pago'];
    }

    /**
     * Procesa webhook de PayPal
     */
    public function handleWebhook($data) {
        $eventType = $data['event_type'] ?? '';
        
        if ($eventType === 'CHECKOUT.ORDER.APPROVED') {
            // Orden aprobada
            $orderId = $data['resource']['id'] ?? '';
            $this->db->update(
                "UPDATE paypal_orders SET status = 'approved', updated_at = NOW() WHERE paypal_order_id = ?",
                [$orderId]
            );
        } elseif ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            // Pago completado
            $captureId = $data['resource']['id'] ?? '';
            $this->db->update(
                "UPDATE paypal_orders SET status = 'completed', updated_at = NOW() WHERE paypal_capture_id = ?",
                [$captureId]
            );
        } elseif ($eventType === 'PAYMENT.CAPTURE.DENIED' || $eventType === 'PAYMENT.CAPTURE.DECLINED') {
            // Pago denegado
            $captureId = $data['resource']['id'] ?? '';
            $this->db->update(
                "UPDATE paypal_orders SET status = 'denied', updated_at = NOW() WHERE paypal_capture_id = ?",
                [$captureId]
            );
        }

        return ['success' => true];
    }

    /**
     * Obtiene el estado de una orden
     */
    public function getOrderStatus($orderId) {
        return $this->db->fetch("SELECT * FROM paypal_orders WHERE paypal_order_id = ?", [$orderId]);
    }

    /**
     * Verifica y acredita las monedas después del pago
     */
    public function verifyAndCreditOrder($orderID, $packageId, $userId) {
        // Verificar si ya fue procesado
        $existing = $this->db->fetch("SELECT * FROM paypal_orders WHERE paypal_order_id = ?", [$orderID]);
        if ($existing && $existing['status'] === 'completed') {
            return ['success' => false, 'error' => 'Esta orden ya fue procesada'];
        }

        // Obtener el paquete
        $package = $this->db->fetch("SELECT * FROM coin_packages WHERE id = ?", [$packageId]);
        if (!$package) {
            return ['success' => false, 'error' => 'Paquete no encontrado'];
        }

        // Obtener token de acceso
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['success' => false, 'error' => 'Error al conectar con PayPal'];
        }

        // Verificar la orden con PayPal
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, PAYPAL_API_URL . '/v2/checkout/orders/' . $orderID);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $orderData = json_decode($response, true);

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => 'Orden no válida'];
        }

        // Verificar que el estado sea COMPLETED
        if ($orderData['status'] !== 'COMPLETED') {
            return ['success' => false, 'error' => 'El pago no está completado'];
        }

        // Obtener el capture ID
        $captureId = $orderData['purchase_units'][0]['payments']['captures'][0]['id'] ?? '';

        // Creditar las monedas
        $totalCoins = $package['coins'] + $package['bonus_coins'];
        
        $this->db->update(
            "INSERT INTO user_coins (user_id, coins, lifetime_coins) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE coins = coins + ?, lifetime_coins = lifetime_coins + ?",
            [$userId, $totalCoins, $totalCoins, $totalCoins, $totalCoins]
        );

        // Registrar la compra
        $this->db->insert(
            "INSERT INTO coin_purchases (user_id, package_id, amount, coins_purchased, bonus_coins, payment_method, transaction_id, status) VALUES (?, ?, ?, ?, ?, 'paypal', ?, 'completed')",
            [$userId, $packageId, $package['price_usd'], $package['coins'], $package['bonus_coins'], $captureId]
        );

        // Guardar registro de PayPal
        $this->db->insert(
            "INSERT INTO paypal_orders (user_id, package_id, paypal_order_id, paypal_capture_id, amount, status, created_at) VALUES (?, ?, ?, ?, ?, 'completed', NOW())",
            [$userId, $packageId, $orderID, $captureId, $package['price_usd']]
        );

        return [
            'success' => true,
            'coins' => $totalCoins
        ];
    }
}
