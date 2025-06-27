<?php
// Habilitar reporte de errores para desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Cargar configuración
$config = require_once '/home2/alfredou/public_html/config.php';

// Obtener el contenido JSON del cuerpo de la solicitud
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Si no se pudo decodificar el JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'message' => 'Datos inválidos'
    ]));
}

// Traducción de campos del config a los del formulario
$field_map = [
    'nombre' => 'name',
    'email' => 'email',
    'telefono' => 'phone',
    'asunto' => 'subject',
    'mensaje' => 'message'
];

// Validar campos requeridos usando la configuración
foreach ($config['FORM']['REQUIRED_FIELDS'] as $field) {
    $formField = $field_map[$field] ?? $field;
    if (empty($data[$formField])) {
        http_response_code(400);
        die(json_encode([
            'success' => false,
            'message' => "El campo $field es requerido"
        ]));
    }
}

// Validar formato de email
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'message' => 'El formato del correo electrónico no es válido'
    ]));
}

// Validar longitud de campos
foreach ($config['FORM']['MAX_LENGTH'] as $field => $maxLength) {
    $formField = $field_map[$field] ?? $field;
    if (!empty($data[$formField]) && strlen($data[$formField]) > $maxLength) {
        http_response_code(400);
        die(json_encode([
            'success' => false,
            'message' => "El campo $field no puede exceder $maxLength caracteres"
        ]));
    }
}

// Cargar PHPMailer usando rutas absolutas correctas
require_once '/home2/alfredou/public_html/vendor/PHPMailer/src/Exception.php';
require_once '/home2/alfredou/public_html/vendor/PHPMailer/src/PHPMailer.php';
require_once '/home2/alfredou/public_html/vendor/PHPMailer/src/SMTP.php';

$mail = new \PHPMailer\PHPMailer\PHPMailer(true);

try {
    // Configuración del servidor usando config.php
    $mail->isSMTP();
    $mail->Host = $config['SMTP']['HOST'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['SMTP']['USER'];
    $mail->Password = $config['SMTP']['PASS'];
    $mail->SMTPSecure = $config['SMTP']['SECURE'] === 'ssl' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $config['SMTP']['PORT'];
    $mail->CharSet = 'UTF-8';
    
    // Configuración del correo usando config.php
    $mail->setFrom($config['SMTP']['FROM_EMAIL'], $config['SMTP']['FROM_NAME']);
    $mail->addAddress($config['SMTP']['TO_EMAIL'], $config['SMTP']['TO_NAME']);
    $mail->addReplyTo($data['email'], $data['name']);
    
    // Contenido
    $mail->isHTML(true);
    $mail->Subject = 'Nuevo mensaje: ' . ($data['subject'] ?? 'Sin asunto');
    
    $mail->Body = "
        <h2>Nuevo mensaje de contacto</h2>
        <p><strong>Nombre:</strong> {$data['name']}</p>
        <p><strong>Email:</strong> {$data['email']}</p>
        " . (!empty($data['phone']) ? "<p><strong>Teléfono:</strong> {$data['phone']}</p>" : "") . "
        " . (!empty($data['service']) ? "<p><strong>Servicio de Interés:</strong> {$data['service']}</p>" : "") . "
        <p><strong>Asunto:</strong> " . ($data['subject'] ?? 'No especificado') . "</p>
        <h3>Mensaje:</h3>
        <p>" . nl2br(htmlspecialchars($data['message'])) . "</p>
    ";
    
    $mail->AltBody = "Nuevo mensaje de contacto\n\n" .
        "Nombre: {$data['name']}\n" .
        "Email: {$data['email']}\n" .
        (!empty($data['phone']) ? "Teléfono: {$data['phone']}\n" : "") .
        (!empty($data['service']) ? "Servicio de Interés: {$data['service']}\n" : "") .
        "Asunto: " . ($data['subject'] ?? 'No especificado') . "\n\n" .
        "Mensaje:\n" . $data['message'];

    // Enviar correo
    $mail->send();
    
    // Respuesta de éxito
    echo json_encode([
        'success' => true,
        'message' => 'Mensaje enviado correctamente'
    ]);

} catch (\PHPMailer\PHPMailer\Exception $e) {
    // Log del error
    error_log('Error al enviar el correo: ' . $e->getMessage());
    
    // Respuesta de error
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error al enviar el mensaje. Por favor, inténtalo de nuevo más tarde.'
    ]);
}