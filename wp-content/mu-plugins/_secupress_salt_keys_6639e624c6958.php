<?php
/**
 * Plugin Name: SecuPress Salt Keys
 * Description: Good Security Keys for each of your blogs of your network (multisite only), auto-reseting each month.
 * Version: 2.0
 * License: GPLv2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Copyright 2012-2021 SecuPress
 */

defined( 'ABSPATH' ) or die( 'Something went wrong.' );

if ( ! get_site_option( 'secupress_active_submodule_wp-config-constant-saltkeys' ) ) {
	return;
}

define( 'SECUPRESS_SALT_KEYS_MODULE_ACTIVE', true );

global $blog_id;

$hash_1     = 'gk68ziuDkKLB!z)FF[bQIvGpHg|,Cb,v9e[s<gNCYvF7`EpUe&xkyQb41?ARHy3h';
$hash_2     = 'K=QMfz]}2s?C!i>K$O/50`{t{,?C0T0Mu5@kC/RuM$Y<lw8B2vUqg!b/uA~hi<Cx';
$file_str   = __FILE__;
$sp_setup   = get_option( 'secupress_settings' );
$hash_key   = isset( $sp_setup['hash_key'] ) ? $sp_setup['hash_key'] : md5( __FILE__ );
$hash_1    .= $hash_2;
$file_str  .= $hash_2;
$main_keys  = [ 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ];

foreach ( $main_keys as $main_key ) {
	if( ! defined( $main_key ) ) {
		define( $main_key, sha1( 'secupress' . $hash_key . $main_key . md5( $main_key . $file_str ) ) . md5( $hash_key . $main_key . $file_str ) );
	}
}

unset( $file_str, $main_key, $main_keys, $hash_1, $hash_2, $hash_key, $sp_setup );
