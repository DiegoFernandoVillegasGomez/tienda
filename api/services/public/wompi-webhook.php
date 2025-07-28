<?php
require_once('../../helpers/wompi_payment.php');
require_once('../../models/data/pedido_data.php');

// Obtener el contenido del webhook
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

// Obtener headers para verificar la firma
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
$timestamp = $_SERVER['HTTP_X_TIMESTAMP'] ?? '';

// Log para depuración (opcional)
error_log("Wompi Webhook recibido: " . $payload);

// Verificar la firma del webhook
$wompi = new WompiPayment();
if (!$wompi->verifyWebhookSignature($payload, $signature, $timestamp)) {
    http_response_code(401);
    exit('Firma inválida');
}

// Procesar el evento del webhook
if (isset($data['event']) && isset($data['data'])) {
    $event = $data['event'];
    $transaction = $data['data'];
    
    switch ($event) {
        case 'transaction.updated':
            // Actualizar el estado del pedido según el estado de la transacción
            $transactionId = $transaction['id'];
            $status = $transaction['status'];
            $reference = $transaction['reference'];
            
            // Buscar el pedido por referencia
            $pedido = new PedidoData();
            
            // Aquí necesitarías implementar un método para buscar por referencia
            // Por ahora, vamos a usar la sesión o implementar la búsqueda
            
            switch ($status) {
                case 'APPROVED':
                    // Pago aprobado - finalizar el pedido
                    error_log("Pago aprobado para transacción: " . $transactionId);
                    // $pedido->approvePayment($reference);
                    break;
                    
                case 'DECLINED':
                    // Pago rechazado
                    error_log("Pago rechazado para transacción: " . $transactionId);
                    // $pedido->declinePayment($reference);
                    break;
                    
                case 'VOIDED':
                    // Pago anulado
                    error_log("Pago anulado para transacción: " . $transactionId);
                    // $pedido->voidPayment($reference);
                    break;
                    
                case 'ERROR':
                    // Error en el pago
                    error_log("Error en pago para transacción: " . $transactionId);
                    break;
            }
            break;
            
        default:
            error_log("Evento no manejado: " . $event);
    }
}

// Responder con éxito
http_response_code(200);
echo json_encode(['status' => 'success']);
?>
