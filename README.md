# OsTicket - Sistema de Incidencias GrupoATU
Sistema de gestión de incidencias y valoraciones basado en osTicket, personalizado y adaptado para GrupoATU.

URL
https://incidencias.grupoatu.com

Requisitos
PHP 8.0+
MySQL 5.5+
Extensiones PHP: mysqli, ZipArchive
Apache/Nginx
Funcionalidades desarrolladas
Importación masiva desde Excel/CSV/ODS (importar_excel.php)
Gestión de valoraciones (valoraciones.php, coordinadores.php)
Estadísticas (estadisticas_coordinadores.php, estadisticas.php)
Panel Gestor personalizado (Gestor.php, gestor_valoraciones.php, gestor_incidencias.php, gestor_coordinadores.php)
Gestión de incidencias (incidencias.php)
Sistema de archivos adjuntos (archivo.php, ver_archivo.php)
SSO para administradores (auth_admin_sso.php)
Branding personalizado con logo GrupoATU
Instalación
Clonar el repositorio en el servidor
Configurar base de datos en include/ost-config.php
Importar el SQL de la base de datos
Ajustar credenciales (ver credenciales.txt)
