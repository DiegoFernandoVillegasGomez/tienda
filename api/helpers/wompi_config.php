<?php
/**
 * Configuración para la integración con Wompi
 * Documentación: https://docs.wompi.co/
 */

class WompiConfig {
    // Credenciales de Wompi - REEMPLAZAR CON TUS CLAVES REALES
    // Una vez obtengas tus claves del dashboard, reemplaza estos valores:
    // Ejemplo: const PUBLIC_KEY_TEST = 'pub_test_abc123def456ghi789jkl012';
    const PUBLIC_KEY_TEST = '';
    const PRIVATE_KEY_TEST = '';
    
    // Para producción (obtener después de la aprobación)
    const PUBLIC_KEY_PROD = '8b90ecfd-5e68-48af-b6e3-92cf9d1fcbd4';
    const PRIVATE_KEY_PROD = '296a6b37-b542-40c3-b7b2-06e157bac961';
    
    // URLs de la API de Wompi
    const API_URL_TEST = 'https://sandbox.wompi.co/v1/';
    const API_URL_PROD = 'https://production.wompi.co/v1/';
    
    // Configuración del entorno (true para pruebas, false para producción)
    const IS_TEST_MODE = true;
    
    /**
     * Obtiene la clave pública según el entorno
     */
    public static function getPublicKey() {
        return self::IS_TEST_MODE ? self::PUBLIC_KEY_TEST : self::PUBLIC_KEY_PROD;
    }
    
    /**
     * Obtiene la clave privada según el entorno
     */
    public static function getPrivateKey() {
        return self::IS_TEST_MODE ? self::PRIVATE_KEY_TEST : self::PRIVATE_KEY_PROD;
    }
    
    /**
     * Obtiene la URL de la API según el entorno
     */
    public static function getApiUrl() {
        return self::IS_TEST_MODE ? self::API_URL_TEST : self::API_URL_PROD;
    }
    
    /**
     * Configuración de moneda
     * USD = Dólar Estadounidense (El Salvador)
     * COP = Colombia, MXN = México, PEN = Perú, CLP = Chile
     */
    const CURRENCY = 'USD'; // El Salvador usa USD como moneda oficial
    
    /**
     * URLs de redirección después del pago
     * Cambiar localhost por tu dominio real cuando tengas hosting
     */
    const REDIRECT_URL_SUCCESS = 'http://localhost/coffeeshop/views/public/payment-success.html';
    const REDIRECT_URL_FAILURE = 'http://localhost/coffeeshop/views/public/payment-failure.html';
    
    /**
     * URL para recibir webhooks de Wompi
     * Importante: Debe ser HTTPS en producción para El Salvador
     */
    const WEBHOOK_URL = 'http://localhost/coffeeshop/api/services/public/wompi-webhook.php';
}
