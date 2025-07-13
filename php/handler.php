<?php
// Iniciar el manejo de sesiones si se va a usar para rate limiting
session_start();

// Definir una constante para la raíz del sitio, para que config.php funcione
define('SITE_ROOT', dirname(__DIR__));

// Cargar la configuración
$config = require_once SITE_ROOT . '/config.php';

// --- Medidas Anti-Spam y Seguridad Adicionales ---

// 1. Límite de Envíos (Rate Limiting)
if ($config['SECURITY']['RATE_LIMIT']['ENABLED']) {
    $limitConfig = $config['SECURITY']['RATE_LIMIT'];
    $currentTime = time();
    
    // Limpiar timestamps antiguos
    if (isset($_SESSION['form_submissions'])) {
        $_SESSION['form_submissions'] = array_filter($_SESSION['form_submissions'], function($timestamp) use ($currentTime, $limitConfig) {
            return ($currentTime - $timestamp) < $limitConfig['TIME_WINDOW'];
        });
    }

    // Comprobar si se ha excedido el límite
    if (isset($_SESSION['form_submissions']) && count($_SESSION['form_submissions']) >= $limitConfig['ATTEMPTS']) {
        http_response_code(429); // Too Many Requests
        echo json_encode(['status' => 'error', 'message' => 'Has enviado demasiados mensajes. Inténtalo de nuevo más tarde.']);
        exit;
    }
}


// Cargar PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

$phpMailerSrcPath = SITE_ROOT . '/vendor/PHPMailer/src/';

require_once $phpMailerSrcPath . 'Exception.php';
require_once $phpMailerSrcPath . 'PHPMailer.php';
require_once $phpMailerSrcPath . 'SMTP.php';

// --- Verificación de Seguridad ---

// 1. Verificar el Origen (CORS) - Mejorado para Google Analytics
$allowedOrigins = $config['SECURITY']['ALLOWED_ORIGINS'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origin) {
    if (in_array($origin, $allowedOrigins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
    } else {
        // Log the blocked origin for debugging
        error_log('Blocked origin: ' . $origin);
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Origen no permitido.']);
        exit;
    }
} else {
    // Allow requests without origin (like from Google Analytics)
    header('Access-Control-Allow-Origin: *');
}

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar petición pre-vuelo (preflight) de CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// 2. Solo permitir peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
    exit;
}

// --- Procesamiento del Formulario ---

$input = json_decode(file_get_contents('php://input'), true);

$nombre = trim($input['nombre'] ?? '');
$email = trim($input['email'] ?? '');
$telefono = trim($input['telefono'] ?? '');
$asunto = trim($input['asunto'] ?? 'Sin asunto');
$mensaje = trim($input['mensaje'] ?? '');
$honeypot = $input['website'] ?? ''; // Campo trampa para bots

// 2. Comprobación del Honeypot
if (!empty($honeypot)) {
    // Es un bot. Finge éxito pero no envíes nada.
    echo json_encode(['status' => 'success', 'message' => '¡Mensaje enviado con éxito!']);
    exit;
}


// --- Validación de Datos ---
$errors = [];
if (empty($nombre)) $errors[] = 'El nombre es obligatorio.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'El email no es válido.';
if (empty($telefono)) $errors[] = 'El teléfono es obligatorio.';
if (empty($mensaje)) $errors[] = 'El mensaje es obligatorio.';

// 3. Validación de longitud
foreach ($config['FORM']['MAX_LENGTH'] as $field => $length) {
    if (isset($input[$field]) && mb_strlen($input[$field]) > $length) {
        $errors[] = "El campo '$field' no puede tener más de $length caracteres.";
    }
}


if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Por favor corrige los errores.', 'errors' => $errors]);
    exit;
}

// --- Envío del Correo ---

$mail = new PHPMailer(true);
$smtpConfig = $config['SMTP'];

try {
    // Configuración del servidor
    // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Descomentar para depuración
    $mail->isSMTP();
    $mail->Host       = $smtpConfig['HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpConfig['USER'];
    $mail->Password   = $smtpConfig['PASS'];
    $mail->SMTPSecure = $smtpConfig['SECURE'];
    $mail->Port       = $smtpConfig['PORT'];
    $mail->CharSet    = 'UTF-8';

    // Destinatarios
    $mail->setFrom($smtpConfig['FROM_EMAIL'], $smtpConfig['FROM_NAME']);
    $mail->addAddress($smtpConfig['TO_EMAIL'], $smtpConfig['TO_NAME']);
    $mail->addReplyTo($email, $nombre);

    // Contenido del correo
    $mail->isHTML(true);
    $mail->Subject = "Nuevo mensaje de contacto: " . htmlspecialchars($asunto);
    $mail->Body    = "Has recibido un nuevo mensaje desde el formulario de tu sitio web.<br><br>" .
                     "<b>Nombre:</b> " . htmlspecialchars($nombre) . "<br>" .
                     "<b>Email:</b> " . htmlspecialchars($email) . "<br>" .
                     "<b>Teléfono:</b> " . htmlspecialchars($telefono) . "<br>" .
                     "<b>Asunto:</b> " . htmlspecialchars($asunto) . "<br>" .
                     "<b>Mensaje:</b><br>" . nl2br(htmlspecialchars($mensaje));
    $mail->AltBody = "Mensaje de: " . htmlspecialchars($nombre) . " (" . htmlspecialchars($email) . ")\n\n" . htmlspecialchars($mensaje);

    $mail->send();

    // Registrar el envío para el rate limiting
    if ($config['SECURITY']['RATE_LIMIT']['ENABLED']) {
        $_SESSION['form_submissions'][] = time();
    }

    echo json_encode(['status' => 'success', 'message' => '¡Mensaje enviado con éxito!']);

} catch (Exception $e) {
    http_response_code(500);
    // No mostrar el error detallado al usuario final en producción
    error_log('PHPMailer Error: ' . $e->getMessage()); // Guardar el error real en los logs del servidor
    echo json_encode(['status' => 'error', 'message' => 'El mensaje no se pudo enviar.']);
}
