<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'shop_xmrclp_com' );

/** Database username */
define( 'DB_USER', 'shop_xmrclp_com' );

/** Database password */
define( 'DB_PASSWORD', 'Bm57fDWi88nCCkkL' );

/** Database hostname */
define( 'DB_HOST', '199.195.252.251' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

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
define( 'AUTH_KEY',         'EM/lI m]tkd:v1Ql5DrdfEbBcq7zWFmI9qd59NlNB=~1lDb^OviAe*@d|#nE!B`$' );
define( 'SECURE_AUTH_KEY',  'kR1xnKeO1iT9[wMgGR};8(B2=RX)|nmSNw~kDO%@!*M+0f9$41=.,PMcc~d.+R:R' );
define( 'LOGGED_IN_KEY',    'iIRt?vo,r!^8;*B&G||ydST7Hj%x~u_E3J&7Gts,.ocS~AuH<d6|baEYu5Zj^*l$' );
define( 'NONCE_KEY',        'b~(MtRWB+fvERO1{rE;|&#U~yQatv5m8G;Jqzd.Dq0|t7hiTuA(?P!3aJzv&WS!.' );
define( 'AUTH_SALT',        '6ZFUX*n?gj<*(ho#?S6Ol!c)9WR&$dK6on}*jeH6w3hr^)#N[b^(r>N%bf9f7*{{' );
define( 'SECURE_AUTH_SALT', '&qGH&O{),~U&GXYMa94Q=U{#sKdN(2P4;W;S,k+BpxbaBRpO[DQIfM9L/b5Y(C=^' );
define( 'LOGGED_IN_SALT',   '2X_J&RVLUbMD^,6-;yt&<E+]J!5m*_ bSW65}<*./dYCc[prq7n&x6Z++Me3[~{?' );
define( 'NONCE_SALT',       't9%wQ1t_Bi.p87l(J;,S]8{eL<@.3:w++*@I8=w ,$_t4l~*|-w|<.(Q6B~(ruir' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

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
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
