<?php

namespace DeliciousBrains\WPMDB\Common\Transfers\Files;

use DeliciousBrains\WPMDB\Common\Util\Util as CommonUtil;

/**
 * Class Excludes
 *
 * @package WPMDB\Transfers\Excludes
 */
class Excludes {
	const ALL_STAGES = [
		'.git',
		'*.DS_Store*',
		'node_modules',
	];

	const MEDIA_FILES = [
		'*.sql',
		'*.log',
		'*backup*/',
		'*cache*/',
		'/elementor/css/',
		'/wpcf7_captcha/',
	];

	const THEME_FILES = [];

	const PLUGIN_FILES = [
		'/bluehost-wordpress-plugin/',
		'/dreamhost-panel-login.php',
		'/sg-cachepress/',
		'/sg-security/',
		'/wpengine-ssl-helper/',
		'/wp-engine-ssl-helper/',
	];

	const MUPLUGIN_FILES = [
		'/endurance-browser-cache.php',
		'/endurance-page-cache.php',
		'/endurance-php-edge.php',
		'/kinsta*',
		'/loader.php',
		'/mu-plugin.php',
		'/pagely*',
		'/pantheon*',
		'/sso.php',
		'/wpcomsh/',
		'/wpcomsh-loader.php',
		'/wpengine-security-auditor.php',
		'/wpengine-common/',
		'/wpe-wp-sign-on-plugin/',
		'/wpe-wp-sign-on-plugin.php',
		'/wpe-elasticpress-autosuggest-logger/',
		'/wpe-elasticpress-autosuggest-logger.php',
		'/wpe-cache-plugin/',
		'/wpe-cache-plugin.php',
	];

	const OTHER_FILES = [
		'*.sql',
		'*.log',
		'*backup*/',
		'backwpup-*',
		'*cache*/',
		'wflogs/',
		'updraft/',
	];

	const CORE_FILES = [];

	public $excludes;

	public function __construct() {
	}

	/**
	 * Given an array of paths, check if $filePath matches.
	 *
	 *
	 * @param string $filePath
	 * @param array  $excludes
	 * @param string $stagePath
	 *
	 * @return bool
	 */
	public static function shouldExcludeFile( $filePath, $excludes, $stagePath ) {
		$matches = [];

		// Check for manifest files, don't want those suckers,
		// Unless later explicitly included for some reason.
		if ( preg_match( "/(([a-z0-9]+-){5})manifest/", wp_basename( $filePath ) ) ) {
			$matches['exclude'][ $filePath ][] = $filePath;
		}

		if ( empty( $excludes ) || ! is_array( $excludes ) ) {
			return count( $matches ) > 0;
		}

		$filePath        = CommonUtil::slash_one_direction( $filePath );
		$relativeToStage = ! empty( $stagePath ) ? ( 0 === strpos( $filePath, $stagePath ) ) : false;
		$pathToMatch     = $relativeToStage ? str_replace( $stagePath, '', $filePath ) : $filePath;
		foreach ( $excludes as $pattern ) {
			$include = false;

			if ( empty( $pattern ) ) {
				continue;
			}

			// If pattern starts with an exclamation mark remove exclamation mark and check if pattern matches current file path
			if ( 0 === strpos( $pattern, '!' ) ) {
				$pattern = ltrim( $pattern, '!' );
				$include = true;
			}

			if ( self::pathMatches( $pathToMatch, $pattern, false, $relativeToStage ) ) {
				$type                            = $include ? 'include' : 'exclude';
				$matches[ $type ][ $filePath ][] = $pattern;
			}
		}

		// If the file should be included (based on the '!' character) none of the matched exclusion patterns matter
		if ( ! empty( $matches['include'] ) ) {
			$matches = [];
		}

		return count( $matches ) > 0;
	}

	/**
	 *
	 * Convert glob pattern to regex
	 * https://stackoverflow.com/a/13914119/130596
	 *
	 * @param      $path
	 * @param      $pattern
	 * @param bool $ignoreCase
	 * @param bool $relativeToStage
	 *
	 * @return bool
	 */
	public static function pathMatches( $path, $pattern, $ignoreCase = false, $relativeToStage = false ) {
		$expr = preg_replace_callback( '/[\\\\^$.[\\]|()?*+{}\\-\\/]/', function ( $matches ) {
			switch ( $matches[0] ) {
				case '*':
					return '.*';
				case '?':
					return '.';
				default:
					return '\\' . $matches[0];
			}
		}, $pattern );

		// Add support for matching strings starting with "/"
		if ( $relativeToStage && ( 0 === strpos( $pattern, '/' ) ) ) {
			$expr = '^' . $expr;
		}

		$expr = '/' . $expr . '/';
		if ( $ignoreCase ) {
			$expr .= 'i';
		}

		return (bool) preg_match( $expr, $path );
	}

	/**
	 * Get the excludes for each stage
	 *
	 * @param string $stage Description
	 *
	 * @return array
	 **/
	public static function get_excludes_for_stage( $stage ) {
		$base_excludes = apply_filters( 'wpmdb_all_stages_excludes', self::ALL_STAGES );

		switch ( $stage ) {
			case 'media_files':
				$stage_excludes = apply_filters( 'wpmdb_media_files_excludes', self::MEDIA_FILES );
				break;
			case 'themes':
				$stage_excludes = apply_filters( 'wpmdb_theme_files_excludes', self::THEME_FILES );
				break;
			case 'plugins':
				$stage_excludes = apply_filters( 'wpmdb_plugin_files_excludes', self::PLUGIN_FILES );
				break;
			case 'muplugins':
				$stage_excludes = apply_filters( 'wpmdb_muplugin_files_excludes', self::MUPLUGIN_FILES );
				break;
			case 'others':
				$stage_excludes = apply_filters( 'wpmdb_other_files_excludes', self::OTHER_FILES );
				break;
			case 'core':
				$stage_excludes = apply_filters( 'wpmdb_core_files_excludes', self::CORE_FILES );
				break;
		}
		// Sort base and stage excludes separately so base are always first
		sort( $base_excludes );
		sort( $stage_excludes );
		$complete_excludes = array_merge( $base_excludes, $stage_excludes );

		return $complete_excludes;
	}
}
