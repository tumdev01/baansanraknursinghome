<?php

namespace DeliciousBrains\WPMDB\Pro\Migration;

use DeliciousBrains\WPMDB\Common\Error\ErrorLog;
use DeliciousBrains\WPMDB\Common\FormData\FormData;
use DeliciousBrains\WPMDB\Common\Http\Helper;
use DeliciousBrains\WPMDB\Common\Http\Http;
use DeliciousBrains\WPMDB\Common\Http\Scramble;
use DeliciousBrains\WPMDB\Common\Http\WPMDBRestAPIServer;
use DeliciousBrains\WPMDB\Common\Migration\FinalizeMigration;
use DeliciousBrains\WPMDB\Common\Migration\Flush as Migration_Flush;
use DeliciousBrains\WPMDB\Common\Migration\MigrationHelper;
use DeliciousBrains\WPMDB\Common\Migration\MigrationManager;
use DeliciousBrains\WPMDB\Common\MigrationPersistence\Persistence;
use DeliciousBrains\WPMDB\Common\MigrationState\MigrationStateManager;
use DeliciousBrains\WPMDB\Common\Properties\Properties;
use DeliciousBrains\WPMDB\Common\Settings\Settings;
use DI\DependencyException;
use DI\NotFoundException;
use WP_Error;

class FinalizeComplete {
	/**
	 * @var Scramble
	 */
	private $scrambler;

	/**
	 * @var MigrationStateManager
	 */
	private $migration_state_manager;

	/**
	 * @var Http
	 */
	private $http;

	/**
	 * @var Helper
	 */
	private $http_helper;

	/**
	 * @var Properties
	 */
	private $props;

	/**
	 * @var ErrorLog
	 */
	private $error_log;

	/**
	 * @var MigrationManager
	 */
	private $migration_manager;

	/**
	 * @var FormData
	 */
	private $form_data;

	/**
	 * @var FinalizeMigration
	 */
	private $finalize;

	/**
	 * @var Settings
	 */
	private $settings;

	/**
	 * @var WPMDBRestAPIServer
	 */
	private $rest_API_server;

	/**
	 * @var Migration_Flush
	 */
	private $flush;

	public function __construct(
		Scramble $scrambler,
		MigrationStateManager $migration_state_manager,
		Http $http,
		Helper $http_helper,
		Properties $props,
		ErrorLog $error_log,
		MigrationManager $migration_manager,
		FormData $form_data,
		FinalizeMigration $finalize,
		Settings $settings,
		WPMDBRestAPIServer $rest_API_server,
		Migration_Flush $flush
	) {
		$this->scrambler               = $scrambler;
		$this->migration_state_manager = $migration_state_manager;
		$this->http                    = $http;
		$this->http_helper             = $http_helper;
		$this->props                   = $props;
		$this->error_log               = $error_log;
		$this->migration_manager       = $migration_manager;
		$this->form_data               = $form_data;
		$this->finalize                = $finalize;
		$this->settings                = $settings->get_settings();
		$this->rest_API_server         = $rest_API_server;
		$this->flush                   = $flush;
	}

	public function register() {
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		add_action(
			'wp_ajax_nopriv_wpmdb_remote_finalize_migration',
			[ $this, 'respond_to_remote_finalize_migration' ]
		);
		add_action( 'wp_ajax_nopriv_wpmdb_remote_flush', [ $this, 'respond_to_remote_flush' ] );
		add_action( 'wp_ajax_nopriv_wpmdb_fire_migration_complete', [ $this, 'ajax_fire_migration_complete' ] );
	}

	public function register_rest_routes() {
		$this->rest_API_server->registerRestRoute( '/migration-complete', [
			'methods'  => 'POST',
			'callback' => [ $this, 'ajax_fire_migration_complete' ],
		] );
	}

	/**
	 * Flush the caches on the remote site.
	 *
	 * @return void
	 * @throws DependencyException
	 * @throws NotFoundException
	 */
	public function respond_to_remote_flush() {
		MigrationHelper::set_is_remote();
		add_filter( 'wpmdb_before_response', array( $this->scrambler, 'scramble' ) );

		$key_rules  = array(
			'action'       => 'key',
			'migration_id' => 'key',
			'sig'          => 'string',
		);
		$state_data = $this->migration_state_manager->set_post_data( $key_rules );

		if ( is_wp_error( $state_data ) ) {
			$this->http->end_ajax( $state_data );

			return;
		}

		$filtered_post = $this->http_helper->filter_post_elements( $state_data, array( 'action', 'migration_id' ) );

		if ( ! $this->http_helper->verify_signature( $filtered_post, $this->settings['key'] ) ) {
			$error_msg = $this->props->invalid_content_verification_error . ' (#123)';
			$this->http->end_ajax( new WP_Error( 'wpmdb_invalid_content_verification_error', $error_msg ) );

			return;
		}

		MigrationHelper::set_current_migration_id( $filtered_post['migration_id'] );

		$return = $this->flush->flush_local();

		$this->http->end_ajax( $return );
	}

	/**
	 * The remote's handler for a request to finalize a migration.
	 *
	 * @return void
	 */
	function respond_to_remote_finalize_migration() {
		MigrationHelper::set_is_remote();
		add_filter( 'wpmdb_before_response', array( $this->scrambler, 'scramble' ) );

		$key_rules = array(
			'action'       => 'key',
			'intent'       => 'key',
			'migration_id' => 'key',
			'url'          => 'url',
			'form_data'    => 'string',
			'tables'       => 'string',
			'temp_prefix'  => 'string',
			'site_details' => 'string',
			'prefix'       => 'string',
			'stage'        => 'string',
			'type'         => 'key',
			'location'     => 'url',
			'sig'          => 'string',
		);

		$state_data = Persistence::setRemotePostData( $key_rules, __METHOD__ );

		$filtered_post = $this->http_helper->filter_post_elements(
			$state_data,
			array(
				'action',
				'intent',
				'migration_id',
				'url',
				'form_data',
				'site_details',
				'tables',
				'temp_prefix',
				'prefix',
				'type',
				'location',
			)
		);

		if ( ! $this->http_helper->verify_signature( $filtered_post, $this->settings['key'] ) ) {
			$error_msg = $this->props->invalid_content_verification_error . ' (#123)';
			$this->http->end_ajax( new WP_Error( 'wpmdb_invalid_content_verification_error', $error_msg ) );

			return;
		}

		MigrationHelper::set_current_migration_id( $filtered_post['migration_id'] );

		$this->form_data            = base64_decode( $filtered_post['form_data'] );
		$state_data['site_details'] = json_decode( base64_decode( $state_data['site_details'] ), true );

		$return = $this->finalize->local_finalize_migration( $state_data );

		do_action( 'wpmdb_remote_finalize', $state_data, $return );

		$this->http->end_ajax( $return );
	}

	/**
	 * Triggers the wpmdb_migration_complete action once the migration is complete.
	 *
	 * @return void
	 */
	public function ajax_fire_migration_complete() {
		MigrationHelper::set_is_remote();

		$state_data = Persistence::setPostData(
			[
				'action'       => 'string',
				'migration_id' => 'key',
				'url'          => 'string',
				'sig'          => 'string',
			],
			__METHOD__
		);

		$filtered_post = $this->http_helper->filter_post_elements(
			$state_data,
			array( 'action', 'migration_id', 'url' )
		);

		if ( ! $this->http_helper->verify_signature( $filtered_post, $this->settings['key'] ) ) {
			$error_msg = $this->props->invalid_content_verification_error . ' (#138)';
			$this->http->end_ajax( new WP_Error( 'wpmdb_invalid_content_verification_error', $error_msg ) );

			return;
		}

		MigrationHelper::set_current_migration_id( $filtered_post['migration_id'] );

		do_action( 'wpmdb_migration_complete', 'pull', $state_data['url'], '' );

		$this->http->end_ajax( true );
	}
}
