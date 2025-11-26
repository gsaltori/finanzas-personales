# Finanzas Personales - Sistema Mejorado

Sistema de gestión de finanzas personales con arquitectura MVC, importación de extractos bancarios, presupuestos y metas de ahorro.

## 🔒 Características de Seguridad

- ✅ CSRF protection en todos los formularios
- ✅ Rate limiting en login e imports
- ✅ Password hashing con bcrypt
- ✅ Sesiones seguras (httponly, samesite, secure)
- ✅ Prepared statements (prevención SQL injection)
- ✅ Validación de archivos subidos
- ✅ Audit trail completo
- ✅ Separación dev/producción

## 📋 Requisitos

- PHP 8.0 o superior
- MySQL 8.0 o superior
- Apache/Nginx con mod_rewrite
- Extensiones PHP: PDO, pdo_mysql, mbstring, dom, libxml
- (Opcional) Poppler-utils para conversión PDF

## 🚀 Instalación

### 1. Clonar el repositorio

```bash
git clone [URL_DEL_REPO]
cd finanzas-personales
```

### 2. Configurar variables de entorno

**IMPORTANTE**: NUNCA versionar el archivo `.env` con credenciales reales.

```bash
cp .env.example .env
```

Editar `.env` con tus credenciales:

```env
APP_ENV=production
APP_DEBUG=false
APP_BASE_URL=/finanzas-personales/public

DB_HOST=127.0.0.1
DB_DATABASE=finanzas
DB_USERNAME=finanzas_user
DB_PASSWORD=TU_PASSWORD_SEGURA_AQUI

SESSION_SECURE=true
SESSION_HTTPONLY=true
SESSION_SAMESITE=Strict
```

**Para desarrollo local**, puedes usar:

```env
APP_ENV=development
APP_DEBUG=true
SESSION_SECURE=false
```

### 3. Crear base de datos

```bash
mysql -u root -p
```

```sql
CREATE DATABASE finanzas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'finanzas_user'@'localhost' IDENTIFIED BY 'password_seguro_aqui';
GRANT ALL PRIVILEGES ON finanzas.* TO 'finanzas_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

```bash
mysql -u finanzas_user -p finanzas < sql/schema.sql
mysql -u finanzas_user -p finanzas < sql/import_and_audit.sql
mysql -u finanzas_user -p finanzas < sql/budgets_goals.sql
```

### 4. Configurar permisos

```bash
# Linux/macOS
chmod 755 public
chmod -R 775 storage
chmod -R 775 storage/logs

# Asegurar que el usuario del web server sea dueño
sudo chown -R www-data:www-data storage
```

### 5. Configurar Apache/Nginx

#### Apache (Virtual Host)

```apache
<VirtualHost *:80>
    ServerName finanzas.local
    DocumentRoot /var/www/finanzas-personales/public
    
    <Directory /var/www/finanzas-personales/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/finanzas_error.log
    CustomLog ${APACHE_LOG_DIR}/finanzas_access.log combined
</VirtualHost>
```

#### Nginx

```nginx
server {
    listen 80;
    server_name finanzas.local;
    root /var/www/finanzas-personales/public;
    
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 6. (Opcional) Instalar Poppler para importación PDF

#### Linux (Debian/Ubuntu)

```bash
sudo apt update
sudo apt install -y poppler-utils
which pdftohtml  # Verificar instalación
```

#### macOS

```bash
brew install poppler
```

#### Windows

1. Descargar de https://github.com/oschwaldp/poppler-windows/releases
2. Extraer en `C:\poppler`
3. Agregar `C:\poppler\bin` al PATH del sistema
4. Reiniciar el servidor web

Si no puedes instalar Poppler en el servidor, puedes:
- Convertir PDFs a HTML localmente y pegar el HTML
- O usar `public/pdf_conversion_diagnostic.php` para troubleshooting

## 🔐 Seguridad en Producción

### Checklist antes de producción:

- [ ] Cambiar `APP_DEBUG=false` en `.env`
- [ ] Establecer `SESSION_SECURE=true` (requiere HTTPS)
- [ ] Usar contraseña fuerte para DB
- [ ] Agregar `.env` al `.gitignore`
- [ ] Configurar HTTPS/SSL
- [ ] Revisar permisos de archivos (644 para archivos, 755 para directorios)
- [ ] Deshabilitar listado de directorios en web server
- [ ] Configurar backups automáticos de DB
- [ ] Revisar logs regularmente en `storage/logs/`

### Proteger archivo .env

Agregar a `.gitignore`:

```
.env
storage/logs/*.log
```

### HTTPS con Let's Encrypt (Linux)

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d finanzas.tudominio.com
```

## 📊 Uso del Sistema

### Crear primer usuario

1. Acceder a `http://finanzas.local/?r=auth/register`
2. Registrar cuenta con email y contraseña fuerte
3. Iniciar sesión

### Importar extracto bancario

1. **Opción A**: Subir PDF directamente (requiere Poppler instalado)
2. **Opción B**: Convertir PDF a HTML localmente y pegar contenido

```bash
# Convertir PDF a HTML localmente
pdftohtml -stdout -nodrm archivo.pdf > extracto.html
```

3. Ir a "Importar extracto"
4. Pegar HTML o subir archivo
5. Revisar preview
6. Seleccionar transacciones a importar
7. Confirmar importación

### Ver diagnóstico de conversión PDF

Acceder a: `http://finanzas.local/pdf_conversion_diagnostic.php`

## 🛠️ Desarrollo

### Estructura del proyecto

```
finanzas-personales/
├── app/
│   ├── Controllers/      # Lógica de controladores
│   ├── Models/           # Modelos de datos
│   ├── Helpers/          # Utilidades (RateLimiter, Parser, etc)
│   └── Views/            # Vistas (HTML/PHP)
├── config/
│   ├── config.php        # Configuración (lee .env)
│   └── database.php      # Conexión PDO
├── public/               # Document root
│   ├── index.php         # Entry point
│   ├── assets/           # CSS, JS, imágenes
│   └── .htaccess         # Rewrite rules
├── sql/                  # Scripts SQL
├── storage/
│   └── logs/             # Logs de errores
├── .env.example          # Template de configuración
└── README.md
```

### Agregar nueva funcionalidad

1. Crear modelo en `app/Models/`
2. Crear controlador en `app/Controllers/`
3. Crear vistas en `app/Views/`
4. Acceder vía `?r=controller/action`

### Testing

```bash
# Diagnóstico de PDF
php public/pdf_conversion_diagnostic.php /ruta/al/test.pdf

# Parser de HTML
php public/pdf_debug_run.php?path=/ruta/al/converted.html
```

## 📈 Arquitectura

### Mejoras implementadas

1. **Configuración segura**: Variables de entorno con `.env`
2. **Rate limiting**: Protección contra fuerza bruta
3. **Sesiones seguras**: httponly, secure, samesite
4. **Parser refactorizado**: Estrategias separadas (Strategy Pattern)
5. **Validación de shell commands**: Escapado seguro de comandos
6. **Páginas de error personalizadas**: 404 y 500
7. **Audit logging mejorado**: Tracking completo de cambios

### Patrón Strategy para Parser

El sistema usa 3 estrategias de parsing en orden:

1. **TableWithHeadersStrategy**: Tablas HTML con encabezados
2. **VerticalBlocksStrategy**: Bloques verticales (pdftohtml -stdout)
3. **PlainTextStrategy**: Texto plano línea por línea

Agregar nueva estrategia:

```php
class MyCustomStrategy implements BankStatementParserStrategy {
    use ParserHelpers;
    
    public function getName(): string {
        return "Mi estrategia";
    }
    
    public function canHandle(string $html): bool {
        // Lógica de detección
    }
    
    public function parse(string $html): array {
        // Lógica de parsing
    }
}

// Registrar en BankStatementParser constructor
$this->addStrategy(new MyCustomStrategy());
```

## 🐛 Troubleshooting

### Error: "exec() no disponible"

Editar `php.ini`:

```ini
disable_functions = 
```

Remover `proc_open` de la lista, reiniciar servidor web.

### Error: "Poppler no encontrado"

Ver sección de instalación de Poppler arriba, o usar conversión local.

### Error: "Rate limit exceeded"

Esperar el tiempo indicado o limpiar sesión:

```bash
# Borrar sesiones en el servidor
rm /var/lib/php/sessions/*
```

### Error: "Permission denied" en storage/

```bash
sudo chown -R www-data:www-data storage/
chmod -R 775 storage/
```

## 📝 Licencia

[Tu licencia aquí]

## 👥 Contribuir

[Instrucciones de contribución]

## 📧 Soporte

[Información de contacto]