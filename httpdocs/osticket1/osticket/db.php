<?php
/**
 * =============================================================================
 * db.php — Proxy de conexión a base de datos del panel gestor (custom/admin)
 * =============================================================================
 *
 * Propósito:
 *   Punto de entrada único para la conexión a BD en el panel de gestión
 *   personalizado de Grupo ATU (Gestor.php, fields.php, options.php, etc.).
 *   Actúa como proxy que delega en el archivo de conexión real ubicado en
 *   custom/admin/db.php, donde se definen las credenciales y la variable $mysqli.
 *
 * Seguridad:
 *   - Detecta si el archivo se está ejecutando directamente (no incluido) y
 *     devuelve HTTP 403 + "Acceso denegado". Esto evita exponer información de
 *     conexión si el archivo es accedido desde el navegador por error.
 *
 * Funcionamiento:
 *   1. Verifica que no sea una ejecución directa (SCRIPT_FILENAME vs __FILE__).
 *   2. Construye la ruta al archivo real: __DIR__ . '/custom/admin/db.php'.
 *   3. Verifica que el archivo exista y sea legible.
 *   4. Si no → responde HTTP 500 con mensaje de error en texto plano.
 *   5. Si sí → lo carga con require_once (que definirá $mysqli y abrirá
 *      la conexión a la BD).
 *
 * Variable disponible tras la inclusión:
 *   $mysqli  — Conexión activa a MySQL (objeto mysqli configurado en custom/admin/db.php)
 *
 * Dependencias:
 *   - custom/admin/db.php (credenciales y apertura de conexión)
 *
 * @package    GrupoATU\Gestor
 * @author     Equipo de desarrollo Grupo ATU
 * @version    1.0
 * =============================================================================
 */

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit('Acceso denegado');
}

$realDb = __DIR__ . '/custom/admin/db.php';

if (!is_file($realDb) || !is_readable($realDb)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("ERROR: No se encuentra el archivo de conexión: $realDb");
}

require_once $realDb;