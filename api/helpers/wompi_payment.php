<?php
require_once('wompi_config.php');

/**
 * Clase para manejar la integración con Wompi
 */
class WompiPayment {
    
    private $publicKey;
    private $privateKey;
    private $apiUrl;
    
    public function __construct() {
        $this->publicKey = WompiConfig::getPublicKey();
        $this->privateKey = WompiConfig::getPrivateKey();
        $this->apiUrl = WompiConfig::getApiUrl();
    }
    
    /**
     * Crear un token de aceptación
     */
    public function createAcceptanceToken() {
        $url = $this->apiUrl . 'merchants/' . $this->publicKey;
        
        $response = $this->makeRequest($url, 'GET');
        
        if ($response && isset($response['data']['presigned_acceptance']['acceptance_token'])) {
            return $response['data']['presigned_acceptance']['acceptance_token'];
        }
        
        return false;
    }
    
    /**
     * Crear una transacción
     */
    public function createTransaction($data) {
        $url = $this->apiUrl . 'transactions';
        
        $transactionData = [
            'amount_in_cents' => $data['amount_in_cents'],
            'currency' => WompiConfig::CURRENCY,
            'customer_email' => $data['customer_email'],
            'payment_method' => [
                'type' => 'CARD',
                'installments' => 1
            ],
            'reference' => $data['reference'],
            'payment_source_id' => $data['payment_source_id'],
            'acceptance_token' => $data['acceptance_token'],
            'customer_data' => [
                'phone_number' => $data['phone_number'],
                'full_name' => $data['full_name']
            ],
            'shipping_address' => [
                'address_line_1' => $data['address'],
                'country' => 'CO',
                'region' => $data['region'],
                'city' => $data['city'],
                'name' => $data['full_name'],
                'phone_number' => $data['phone_number']
            ],
            'redirect_url' => WompiConfig::REDIRECT_URL_SUCCESS
        ];
        
        return $this->makeRequest($url, 'POST', $transactionData);
    }
    
    /**
     * Crear una fuente de pago (tokenizar tarjeta)
     */
    public function createPaymentSource($cardData) {
        $url = $this->apiUrl . 'payment_sources';
        
        $paymentSourceData = [
            'type' => 'CARD',
            'token' => $cardData['token'],
            'customer_email' => $cardData['customer_email'],
            'acceptance_token' => $cardData['acceptance_token']
        ];
        
        return $this->makeRequest($url, 'POST', $paymentSourceData);
    }
    
    /**
     * Consultar el estado de una transacción
     */
    public function getTransaction($transactionId) {
        $url = $this->apiUrl . 'transactions/' . $transactionId;
        return $this->makeRequest($url, 'GET');
    }
    
    /**
     * Realizar petición HTTP a la API de Wompi
     */
    private function makeRequest($url, $method = 'GET', $data = null) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->privateKey
            ]
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            error_log("Wompi API Error: " . $error);
            return false;
        }
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return $decodedResponse;
        } else {
            error_log("Wompi API HTTP Error: " . $httpCode . " - " . $response);
            return false;
        }
    }
    
    /**
     * Verificar integridad de webhook
     */
    public function verifyWebhookSignature($payload, $signature, $timestamp) {
        $secret = WompiConfig::getPrivateKey();
        $checksum = hash('sha256', $payload . $timestamp . $secret);
        
        return hash_equals($signature, $checksum);
    }
}
