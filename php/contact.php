<?php
// Habilitar reporte de errores para desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Definir constante para seguridad
define('SITE_ROOT', true);

// Cargar configuración
$config = require __DIR__ . '/config.php';

// Configurar cabeceras para respuesta JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Validar método de la petición
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]));
}

// Validar origen de la petición
if (isset($config['SECURITY']['ALLOWED_ORIGINS'])) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (!in_array($origin, $config['SECURITY']['ALLOWED_ORIGINS'])) {
        http_response_code(403);
        die(json_encode([
            'success' => false,
            'message' => 'Origen no permitido'
        ]));
    }
}

// Validar rate limiting
if ($config['SECURITY']['RATE_LIMIT']['ENABLED']) {
    session_start();
    $ip = $_SERVER['REMOTE_ADDR'];
    $now = time();
    
    if (!isset($_SESSION['contact_attempts'])) {
        $_SESSION['contact_attempts'] = [];
    }
    
    // Limpiar intentos antiguos
    $_SESSION['contact_attempts'] = array_filter(
        $_SESSION['contact_attempts'],
        function($attempt) use ($now, $config) {
            return ($now - $attempt) < $config['SECURITY']['RATE_LIMIT']['TIME_WINDOW'];
        }
    );
    
    // Verificar límite de intentos
    if (count($_SESSION['contact_attempts']) >= $config['SECURITY']['RATE_LIMIT']['ATTEMPTS']) {
        http_response_code(429);
        die(json_encode([
            'success' => false,
            'message' => 'Demasiados intentos. Por favor, inténtalo de nuevo más tarde.'
        ]));
    }
    
    // Registrar intento
    $_SESSION['contact_attempts'][] = $now;
}

// Validar y limpiar datos del formulario
$data = [
    'nombre' => trim($_POST['name'] ?? ''),
    'email' => filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL),
    'telefono' => trim($_POST['phone'] ?? ''),
    'asunto' => trim($_POST['subject'] ?? ''),
    'mensaje' => trim($_POST['message'] ?? '')
];

// Validaciones
$errors = [];

// Validar campos requeridos
foreach ($config['FORM']['REQUIRED_FIELDS'] as $field) {
    if (empty($data[$field])) {
        $errors[] = "El campo " . ucfirst($field) . " es requerido";
    }
}

// Validar formato de email
if ($data['email'] === false) {
    $errors[] = "El formato del correo electrónico no es válido";
}

// Validar longitud máxima
foreach ($config['FORM']['MAX_LENGTH'] as $field => $max) {
    if (isset($data[$field]) && mb_strlen($data[$field]) > $max) {
        $errors[] = "El campo " . ucfirst($field) . " no debe exceder los $max caracteres";
    }
}

// Si hay errores, devolverlos
if (!empty($errors)) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'message' => 'Error de validación',
        'errors' => $errors
    ]));
}

// Cargar PHPMailer
require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // Configuración del servidor
    $mail->isSMTP();
    $mail->Host = $config['SMTP']['HOST'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['SMTP']['USER'];
    $mail->Password = $config['SMTP']['PASS'];
    $mail->SMTPSecure = $config['SMTP']['SECURE'];
    $mail->Port = $config['SMTP']['PORT'];
    $mail->CharSet = 'UTF-8';

    // Remitente
    $mail->setFrom(
        $config['SMTP']['FROM_EMAIL'],
        $config['SMTP']['FROM_NAME']
    );
    
    // Destinatario
    $mail->addAddress(
        $config['SMTP']['TO_EMAIL'],
        $config['SMTP']['TO_NAME']
    );

    // Responder a
    $mail->addReplyTo($data['email'], $data['nombre']);

    // Contenido
    $mail->isHTML(true);
    $mail->Subject = 'Nuevo mensaje de contacto: ' . ($data['asunto'] ?: 'Sin asunto');
    
    $body = "
    <h2>Nuevo mensaje de contacto</h2>
    <p><strong>Nombre:</strong> {$data['nombre']}</p>
    <p><strong>Email:</strong> {$data['email']}</p>
    " . ($data['telefono'] ? "<p><strong>Teléfono:</strong> {$data['telefono']}</p>" : "") . "
    " . ($data['asunto'] ? "<p><strong>Asunto:</strong> {$data['asunto']}</p>" : "") . "
    <h3>Mensaje:</h3>
    <p>" . nl2br(htmlspecialchars($data['mensaje'])) . "</p>
    ";
    
    $mail->Body = $body;
    $mail->AltBody = strip_tags(str_replace(["<br>", "<br/>", "<br />"], "\n", $body));

    // Enviar correo
    $mail->send();

    // Enviar copia al remitente si es diferente
    if ($data['email'] !== $config['SMTP']['USER']) {
        $mail->clearAddresses();
        $mail->addAddress($data['email'], $data['nombre']);
        $mail->Subject = 'Hemos recibido tu mensaje - ' . $config['SMTP']['TO_NAME'];
        $mail->Body = "
        <p>Hola {$data['nombre']},</p>
        <p>Hemos recibido tu mensaje correctamente. Nos pondremos en contacto contigo lo antes posible.</p>
        <p>Este es un mensaje automático, por favor no respondas a este correo.</p>
        <p>Atentamente,<br>{$config['SMTP']['TO_NAME']}</p>
        ";
        $mail->AltBody = "Hola {$data['nombre']},\n\nHemos recibido tu mensaje correctamente. Nos pondremos en contacto contigo lo antes posible.\n\nEste es un mensaje automático, por favor no respondas a este correo.\n\nAtentamente,\n{$config['SMTP']['TO_NAME']}";
        
        $mail->send();
    }

    // Respuesta de éxito
    echo json_encode([
        'success' => true,
        'message' => 'Mensaje enviado correctamente. ¡Gracias por contactarnos!'
    ]);

} catch (Exception $e) {
    // Log del error
    error_log('Error al enviar el correo: ' . $e->getMessage());
    
    // Respuesta de error
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error al enviar el mensaje. Por favor, inténtalo de nuevo más tarde.'
    ]);
}