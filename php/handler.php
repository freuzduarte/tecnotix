<?php
// Iniciar el manejo de sesiones si se va a usar para rate limiting
session_start();

// Definir una constante para la raíz del sitio, para que config.php funcione
define('SITE_ROOT', dirname(__DIR__));

// Cargar la configuración
$config = require_once SITE_ROOT . '/config.php';

// Cargar PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

$phpMailerSrcPath = SITE_ROOT . '/vendor/PHPMailer/src/';

require_once $phpMailerSrcPath . 'Exception.php';
require_once $phpMailerSrcPath . 'PHPMailer.php';
require_once $phpMailerSrcPath . 'SMTP.php';

// --- Verificación de Seguridad ---

// 1. Verificar el Origen (CORS)
if (isset($_SERVER['HTTP_ORIGIN'])) {
    if (!in_array($_SERVER['HTTP_ORIGIN'], $config['SECURITY']['ALLOWED_ORIGINS'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Origen no permitido.']);
        exit;
    }
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
} else {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Acceso prohibido.']);
    exit;
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

// --- Validación de Datos ---
$errors = [];
if (empty($nombre)) $errors[] = 'El nombre es obligatorio.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'El email no es válido.';
if (empty($telefono)) $errors[] = 'El teléfono es obligatorio.';
if (empty($mensaje)) $errors[] = 'El mensaje es obligatorio.';

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
    echo json_encode(['status' => 'success', 'message' => '¡Mensaje enviado con éxito!']);

} catch (Exception $e) {
    http_response_code(500);
    // No mostrar el error detallado al usuario final en producción
    error_log('PHPMailer Error: ' . $e->getMessage()); // Guardar el error real en los logs del servidor
    echo json_encode(['status' => 'error', 'message' => 'El mensaje no se pudo enviar.']);
}
