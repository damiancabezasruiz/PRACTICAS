<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wp_t7q6d' );

/** Database username */
define( 'DB_USER', 'wp_easmk' );

/** Database password */
define( 'DB_PASSWORD', 'uh67P?Pz18*f3zEt' );

/** Database hostname */
define( 'DB_HOST', 'localhost:3306' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY', 'N6Sbi7_wHQC85GtYt;C|xC7++-&z6f3yCH3IB[;]S17j-IM-[@mcGSyw4JQmrLkX');
define('SECURE_AUTH_KEY', 'g6PI7yY5O20P1kptP~5HS_~W82DHJf1Ms*7UAM~[t4%J;BO4f(k@9EeCGHh/(C7U');
define('LOGGED_IN_KEY', 'Y59zINdwP@iU-s;Zf6SOp~)8aN90DU-3E7Qiv8[1+t[I)28v3zH1k!78YLTbLqsx');
define('NONCE_KEY', '0~#:u-C52Y46/7rc5C6:q9h+(8RWLsZ_p4_t;rY6K1121n-jd#38J3Bt;ndG+[7f');
define('AUTH_SALT', 'Z0~zzRa2~&HnEF1lPO:m+2kEg%-6do]bXvz#n809%Ot_!&559mxOW]]1c~v4J55;');
define('SECURE_AUTH_SALT', 'd6JZ5R|qXt/JBouRS17~|[b8k6!CJxA5uHo#G4)i9(1&A_i6Y%XLn3MTo1vuS20r');
define('LOGGED_IN_SALT', '56:4aWOrV:1;8V!Qx!Rh8ds-#5;AV07*mNB7[@%Q!9!N4R8_749sqy!O9m6Iqx|*');
define('NONCE_SALT', 'nyO9Cz7QUi_1;)!I67;C:8(8u8~80&[@z(o%c*022Aj5nQNx5+7dG]8Mc(wj(;Ja');


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'qogwiOTFO_';


/* Add any custom values between this line and the "stop editing" line. */

define('WP_ALLOW_MULTISITE', true);
/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
