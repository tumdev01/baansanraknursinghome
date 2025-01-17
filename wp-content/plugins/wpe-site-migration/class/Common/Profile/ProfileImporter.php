<?php

namespace DeliciousBrains\WPMDB\Common\Profile;

use DeliciousBrains\WPMDB\Common\Sql\Table;
use DeliciousBrains\WPMDB\Common\Util\Util;

class ProfileImporter {
	/**
	 * @var Table
	 */
	private $table;

	/**
	 * @var array
	 */
	private $valid_post_types;

	/**
	 * ProfileImporter constructor.
	 *
	 * @param Table $table
	 */
	public function __construct( Table $table ) {
		$this->table = $table;
	}

	/**
	 * Starts the profile import.
	 *
	 * @param numeric $schema_version
	 */
	public function setProfileDefaults( $schema_version ) {
		if ( $schema_version >= 3.1 ) {
			return;
		}

		$new_opts = [
			WPMDB_SAVED_PROFILES_OPTION,
			WPMDB_RECENT_MIGRATIONS_OPTION,
			WPMDB_MIGRATION_OPTIONS_OPTION,
			WPMDB_MIGRATION_STATE_OPTION,
			WPMDB_REMOTE_RESPONSE_OPTION,
			WPMDB_REMOTE_MIGRATION_STATE_OPTION,
		];

		foreach ( $new_opts as $opt ) {
			$saved_opt = get_site_option( $opt );
			if ( empty( $saved_opt ) ) {
				update_site_option( $opt, '' );
			}
		}

		$new_saved_profiles = get_site_option( WPMDB_SAVED_PROFILES_OPTION ); //New profiles
		$wpmdb_settings     = get_site_option( WPMDB_SETTINGS_OPTION );
		$home               = preg_replace( '/^https?:/', '', Util::home_url() );
		$path               = esc_html( addslashes( Util::get_absolute_root_file_path() ) );

		$new_saved_profiles = $this->importOldProfiles( $new_saved_profiles, $wpmdb_settings, $home, $path );

		if ( ! empty( $new_saved_profiles ) ) {
			update_site_option( WPMDB_SAVED_PROFILES_OPTION, $new_saved_profiles );
		}

		flush_rewrite_rules();
	}

	/**
	 * Upgrade old profiles to latest format.
	 *
	 * @param array  $new_saved_profiles
	 * @param array  $wpmdb_settings
	 * @param string $home
	 * @param string $path
	 *
	 * @return array
	 */
	public function importOldProfiles( $new_saved_profiles, $wpmdb_settings, $home, $path ) {
		$old_profiles = isset( $wpmdb_settings['profiles'] ) ? $wpmdb_settings['profiles'] : false;

		if ( empty( $old_profiles ) ) {
			return [];
		}

		foreach ( $old_profiles as $old_key => $profile ) {
			$profile = $this->profileFormat( $profile, $home, $path );

			if ( empty( $profile ) ) {
				return [];
			}

			if ( empty( $new_saved_profiles ) ) {
				$new_saved_profiles = [];
				// Set index to start at 1
				array_unshift( $new_saved_profiles, "" );
				unset( $new_saved_profiles[0] );
			}

			$new_saved_profiles[] = [
				'name'     => $profile['current_migration']['profile_name'],
				'value'    => json_encode( $profile ),
				'guid'     => Util::uuidv4(),
				'date'     => time(),
				'imported' => true,
				'old_id'   => $old_key,
			];
		}

		// Unset the old profiles, so we don't redo all this next schema upgrade.
		unset( $wpmdb_settings['profiles'] );
		update_site_option( WPMDB_SETTINGS_OPTION, $wpmdb_settings );

		return $new_saved_profiles;
	}

	/**
	 * Append allowed extra options to profile.
	 *
	 * @param array $profile
	 * @param array $current_migration_details
	 *
	 * @return array|bool
	 */
	public function appendExtraProfileOpts( $profile, $current_migration_details ) {
		if ( empty( $profile ) ) {
			return false;
		}

		$allowedExtraOpts = [
			'mst_select_subsite',
			'mst_selected_subsite',
			'mst_destination_subsite',
			'new_prefix',
			'media_files',
			'migrate_themes',
			'select_themes',
			'migrate_plugins',
			'select_plugins',
			'file_ignores',
			'mf_select_subsites',
			'mf_selected_subsites',
		];

		foreach ( $profile as $profile_key => $value ) {
			if ( in_array( $profile_key, $allowedExtraOpts ) ) {
				$current_migration_details[ $profile_key ] = $value;
			}
		}

		return $current_migration_details;
	}

	/**
	 * Ensure format of profile fields are correct.
	 *
	 * @param array  $profile
	 * @param string $home
	 * @param string $path
	 *
	 * @return array
	 */
	public function profileFormat( $profile, $home, $path ) {
		if ( empty( $profile ) ) {
			return [];
		}

		$profileObj = (object) $profile;

		if ( ! is_object( $profileObj ) ) {
			return [];
		}

		$intent = isset( $profileObj->action ) ? $profileObj->action : '';

		list( $connection_key, $url, $connection_info ) = $this->computeConnectionInfo( $profileObj );
		$advanced_options = $this->computeAdvancedOptions( $profileObj, $intent );

		if ( empty( $this->valid_post_types ) ) {
			$this->valid_post_types = $this->table->get_post_types();
		}

		$selected_post_types = ! empty( $profileObj->select_post_types ) ? $profileObj->select_post_types : [];

		/**
		 * In 1.9.x, post types were excluded, but in 2.0+ they are included.
		 *
		 * We don't have enough information for imports and pulls to fix this,
		 * so we leave the selected post types empty.
		 *
		 * For other migrations, we know what post types there could be so we fix that here.
		 */
		$post_types = [];
		if ( ! empty( $selected_post_types ) && ! in_array( $intent, [ 'import', 'pull' ] ) ) {
			$post_types = array_values( array_diff( $this->valid_post_types, $selected_post_types ) );
		}

		$custom_search_replace = $this->composeFindAndReplace(
			$home,
			$path,
			$profileObj,
			$intent
		);

		$current_migration_details = $this->composeCurrentMigrationDetails(
			$profile,
			$intent,
			$profileObj,
			$post_types,
			$advanced_options
		);

		$current_migration_details['cli_exclude_post_types'] = ! empty( $selected_post_types ) ? $selected_post_types : [];

		$formattedProfile = [
			'current_migration' => $current_migration_details,
			'connection_info'   => [
				'connection_state' =>
					[
						'value' => $connection_info,
						'url'   => $url,
						'key'   => $connection_key,
					],
			],
			'search_replace'    => [
				'custom_search_replace'    => $custom_search_replace ?: [],
				'standard_search_visible'  => in_array( $intent, [ 'pull', 'push', 'import' ] ),
				'standard_options_enabled' => in_array( $intent, [ 'pull', 'push', 'import' ] ) ? [
					'domain',
					'path',
				] : [],
			],
		];

		// Addons.
		$formattedProfile['media_files']        = $this->computeMediaFilesDetails( $profileObj );
		$formattedProfile['theme_plugin_files'] = $this->computeThemePluginDetails( $profileObj );
		$formattedProfile['multisite_tools']    = $this->computeMultisiteToolsDetails( $profileObj );

		return $formattedProfile;
	}

	/**
	 * Checks for legacy MST options.
	 *
	 * @param object $profile
	 *
	 * @return array
	 */
	public function computeMultisiteToolsDetails( $profile ) {
		// We might already be using the new format.
		if ( isset( $profile->multisite_tools ) ) {
			return $profile->multisite_tools;
		}

		// Set up some defaults.
		$output = [
			'enabled'          => false,
			'selected_subsite' => 0,
		];

		if (
			property_exists( $profile, 'mst_select_subsite' ) &&
			$profile->mst_select_subsite === true &&
			isset( $profile->mst_selected_subsite )
		) {
			$output['enabled']          = true;
			$output['selected_subsite'] = (int) $profile->mst_selected_subsite;

			if ( isset( $profile->mst_destination_subsite ) ) {
				$output['destination_subsite'] = (int) $profile->mst_destination_subsite;
			}

			if ( isset( $profile->new_prefix ) ) {
				$output['new_prefix'] = $profile->new_prefix;
			}
		}

		return $output;
	}

	/**
	 * Gets legacy Media Files into a format we can work with.
	 *
	 * @param object $profile
	 *
	 * @return array
	 */
	protected function computeMediaFilesDetails( $profile ) {
		// We might already be using the new format.
		if ( isset( $profile->media_files, $profile->media_files['enabled'] ) ) {
			return $profile->media_files;
		}

		// None of the old media files options match up with 2.0 options,
		// so this is all we can assume here.
		$output = [
			'enabled' => false,
		];

		if ( isset( $profile->media_files ) && $profile->media_files ) {
			$output['enabled'] = true;
		}

		return $output;
	}

	/**
	 * Gets legacy Themes & Plugins profile data into a format we can work with.
	 *
	 * @param object $profile
	 *
	 * @return array
	 */
	protected function computeThemePluginDetails( $profile ) {
		// We might already be using the new format.
		if ( isset( $profile->theme_plugin_files ) ) {
			return $profile->theme_plugin_files;
		}

		// Set defaults rather than merge in on frontend
		$output = [
			'plugin_files'     => [
				'enabled' => false,
			],
			'plugins_option'   => '',
			'plugins_selected' => [],
			'plugins_excluded' => [],
			'theme_files'      => [
				'enabled' => false,
			],
			'themes_option'    => '',
			'themes_selected'  => [],
			'themes_excluded'  => [],
		];

		if ( isset( $profile->migrate_themes ) && $profile->migrate_themes === '1' ) {
			if ( ! empty( $profile->select_themes ) ) {
				$output['theme_files']['enabled'] = true;
				$output['themes_selected']        = $profile->select_themes;
			}
		}

		if ( isset( $profile->migrate_plugins ) && $profile->migrate_plugins === '1' ) {
			if ( ! empty( $profile->select_plugins ) ) {
				$output['plugin_files']['enabled'] = true;
				$output['plugins_selected']        = $profile->select_plugins;
			}
		}
		//check if migrate_plugins === '1' and 'plugins_selected'
		if ( isset( $profile->file_ignores ) ) {
			$output['excludes'] = $profile->file_ignores;
		}

		return $output;
	}

	/**
	 * Compute connection info from profile.
	 *
	 * @param object $profileObj
	 *
	 * @return array
	 */
	protected function computeConnectionInfo( $profileObj ) {
		$connection_info = $url = $connection_key = '';

		if ( empty( $profileObj->connection_info ) ) {
			return array( $connection_key, $url, $connection_info );
		}

		$connection_info = $profileObj->connection_info;
		$parts           = explode( PHP_EOL, $connection_info );
		$url             = ! empty( $parts[0] ) ? preg_replace( '/\\r$/', '', $parts[0] ) : '';
		$connection_key  = ! empty( $parts[1] ) ? $parts[1] : '';

		return array( $connection_key, $url, $connection_info );
	}

	/**
	 * Compute advanced options from profile.
	 *
	 * @param object $profileObj
	 * @param string $intent
	 *
	 * @return array
	 */
	public function computeAdvancedOptions( $profileObj, $intent ) {
		$advanced_options = [];

		$possibleAdvOptions = [
			'replace_guids',
			'exclude_spam',
			'exclude_transients',
			'keep_active_plugins',
			'keep_blog_public',
			'compatibility_older_mysql',
			'gzip_file',
		];

		$allowed_advanced_options = [
			'push'         => [
				'replace_guids',
				'exclude_spam',
				'exclude_transients',
				'keep_active_plugins',
				'keep_blog_public',
			],
			'pull'         => [
				'replace_guids',
				'exclude_spam',
				'exclude_transients',
				'keep_active_plugins',
				'keep_blog_public',
			],
			'import'       => [
				'keep_active_plugins',
				'keep_blog_public',
			],
			'savefile'     => [
				'replace_guids',
				'exclude_spam',
				'exclude_transients',
				'compatibility_older_mysql',
				'gzip_file',
			],
			'find_replace' => [
				'replace_guids',
				'exclude_spam',
				'exclude_transients',
			],
		];

		foreach ( $possibleAdvOptions as $option ) {
			if ( isset( $profileObj->$option ) && (string) $profileObj->$option === '1' ) {
				if ( ! in_array( $option, $allowed_advanced_options[ $intent ], true ) ) {
					continue;
				}
				$advanced_options[] = $option;
			}
		}

		return $advanced_options;
	}

	/**
	 * Compose find and replace from profile.
	 *
	 * @param string $home
	 * @param string $path
	 * @param object $profileObj
	 * @param string $intent
	 *
	 * @return array|bool
	 */
	public function composeFindAndReplace( $home, $path, $profileObj, $intent ) {
		if ( ! is_object( $profileObj ) ) {
			return false;
		}

		$custom_search_replace = [];

		if (
			! isset( $profileObj->replace_old ) || ! is_array( $profileObj->replace_old ) ||
			! isset( $profileObj->replace_new ) || ! is_array( $profileObj->replace_new )
		) {
			return false;
		}

		$replace_old = $profileObj->replace_old;
		$replace_new = $profileObj->replace_new;

		if ( in_array( $intent, [ 'pull', 'push', 'import' ] ) ) {
			$values = $intent === 'push' ? $replace_old : $replace_new;

			foreach ( $values as $replace_key => $value ) {
				if ( ( $value === $home || $value === $path ) && ! isset( $profileObj->cli_profile ) ) {
					// Remove replacements as they'd be handled by the "Standard Search and Replace" options
					unset( $replace_old[ $replace_key ], $replace_new[ $replace_key ] );
				}
			}
		}

		foreach ( $replace_old as $key => $item ) {
			$custom_search_replace[] =
				[
					'replace_old' => $item,
					'replace_new' => $replace_new[ $key ],
					'id'          => Util::uuidv4(),
				];
		}

		return $custom_search_replace;
	}

	/**
	 * Compose current migration details from profile.
	 *
	 * @param array  $profile
	 * @param string $intent
	 * @param object $profileObj
	 * @param array  $post_types
	 * @param array  $advanced_options
	 *
	 * @return array
	 */
	protected function composeCurrentMigrationDetails(
		$profile,
		$intent,
		$profileObj,
		array $post_types,
		array $advanced_options
	) {
		$current_migration_details = [
			'connected'                 => false,
			'intent'                    => $intent,
			'tables_option'             => ! empty( $profileObj->table_migrate_option ) && $profileObj->table_migrate_option === 'migrate_select' ? 'selected' : 'all',
			'tables_selected'           => ! empty( $profileObj->select_tables ) ? $profileObj->select_tables : [],
			'backup_option'             => $profileObj->create_backup != "0" ? $profileObj->backup_option : 'none',
			'backup_tables_selected'    => ! empty( $profileObj->select_backup ) ? $profileObj->select_backup : [],
			'post_types_option'         => ! empty( $profileObj->exclude_post_types ) && $profileObj->exclude_post_types === '1' ? 'selected' : 'all',
			'post_types_selected'       => $post_types,
			'advanced_options_selected' => $advanced_options,
			'profile_name'              => $profileObj->name,
			'migration_enabled'         => in_array( $intent, [ 'savefile', 'find_replace' ] ),
			'databaseEnabled'           => isset( $profile['databaseEnabled'] ) ? $profile['databaseEnabled'] : true,
			'preview'                   => isset( $profile['preview'] ) ? $profile['preview'] : false,
		];

		return $this->appendExtraProfileOpts( $profile, $current_migration_details );
	}
}
