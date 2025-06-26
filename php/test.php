<?php
// Mostrar información del servidor
echo "<h1>¡PHP está funcionando correctamente!</h1>";
echo "<h2>Información del servidor:</h2>";

// Mostrar información de PHP
echo "<h3>Versión de PHP: " . phpversion() . "</h3>";

// Probar la conexión a la base de datos (opcional)
echo "<h3>Extensiones cargadas:</h3>";
echo "<pre>" . print_r(get_loaded_extensions(), true) . "</pre>";

// Probar si puedes escribir en el servidor
$test_file = 'test_write.txt';
if (file_put_contents($test_file, 'Prueba de escritura ' . date('Y-m-d H:i:s'))) {
    echo "<p style='color:green;'>✓ El servidor permite escritura de archivos</p>";
    // Opcional: eliminar el archivo de prueba
    unlink($test_file);
} else {
    echo "<p style='color:red;'>✗ No se pudo escribir en el servidor</p>";
}

// Probar si PHPMailer está correctamente instalado
echo "<h3>Probando PHPMailer:</h3>";
if (file_exists('vendor/PHPMailer/src/PHPMailer.php')) {
    echo "<p style='color:green;'>✓ PHPMailer está correctamente instalado</p>";
    
    // Intentar cargar PHPMailer
    require 'vendor/PHPMailer/src/Exception.php';
    require 'vendor/PHPMailer/src/PHPMailer.php';
    require 'vendor/PHPMailer/src/SMTP.php';
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        echo "<p style='color:green;'>✓ PHPMailer se cargó correctamente</p>";
        
        // Probar configuración SMTP
        $mail->isSMTP();
        $mail->Host = 'localhost';
        $mail->SMTPAutoTLS = false;
        $mail->SMTPSecure = false;
        $mail->SMTPAuth = false;
        $mail->Port = 25;
        
        // Deshabilitar verificación de certificado SSL (solo para pruebas)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        
        echo "<p style='color:green;'>✓ Configuración SMTP básica probada</p>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>✗ Error al cargar PHPMailer: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:red;'>✗ No se encontró PHPMailer en la ruta esperada</p>";
}

// Mostrar información de errores
echo "<h3>Configuración de errores:</h3>";
echo "<pre>";
echo "display_errors: " . ini_get('display_errors') . "\n";
echo "error_reporting: " . ini_get('error_reporting') . "\n";
echo "</pre>";

// Mostrar información del servidor
echo "<h3>Variables de servidor:</h3>";
echo "<pre>";
echo "SERVER_SOFTWARE: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'No disponible') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'No disponible') . "\n";
echo "</pre>";
?>