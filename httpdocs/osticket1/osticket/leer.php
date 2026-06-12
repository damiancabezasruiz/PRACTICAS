<?php
/**
 * =============================================================================
 * leer.php — Inicialización / Migración de estructura de base de datos
 * =============================================================================
 *
 * Propósito:
 *   Herramienta de mantenimiento que asegura la existencia de todas las columnas
 *   personalizadas en la tabla ost_ticket__cdata de osTicket. Se ejecuta de forma
 *   manual desde el navegador cuando se despliega una nueva versión o se necesita
 *   añadir campos al esquema sin perder datos existentes.
 *
 * Funcionamiento:
 *   1. Conecta a la BD usando las credenciales definidas en ost-config.php.
 *   2. Consulta las columnas actuales de ost_ticket__cdata.
 *   3. Por cada columna del catálogo definido en $columnas:
 *      - Si ya existe → la informa como existente (no toca nada).
 *      - Si falta     → la crea con ALTER TABLE ADD COLUMN.
 *   4. Al finalizar, ofrece un enlace a la siguiente fase del proceso.
 *
 * Uso:
 *   Acceder directamente desde el navegador: https://dominio/leer.php
 *   Solo debe ejecutarse por administradores del sistema.
 *
 * Dependencias:
 *   - PHP 7.4+
 *   - Extensión mysqli
 *   - include/ost-config.php (define DBHOST, DBUSER, DBPASS, DBNAME)
 *
 * Seguridad:
 *   ⚠ Este archivo NO debe ser accesible públicamente en producción.
 *   Debe eliminarse o protegerse con autenticación tras su uso.
 *
 * @package    GrupoATU\Incidencias
 * @author     Equipo de desarrollo Grupo ATU
 * @version    1.0
 * =============================================================================
 */

// Mostrar todos los errores — útil durante la ejecución manual en desarrollo/mantenimiento
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Forzar salida HTML en UTF-8 para evitar problemas con acentos y caracteres especiales
header('Content-Type: text/html; charset=utf-8');

echo "<h1> Crear Estructura de BD</h1>";

// -----------------------------------------------------------------------------
// CONEXIÓN A BASE DE DATOS
// Se carga la configuración de osTicket que define las constantes de conexión:
// DBHOST, DBUSER, DBPASS, DBNAME
// -----------------------------------------------------------------------------
define('INCLUDE_DIR', '/home/puntaca1/public_html/osticket/include/');
require_once INCLUDE_DIR . 'ost-config.php';

$db = new mysqli(DBHOST, DBUSER, DBPASS, DBNAME);

// utf8mb4 soporta el rango completo Unicode, incluidos emojis y caracteres especiales
$db->set_charset('utf8mb4');

// -----------------------------------------------------------------------------
// CATÁLOGO DE COLUMNAS REQUERIDAS
// Formato: 'nombre_columna' => 'tipo_sql'
// Estas son las columnas personalizadas que extienden los datos de cada ticket
// en la tabla ost_ticket__cdata, más allá del esquema base de osTicket.
// -----------------------------------------------------------------------------
$columnas = [
    // Identificación del alumno
    'subject'               => 'VARCHAR(255)',  // Asunto / número de expediente
    'nombreAlu'             => 'VARCHAR(255)',  // Nombre del alumno
    'apellidosAlu'          => 'VARCHAR(255)',  // Apellidos del alumno

    // Datos del plan formativo y adscripción territorial
    'plan'                  => 'VARCHAR(255)',  // Plan de formación (LABORA, Asturias, etc.)
    'sector_labora'         => 'VARCHAR(255)',  // Sector específico del plan LABORA
    'sector_cyl'            => 'VARCHAR(255)',  // Sector específico de Castilla y León
    'sector_asturias'       => 'VARCHAR(255)',  // Sector específico de Asturias
    'sector_estatal'        => 'VARCHAR(255)',  // Sector específico del plan Estatal

    // Datos de la acción formativa
    'tutor'                 => 'VARCHAR(255)',  // Tutor o formador interno responsable
    'empresa'               => 'VARCHAR(255)',  // Empresa del alumno
    'accion'                => 'VARCHAR(255)',  // Código o nombre de la acción formativa
    'grupo'                 => 'VARCHAR(64)',   // Grupo dentro de la acción
    'curso'                 => 'VARCHAR(255)',  // Nombre del curso

    // Datos de la incidencia
    'incidencia'            => 'TEXT',          // Tipo de incidencia (valor de la lista)
    'motivo'                => 'TEXT',          // Motivo libre de la incidencia
    'fotos'                 => 'TEXT',          // Referencias a adjuntos / fotos (IDs o URLs)
    'medidas'               => 'TEXT',          // Medidas adoptadas

    // Resolución manual (gestionada fuera de osTicket)
    'solucion_manual'       => 'TEXT',          // Descripción de la solución aplicada
    'fecha_solucion_manual' => 'DATETIME NULL', // Fecha en que se resolvió manualmente

    // Estado del ticket en texto plano (complementa el estado nativo de osTicket)
    'estado_texto'          => 'VARCHAR(64)',
];

// -----------------------------------------------------------------------------
// LEER COLUMNAS EXISTENTES EN LA TABLA
// Se normalizan a minúsculas para comparaciones case-insensitive
// -----------------------------------------------------------------------------
$existentes = [];
$r = $db->query("SHOW COLUMNS FROM ost_ticket__cdata");

while ($row = $r->fetch_assoc()) {
    $existentes[] = strtolower($row['Field']);
}

// -----------------------------------------------------------------------------
// VERIFICAR Y CREAR COLUMNAS FALTANTES
// Por cada columna del catálogo se comprueba si ya existe; si no, se crea.
// Se usa try/catch para capturar errores de permisos o sintaxis sin detener
// la ejecución de las demás columnas.
// -----------------------------------------------------------------------------
echo "<ul>";

foreach ($columnas as $col => $tipo) {
    if (in_array(strtolower($col), $existentes)) {
        // Columna ya presente → no se modifica
        echo "<li> $col existe</li>";
    } else {
        // Columna ausente → crearla
        try {
            $db->query("ALTER TABLE ost_ticket__cdata ADD COLUMN `$col` $tipo");
            echo "<li> $col CREADA</li>";
        } catch (Exception $e) {
            // Error al crear (permisos insuficientes, tipo incorrecto, etc.)
            echo "<li> $col: " . $e->getMessage() . "</li>";
        }
    }
}

echo "</ul>";

// Enlace a la siguiente fase del proceso de instalación / mantenimiento
echo "<p> <a href='limpiar_importados.php'>Siguiente: Limpiar tickets</a></p>";

$db->close();