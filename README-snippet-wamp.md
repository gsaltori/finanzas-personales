1) Copiar archivos al directorio del proyecto (por ejemplo: C:\wamp64\www\finanzas-personales)

2) Composer:
   - Instala Composer en Windows (si no lo tienes).
   - Desde la raíz del proyecto ejecuta: composer install

3) Permisos .htaccess:
   - En WAMP, asegúrate de que Apache permita .htaccess (AllowOverride All).
   - Reinicia Apache.

4) Configura .env:
   - Copia .env.example a .env y ajusta las credenciales MySQL (WAMP usa normalmente root sin contraseña).

5) Crear tabla users (ejemplo):
   - En phpMyAdmin crea DB 'finanzas' y ejecuta:
     CREATE TABLE users (
       id INT AUTO_INCREMENT PRIMARY KEY,
       email VARCHAR(255) UNIQUE NOT NULL,
       password VARCHAR(255) NOT NULL,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
     );

   - Para crear un usuario de prueba en PHP:
     <?php
     require 'bootstrap.php';
     require 'vendor/autoload.php';
     $db = new App\Services\DatabaseService('127.0.0.1','finanzas','root','','3306');
     $auth = new App\Services\AuthService($db);
     $auth->createUser('test@example.com','secret123');
     ?>

6) Accede a http://localhost/tu-proyecto/public/
