// Constante para completar la ruta de la API.
const WOMPI_API = 'services/public/wompi.php';
const PEDIDO_API = 'services/public/pedido.php';


// Variables para el widget de Wompi
let wompiWidget = null;
let acceptanceToken = null;
let publicKey = null;
let orderTotal = 0;

// Elementos del DOM
const ORDER_SUMMARY = document.getElementById('orderSummary');
const TOTAL_AMOUNT = document.getElementById('totalAmount');
const CUSTOMER_FORM = document.getElementById('customerForm');
const PROCESS_PAYMENT_BTN = document.getElementById('processPaymentBtn');
const LOADING_INDICATOR = document.getElementById('loadingIndicator');

// Campos del formulario
const FULL_NAME = document.getElementById('fullName');
const EMAIL = document.getElementById('email');
const PHONE = document.getElementById('phone');

// Campos de la tarjeta
const CARD_NUMBER = document.getElementById('cardNumber');
const CARD_EXPIRY = document.getElementById('cardExpiry');
const CARD_CVC = document.getElementById('cardCvc');
const CARD_NAME = document.getElementById('cardName');

// Método del evento para cuando el documento ha cargado.
document.addEventListener('DOMContentLoaded', () => {
    // Llamada a la función para mostrar el encabezado y pie del documento.
    loadTemplate();
    // Se establece el título del contenido principal.
    MAIN_TITLE.textContent = 'Procesar Pago';
    // Cargar el resumen del pedido
    loadOrderSummary();
    // Inicializar Wompi
    initializeWompi();
    // Agregar eventos para formatear tarjeta
    setupCardFormatting();
});

/*
*   Función para configurar el formateo de los campos de tarjeta.
*/
const setupCardFormatting = () => {
    // Formatear número de tarjeta
    CARD_NUMBER.addEventListener('input', (e) => {
        let value = e.target.value.replace(/\s/g, '');
        let formattedValue = value.replace(/(.{4})/g, '$1 ').trim();
        e.target.value = formattedValue;
    });
    
    // Formatear fecha de vencimiento
    CARD_EXPIRY.addEventListener('input', (e) => {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length >= 2) {
            value = value.substring(0, 2) + '/' + value.substring(2, 4);
        }
        e.target.value = value;
    });
    
    // Solo números en CVC
    CARD_CVC.addEventListener('input', (e) => {
        e.target.value = e.target.value.replace(/\D/g, '');
    });
    
    // Configurar el botón de pago
    PROCESS_PAYMENT_BTN.addEventListener('click', () => {
        processPaymentWithCard();
    });
};

/*
*   Función asíncrona para cargar el resumen del pedido.
*/
const loadOrderSummary = async () => {
    // Petición para obtener los productos del carrito.
    const DATA = await fetchData(PEDIDO_API, 'readDetail');
    
    if (DATA.status) {
        let total = 0;
        let summaryHTML = '';
        
        DATA.dataset.forEach(row => {
            const subtotal = parseFloat(row.subtotal);
            total += subtotal;
            
            summaryHTML += `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <strong>${row.nombre_producto}</strong><br>
                        <small class="text-muted">Cantidad: ${row.cantidad_producto}</small>
                    </div>
                    <div class="text-end">
                        <strong>$${subtotal.toFixed(2)}</strong>
                    </div>
                </div>
            `;
        });
        
        ORDER_SUMMARY.innerHTML = summaryHTML;
        TOTAL_AMOUNT.textContent = total.toFixed(2);
        orderTotal = total;
    } else {
        sweetAlert(3, 'No hay productos en el carrito', true, 'cart.html');
    }
};

/*
*   Función asíncrona para inicializar Wompi.
*/
const initializeWompi = async () => {
    try {
        // Obtener el token de aceptación y la clave pública
        const DATA = await fetchData(WOMPI_API, 'getAcceptanceToken');
        
        if (DATA.status) {
            acceptanceToken = DATA.dataset.acceptance_token;
            publicKey = DATA.dataset.public_key;
            
            // Inicializar el widget de Wompi
            initializeWompiWidget();
        } else {
            sweetAlert(2, 'Error al inicializar el sistema de pagos: ' + DATA.error, false);
        }
    } catch (error) {
        sweetAlert(2, 'Error al conectar con el sistema de pagos', false);
        console.error('Error inicializando Wompi:', error);
    }
};

/*
*   Función para inicializar el widget de Wompi.
*/
const initializeWompiWidget = () => {
    // Configuración del widget
    const checkout = new WidgetCheckout({
        currency: 'USD', // El Salvador usa USD
        amountInCents: Math.round(orderTotal * 100), // Convertir a centavos
        reference: 'ORDER_' + Date.now(),
        publicKey: publicKey,
        containerId: 'wompi-widget',
        onReady: () => {
            console.log('Widget de Wompi listo');
        },
        onTokenGenerated: (token) => {
            console.log('Token generado:', token);
            // Habilitar el botón de procesar pago
            PROCESS_PAYMENT_BTN.disabled = false;
            
            // Guardar el token para usar en el procesamiento
            PROCESS_PAYMENT_BTN.onclick = () => processPayment(token);
        }
    });
    
    wompiWidget = checkout;
};

/*
*   Función asíncrona para procesar el pago con tarjeta manual.
*/
const processPaymentWithCard = async () => {
    // Validar formularios
    if (!validateCustomerForm() || !validateCardForm()) {
        return;
    }
    
    // Mostrar indicador de carga
    showLoading(true);
    PROCESS_PAYMENT_BTN.disabled = true;
    
    try {
        // Crear token de tarjeta con Wompi
        const cardToken = await createCardToken();
        
        if (!cardToken) {
            throw new Error('No se pudo generar el token de la tarjeta');
        }
        
        // Continuar con el proceso de pago
        await processPayment(cardToken);
        
    } catch (error) {
        showLoading(false);
        PROCESS_PAYMENT_BTN.disabled = false;
        sweetAlert(2, 'Error al procesar el pago: ' + error.message, false);
        console.error('Error procesando pago:', error);
    }
};

/*
*   Función para crear token de tarjeta con Wompi.
*/
const createCardToken = async () => {
    try {
        // Preparar datos de la tarjeta
        const cardData = {
            number: CARD_NUMBER.value.replace(/\s/g, ''),
            cvc: CARD_CVC.value,
            exp_month: CARD_EXPIRY.value.split('/')[0],
            exp_year: '20' + CARD_EXPIRY.value.split('/')[1],
            card_holder: CARD_NAME.value
        };
        
        // Llamar a la API de Wompi para crear token
        const response = await fetch('https://sandbox.wompi.co/v1/tokens/cards', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${publicKey}`
            },
            body: JSON.stringify(cardData)
        });
        
        const result = await response.json();
        
        if (result.status === 'CREATED' && result.data) {
            return result.data.id;
        } else {
            throw new Error(result.error ? result.error.reason : 'Error desconocido');
        }
        
    } catch (error) {
        console.error('Error creando token:', error);
        return null;
    }
};

/*
*   Función asíncrona para procesar el pago con el token.
*/
const processPayment = async (cardToken) => {
    try {
        // Preparar los datos del cliente
        const customerData = {
            fullName: FULL_NAME.value.trim(),
            email: EMAIL.value.trim(),
            phone: PHONE.value.trim()
        };
        
        // Preparar los datos del pago
        const paymentData = {
            token: cardToken,
            amount: Math.round(orderTotal * 100), // Convertir a centavos
            currency: 'USD',
            customer: customerData,
            acceptance_token: acceptanceToken,
            reference: 'ORDER_' + Date.now()
        };
        
        // Llamar a nuestra API para procesar el pago
        const FORM = new FormData();
        FORM.append('action', 'processPayment');
        FORM.append('paymentData', JSON.stringify(paymentData));
        
        const RESPONSE = await fetchData(WOMPI_API, 'processPayment', FORM);
        
        if (RESPONSE.status) {
            // Verificar el estado del pago
            await checkPaymentStatus(RESPONSE.dataset.transaction_id);
        } else {
            throw new Error(RESPONSE.error || 'Error al procesar el pago');
        }
        
    } catch (error) {
        showLoading(false);
        PROCESS_PAYMENT_BTN.disabled = false;
        sweetAlert(2, 'Error al procesar el pago: ' + error.message, false);
        console.error('Error procesando pago:', error);
    }
};

/*
*   Función para validar el formulario de tarjeta.
*/
const validateCardForm = () => {
    const cardNumber = CARD_NUMBER.value.replace(/\s/g, '');
    
    if (!cardNumber || cardNumber.length < 13) {
        sweetAlert(2, 'Por favor ingrese un número de tarjeta válido', false);
        CARD_NUMBER.focus();
        return false;
    }
    
    if (!CARD_EXPIRY.value || CARD_EXPIRY.value.length !== 5) {
        sweetAlert(2, 'Por favor ingrese una fecha de vencimiento válida (MM/AA)', false);
        CARD_EXPIRY.focus();
        return false;
    }
    
    if (!CARD_CVC.value || CARD_CVC.value.length < 3) {
        sweetAlert(2, 'Por favor ingrese un CVC válido', false);
        CARD_CVC.focus();
        return false;
    }
    
    if (!CARD_NAME.value.trim()) {
        sweetAlert(2, 'Por favor ingrese el nombre que aparece en la tarjeta', false);
        CARD_NAME.focus();
        return false;
    }
    
    return true;
};

/*
*   Función asíncrona para verificar el estado del pago.
*/
const checkPaymentStatus = async (transactionId) => {
    const FORM = new FormData();
    FORM.append('transaction_id', transactionId);
    
    const RESPONSE = await fetchData(WOMPI_API, 'checkPaymentStatus', FORM);
    
    if (RESPONSE.status) {
        const status = RESPONSE.dataset.status;
        const statusMessage = RESPONSE.dataset.status_message;
        
        showLoading(false);
        
        switch (status) {
            case 'APPROVED':
                sweetAlert(1, 'Pago procesado exitosamente', true, 'payment-success.html');
                break;
            case 'PENDING':
                sweetAlert(3, 'Pago pendiente de aprobación', true, 'cart.html');
                break;
            case 'DECLINED':
                sweetAlert(2, 'Pago rechazado: ' + statusMessage, false);
                PROCESS_PAYMENT_BTN.disabled = false;
                break;
            case 'VOIDED':
                sweetAlert(2, 'Pago anulado', false);
                PROCESS_PAYMENT_BTN.disabled = false;
                break;
            default:
                sweetAlert(2, 'Estado de pago desconocido: ' + status, false);
                PROCESS_PAYMENT_BTN.disabled = false;
        }
    } else {
        showLoading(false);
        sweetAlert(2, 'Error al verificar el estado del pago', false);
        PROCESS_PAYMENT_BTN.disabled = false;
    }
};

/*
*   Función para validar el formulario del cliente.
*/
const validateCustomerForm = () => {
    if (!FULL_NAME.value.trim()) {
        sweetAlert(2, 'Por favor ingrese su nombre completo', false);
        FULL_NAME.focus();
        return false;
    }
    
    if (!EMAIL.value.trim()) {
        sweetAlert(2, 'Por favor ingrese su correo electrónico', false);
        EMAIL.focus();
        return false;
    }
    
    if (!PHONE.value.trim()) {
        sweetAlert(2, 'Por favor ingrese su teléfono', false);
        PHONE.focus();
        return false;
    }
    
    return true;
};

/*
*   Función para mostrar/ocultar el indicador de carga.
*/
const showLoading = (show) => {
    if (show) {
        LOADING_INDICATOR.style.display = 'block';
        CUSTOMER_FORM.style.display = 'none';
        document.getElementById('wompi-widget').style.display = 'none';
    } else {
        LOADING_INDICATOR.style.display = 'none';
        CUSTOMER_FORM.style.display = 'block';
        document.getElementById('wompi-widget').style.display = 'block';
    }
};
