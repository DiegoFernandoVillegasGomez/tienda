<?php
// Se incluyen las clases necesarias.
require_once('../../helpers/wompi_payment.php');
require_once('../../models/data/pedido_data.php');

// Se comprueba si existe una acción a realizar, de lo contrario se finaliza el script con un mensaje de error.
if (isset($_GET['action'])) {
    // Se crea una sesión o se reanuda la actual para poder utilizar variables de sesión en el script.
    session_start();
    // Se instancia la clase correspondiente.
    $wompi = new WompiPayment();
    $pedido = new PedidoData();
    // Se declara e inicializa un arreglo para guardar el resultado que retorna la API.
    $result = array('status' => 0, 'session' => 0, 'message' => null, 'error' => null, 'exception' => null, 'dataset' => null);
    
    // Se verifica si existe una sesión iniciada como cliente.
    if (isset($_SESSION['idCliente'])) {
        $result['session'] = 1;
        
        // Se compara la acción a realizar cuando un cliente ha iniciado sesión.
        switch ($_GET['action']) {
            // Acción para obtener el token de aceptación de Wompi
            case 'getAcceptanceToken':
                $acceptanceToken = $wompi->createAcceptanceToken();
                if ($acceptanceToken) {
                    $result['status'] = 1;
                    $result['dataset'] = [
                        'acceptance_token' => $acceptanceToken,
                        'public_key' => WompiConfig::getPublicKey()
                    ];
                } else {
                    $result['error'] = 'No se pudo obtener el token de aceptación';
                }
                break;
            
            // Acción para crear una fuente de pago
            case 'createPaymentSource':
                $_POST = Validator::validateForm($_POST);
                
                $cardData = [
                    'token' => $_POST['token'],
                    'customer_email' => $_POST['customer_email'],
                    'acceptance_token' => $_POST['acceptance_token']
                ];
                
                $paymentSource = $wompi->createPaymentSource($cardData);
                
                if ($paymentSource && isset($paymentSource['data']['id'])) {
                    $result['status'] = 1;
                    $result['dataset'] = [
                        'payment_source_id' => $paymentSource['data']['id']
                    ];
                } else {
                    $result['error'] = 'No se pudo crear la fuente de pago';
                    if (isset($paymentSource['error'])) {
                        $result['error'] .= ': ' . $paymentSource['error']['reason'];
                    }
                }
                break;
            
            // Acción para procesar el pago
            case 'processPayment':
                $_POST = Validator::validateForm($_POST);
                
                // Verificar que existe un pedido activo
                if (!$pedido->getOrder()) {
                    $result['error'] = 'No hay un pedido activo para procesar';
                    break;
                }
                
                // Obtener el total del pedido
                $orderDetails = $pedido->readDetail();
                $total = 0;
                foreach ($orderDetails as $detail) {
                    $total += $detail['subtotal'];
                }
                
                // Convertir a centavos
                $amountInCents = intval($total * 100);
                
                // Generar referencia única
                $reference = 'ORDER_' . $_SESSION['idCliente'] . '_' . time();
                
                $transactionData = [
                    'amount_in_cents' => $amountInCents,
                    'customer_email' => $_POST['customer_email'],
                    'reference' => $reference,
                    'payment_source_id' => $_POST['payment_source_id'],
                    'acceptance_token' => $_POST['acceptance_token'],
                    'phone_number' => $_POST['phone_number'],
                    'full_name' => $_POST['full_name'],
                    'address' => $_POST['address'],
                    'region' => $_POST['region'],
                    'city' => $_POST['city']
                ];
                
                $transaction = $wompi->createTransaction($transactionData);
                
                if ($transaction && isset($transaction['data']['id'])) {
                    // Guardar la información de la transacción en el pedido
                    $pedido->setReferenciaPago($reference);
                    $pedido->setTransactionId($transaction['data']['id']);
                    
                    $result['status'] = 1;
                    $result['dataset'] = [
                        'transaction_id' => $transaction['data']['id'],
                        'reference' => $reference,
                        'status' => $transaction['data']['status'],
                        'amount' => $total
                    ];
                } else {
                    $result['error'] = 'No se pudo procesar el pago';
                    if (isset($transaction['error'])) {
                        $result['error'] .= ': ' . $transaction['error']['reason'];
                    }
                }
                break;
            
            // Acción para consultar el estado de una transacción
            case 'checkPaymentStatus':
                $_POST = Validator::validateForm($_POST);
                
                $transaction = $wompi->getTransaction($_POST['transaction_id']);
                
                if ($transaction && isset($transaction['data'])) {
                    $result['status'] = 1;
                    $result['dataset'] = [
                        'transaction_id' => $transaction['data']['id'],
                        'status' => $transaction['data']['status'],
                        'status_message' => $transaction['data']['status_message']
                    ];
                    
                    // Si el pago fue aprobado, finalizar el pedido
                    if ($transaction['data']['status'] === 'APPROVED') {
                        $pedido->finishOrder();
                    }
                } else {
                    $result['error'] = 'No se pudo consultar el estado del pago';
                }
                break;
            
            default:
                $result['error'] = 'Acción no disponible dentro de la sesión';
        }
    } else {
        $result['error'] = 'Debe iniciar sesión para realizar pagos';
    }
    
    // Se obtiene la excepción del servidor de base de datos por si ocurrió un problema.
    $result['exception'] = Database::getException();
    // Se indica el tipo de contenido a mostrar y su respectivo conjunto de caracteres.
    header('Content-type: application/json; charset=utf-8');
    // Se imprime el resultado en formato JSON y se retorna al controlador.
    print(json_encode($result));
} else {
    print(json_encode('Recurso no disponible'));
}
