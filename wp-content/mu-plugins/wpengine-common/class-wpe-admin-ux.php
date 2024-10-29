<?php
/**
 * Wpe-Admin_Ux
 *
 * @package wpengine/common-mu-plugin
 */

namespace wpe\plugin;

/**
 * Class Wpe_Admin_Ux
 * Handles WordPress Admin UX modifications.
 */
class Wpe_Admin_Ux {

	/**
	 * Registers all hooks for the class.
	 *
	 * This method adds a hook for `admin_head` to apply custom plugin install page changes.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_head', array( $this, 'plugin_install_admin' ), 10 );
		add_action( 'admin_head', array( $this, 'theme_install_admin' ), 10 );
		add_action( 'admin_notices', array( $this, 'wpe_notices' ) );
		add_action( 'network_admin_notices', array( $this, 'wpe_notices' ) );
		$this->handle_dashboard_widgets();
	}

	/**
	 * Register dashboard widget hooks
	 *
	 * @return void
	 */
	public function handle_dashboard_widgets() {
		if ( is_network_admin() ) {
			add_action( 'wp_network_dashboard_setup', array( $this, 'wpe_remove_network_dashboard_widgets' ) );
		} else {
			add_action( 'wp_dashboard_setup', array( $this, 'wpe_remove_dashboard_widgets' ) );
		}
	}

	/**
	 * Remove WordPress Events and News from the Dashboard
	 */
	public function wpe_remove_dashboard_widgets() {
		remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
	}

	/**
	 * Remove WordPress Events and News from the Network Admin Dashboard
	 */
	public function wpe_remove_network_dashboard_widgets() {
		remove_meta_box( 'dashboard_primary', 'dashboard-network', 'side' );
	}

	/**
	 * Applies changes to the plugin install admin screen.
	 *
	 * @return void
	 */
	public function plugin_install_admin() {
		$screen         = get_current_screen();
		$is_plugin_page = $screen && ( 'plugin-install' === $screen->id || 'plugin-install-network' === $screen->id );
		if ( $is_plugin_page && wpe_use_wp_updater_api() ) {
			add_filter( 'gettext', array( $this, 'remove_dotorg_notice_from_plugin_page' ), 10, 3 );
			$this->wpe_missing_plugin_image_styles();
			$this->script_search_plugin_card_image();
		}

		if ( $is_plugin_page && ! wpe_is_feature_flag_active( 'showAddPluginsFavoritesTab' ) ) {
			$this->remove_favorites_tab_script();
		}

		if ( $is_plugin_page && ! wpe_is_feature_flag_active( 'showAddPluginsPopularTags' ) ) {
			$this->remove_popular_tags_script();
		}

		if ( $is_plugin_page && wpe_is_feature_flag_active( 'showAddPluginsFallbackPanel' ) ) {
			$this->plugin_install_hide_selector();
			$this->wpe_repo_notice_styles();
			$this->script_upload_plugin_wrap();
		}
	}

	/**
	 * Applies changes to the plugin install admin screen.
	 *
	 * @return void
	 */
	public function theme_install_admin() {
		$screen        = get_current_screen();
		$is_theme_page = $screen && ( 'theme-install' === $screen->id || 'theme-install-network' === $screen->id );
		if ( $is_theme_page && wpe_is_feature_flag_active( 'showAddThemesFallbackPanel' ) ) {
			$this->theme_install_hide_selector();
			$this->wpe_repo_notice_styles();
			$this->script_upload_theme_wrap();
		}

	}

	/**
	 * Removes the default WordPress.org plugin repository notice.
	 *
	 * Filters the specific text via the gettext hook on the plugin install page.
	 *
	 * @param string $translated_text The translated text.
	 * @param string $untranslated_text The original untranslated text.
	 * @param string $domain The text domain.
	 * @return string Modified text or original text if not a match.
	 */
	public function remove_dotorg_notice_from_plugin_page( $translated_text, $untranslated_text, $domain ) {
		$text_to_remove = 'Plugins extend and expand the functionality of WordPress. You may install plugins in the <a href="%s">WordPress Plugin Directory</a> right from here, or upload a plugin in .zip format by clicking the button at the top of this page.';

		if ( $untranslated_text === $text_to_remove && 'default' === $domain ) {
			$translated_text = '';
		}

		return $translated_text;
	}

	/**
	 * Add inline script to remove Popular Tags section.
	 *
	 * @return void
	 */
	public function remove_popular_tags_script() {
		?>
		<script type="text/javascript">
			document.addEventListener('DOMContentLoaded', function() {
				var popularTags = document.querySelector('.plugins-popular-tags-wrapper');
				if ( popularTags ) {
					popularTags.remove();
				}
			});
		</script>
		<?php
	}
	/**
	 * Add inline script to remove Favorites Tab
	 *
	 * @return void
	 */
	public function remove_favorites_tab_script() {
		?>
		<script type="text/javascript">
			document.addEventListener('DOMContentLoaded', function() {
				var popularTags = document.querySelector('.plugin-install-favorites');
				if ( popularTags ) {
					popularTags.remove();
				}
			});
		</script>
		<?php
	}

	/**
	 * Add inline script to replace missing plugin images.
	 *
	 * @return void
	 */
	public function script_search_plugin_card_image() {
		?>
		<script type="text/javascript">

			function onClassChange(node, callback) {
				var lastClassString = node.classList.toString();

				const mutationObserver = new MutationObserver((mutationList) => {
					for (const item of mutationList) {
						if (item.attributeName === "class") {
							const classString = node.classList.toString();
							if (classString !== lastClassString) {
								callback(mutationObserver);
								lastClassString = classString;
								break;
							}
						}
					}
				});

				mutationObserver.observe(node, { attributes: true });

				return mutationObserver;
			}

			document.addEventListener('DOMContentLoaded', function() {
				const node = document.querySelector("body");
				onClassChange(node, (observer) => {
					var uploadPluginCardWrap = document.querySelectorAll('.plugin-icon');
					if (uploadPluginCardWrap) {
						uploadPluginCardWrap.forEach((card) => {
							if (card.attributes.src.value == '') {
								var newDiv = document.createElement('span');
								newDiv.classList.add('dashicons');
								newDiv.classList.add('fallback-plugin-image');
								card.replaceWith(newDiv);
							}
						});
					}
				});
			});
		</script>
		<?php
	}

	/**
	 * Add ui styling for plugin fallback image.
	 *
	 * @return void
	 */
	public function wpe_missing_plugin_image_styles() {

		?>
		<style>
			.fallback-plugin-image {
				position: absolute;
				top: 20px;
				left: 20px;
				width: 128px;
				height: 128px;
				margin: 0 20px 20px 0;
				padding: 2px;
				background-color: #f0f0f1;
				box-shadow: inset 0 0 10px rgba(167, 170, 173, .15);
				font-size: 60px;
				color: #c3c4c7;
				display: flex; /// need to check how absolute vs flex would work here....
				align-items: center;
				justify-content: center;
				align-items: center;
			}
			.fallback-plugin-image::before {
				content: "\f106";
				font-size: 86px;
			}
		</style>
		<?php
	}

	/**
	 * Inline script for theme upload notice
	 *
	 * @return void
	 */
	public function script_upload_theme_wrap() {
		$div_content = $this->theme_install_ui();
		?>
		<script type="text/javascript">
			document.addEventListener('DOMContentLoaded', function() {
				var uploadPluginWrap = document.querySelector('#wpbody-content');
				if (uploadPluginWrap) {
					var newDiv = document.createElement('div');
					newDiv.innerHTML = <?php echo wp_json_encode( $div_content ); ?>;
					uploadPluginWrap.appendChild(newDiv);
				}
			});
		</script>
		<?php
	}

	/**
	 * Theme Install Notice
	 *
	 * @return string
	 */
	public function theme_install_ui() {
		ob_start();
		?>
		<div class="wpe-repo-notice theme">
			<div class="wpe-repo-notice-content">
				<?php $this->icon_themes(); ?>
				<h2><?php esc_html_e( 'Need to add or update a theme?', 'wpengine' ); ?></h2>
				<p>
				<?php
					echo sprintf(
						wp_kses_post(
							"Theme listings are not currently available. You can still download themes from the <a href='%s' target='_blank' >WordPress Theme Directory</a> and add them using the <strong>Upload Theme</strong> button above."
						),
						esc_url( 'https://wordpress.org/themes/' )
					);
				?>

				</p>
				<a href="https://wpengine.com/support/manage-plugins-and-themes-manually#Install_Theme_in_WP-Admin" target="_blank" ><?php esc_html_e( 'Learn how to manually install a theme', 'wpengine' ); ?></a>
			</div>
			<div class="wpe-repo-notice-logo">
				<?php $this->wpe_logo_horizontal(); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Inline script for plugin upload notice
	 *
	 * @return void
	 */
	public function script_upload_plugin_wrap() {
		$div_content = $this->plugin_install_ui();
		?>
		<script type="text/javascript">
			document.addEventListener('DOMContentLoaded', function() {
				var uploadPluginWrap = document.querySelector('.upload-plugin-wrap');
				if (uploadPluginWrap) {
					var newDiv = document.createElement( 'div' );
					newDiv.innerHTML = <?php echo wp_json_encode( $div_content ); ?>;
					uploadPluginWrap.appendChild(newDiv);
				}
			});
		</script>
		<?php
	}

	/**
	 * Plugin Install Notice
	 *
	 * @return string
	 */
	public function plugin_install_ui() {
		ob_start();
		?>
			<div class="wpe-repo-notice">
			<div class="wpe-repo-notice-content">
				<?php $this->icon_plugins(); ?>
				<h2><?php esc_html_e( 'Need to add or update a plugin?', 'wpengine' ); ?></h2>
				<p>
				<?php
					echo sprintf(
						wp_kses_post(
							/* translators: 1: WordPress plugin URL */
							__( "Plugin listings are not currently available. You can still download plugins from the <a href='%s' target='_blank'>WordPress Plugin Directory</a> and add them using the <strong>Upload Plugin</strong> button above." )
						),
						esc_url( 'https://wordpress.org/plugins/' )
					);
				?>
				</p>
				<a href="https://wpengine.com/support/manage-plugins-and-themes-manually#Install_Plugin_in_WP-Admin" target="_blank"><?php esc_html_e( 'Learn how to manually install a plugin', 'wpengine' ); ?></a>
			</div>
			<div class="wpe-repo-notice-logo">
				<?php $this->wpe_logo_horizontal(); ?>
			</div>
			</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Inline CSS for plugin install notice
	 *
	 * @return void
	 */
	public function plugin_install_hide_selector() {
		?>
		<style>
			.plugin-install-php .wp-filter,
			.plugin-install-php .plugin-install {
				display: none;
			}
		</style>
		<?php
	}

	/**
	 * Inline Styles for WP Engine Notice
	 *
	 * @return void
	 */
	public function wpe_repo_notice_styles() {
		$theme_and_plugin_pages = array( 'theme-install', 'theme-install-network', 'plugin-install', 'plugin-install-network' );
		if ( in_array( get_current_screen()->id, $theme_and_plugin_pages, true ) ) {
			?>
				<style>
					.wpe-repo-notice {
						margin-top: 24px;
						display: flex;
						flex-direction: column;
						align-items: center;
						text-align: center;
						background: #FFFFFF;
						border: 1px solid rgba(31, 41, 55, 0.08);
						box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.02);
						border-radius: 12px;

					}
					.wpe-repo-notice.theme {
						margin-right: 20px;
					}
					.wpe-repo-notice-content {
						display: flex;
						flex-direction: column;
						align-items: center;
						max-width: 540px;
						padding: 56px 40px;
					}
					.wpe-repo-notice h2 {
						font-size: 18px;
						line-height: 21px;
						color: #3C434A;
						font-weight: 510;
					}
					.wpe-repo-notice p {
						font-weight: 400;
						font-size: 14px;
						line-height: 140%;
						color: #3C434A;
					}
					.wpe-repo-notice a {
						font-weight: 510;
						font-size: 14px;
						line-height: 140%;
						color: #006BD6;
					}
					.wpe-repo-notice-logo {
						width: 100%;
						display: flex;
						flex-direction: column;
						justify-content: center;
						align-items: center;
						padding: 16px 0px;
						background: #F8F9FA;
						border-top: 1px solid #EEF0F1;
					}
				</style>
			<?php
		}
	}

	/**
	 * Themes Install Page Hide with CSS
	 *
	 * @return void
	 */
	public function theme_install_hide_selector() {
		?>
			<style>
				.theme-install-php .wp-filter,
				.theme-browser .error,
				.theme-browser .themes,
				.wrap .spinner {
					display: none;
				}
			</style>
		<?php
	}

	/**
	 * Notice that updates are coming from WPE API
	 *
	 * @return void
	 */
	public function wpe_notices() {
		$notices = wpe_get_notices();
		if ( empty( $notices ) ) {
			return;
		}
		$screen_id = get_current_screen()->id;
		foreach ( $notices as $notice ) {
			if ( $notice && ( $notice['isGlobal'] || in_array( $screen_id, $notice['screenIds'], true ) ) ) {
				?>
				<div class="<?php echo esc_attr( $this->get_notice_classes( $notice, $screen_id ) ); ?>" style="<?php echo esc_attr( $this->get_notice_styles( $notice ) ); ?>">
					<?php $this->notice_logo_and_heading( $notice ); ?>
					<p style="max-width:720px;">
						<?php echo wp_kses_post( $notice['message'] ); ?>
					</p>
					<?php $this->notice_ctas( $notice ); ?>
				</div>
				<?php
			}
		}
	}

	/**
	 * Generates the CSS class string for the notice.
	 *
	 * @param  array  $notice The notice data.
	 * @param  string $screen_id Current screen.
	 * @return string The CSS class for the notice.
	 */
	public function get_notice_classes( $notice, $screen_id ) {
		$classes = array( 'notice', 'wpe-notice' );

		$type_class = isset( $notice['type'] ) ? 'notice-' . $notice['type'] : 'notice-info';
		$classes[]  = $type_class;

		if ( isset( $notice['isGlobal'] ) && ! $notice['isGlobal'] ) {
			$page_id_class = 'wpe-notice-' . $screen_id;
			$classes[]     = $page_id_class;
		}

		if ( isset( $notice['isDismissible'] ) && $notice['isDismissible'] ) {
			$classes[] = 'is-dismissible';
		}

		return implode( ' ', $classes );
	}

	/**
	 * Generates the inline style string for the notice.
	 *
	 * @param array $notice The notice data.
	 * @return string The inline styles for the notice.
	 */
	public function get_notice_styles( $notice ) {
		$styles = array( 'padding: 12px;' );

		if ( isset( $notice['type'] ) && 'info' === $notice['type'] ) {
			$styles[] = 'border-left-color: #0ECAD4;';
		}

		return implode( ' ', $styles );
	}


	/**
	 * Outputs a div containing the WPE logo and heading for a notice, if one or both exist.
	 *
	 * @param array $notice The notice data.
	 */
	public function notice_logo_and_heading( $notice ) {
		$has_logo    = isset( $notice['showWpeLogo'] ) && $notice['showWpeLogo'];
		$has_heading = isset( $notice['heading'] ) && ! empty( $notice['heading'] );

		if ( ! $has_logo && ! $has_heading ) {
			return;
		}

		echo '<div class="wpe-notice-header" style="display: flex;flex-direction: row;align-content: center;align-items: center;gap: 8px;">';

		if ( $has_logo ) {
			$this->wpe_logo_square();
		}

		if ( $has_heading ) {
			echo '<h2 style="font-size: 14px; font-weight: 400; margin: 0;">' . esc_html( $notice['heading'] ) . '</h2>';
		}

		echo '</div>';
	}

	/**
	 * Outputs the primary and secondary call to actions as buttons.
	 *
	 * @param array $notice The notice data.
	 */
	public function notice_ctas( $notice ) {
		$primary_cta   = isset( $notice['primaryCallToAction'] ) ? $notice['primaryCallToAction'] : null;
		$secondary_cta = isset( $notice['secondaryCallToAction'] ) ? $notice['secondaryCallToAction'] : null;

		// If both CTAs are empty, return nothing.
		if ( empty( $primary_cta ) && empty( $secondary_cta ) ) {
			return;
		}

		echo '<div class="wpe-notice-ctas" style="display: flex;flex-direction: row;align-content: center;align-items: center;gap: 8px;">';

		if ( ! empty( $primary_cta['url'] ) && ! empty( $primary_cta['text'] ) ) {
			$primary_target = isset( $primary_cta['opensNewTab'] ) && $primary_cta['opensNewTab'] ? ' target="_blank" rel="noopener noreferrer"' : '';
			echo '<a href="' . esc_url( $primary_cta['url'] ) . '" class="button button-primary"' . esc_attr( $primary_target ) . '>';
			echo esc_html( $primary_cta['text'] );
			echo '</a>';
		}

		if ( ! empty( $secondary_cta ) && isset( $secondary_cta['url'], $secondary_cta['text'] ) ) {
			$secondary_target = isset( $secondary_cta['opensNewTab'] ) && $secondary_cta['opensNewTab'] ? ' target="_blank" rel="noopener noreferrer"' : '';
			echo '<a href="' . esc_url( $secondary_cta['url'] ) . '" class="button button-secondary"' . esc_attr( $secondary_target ) . '>';
			echo esc_html( $secondary_cta['text'] );
			echo '</a>';
		}

		echo '</div>';
	}

	/**
	 * Square WPE Logo
	 *
	 * @return void
	 */
	public function wpe_logo_square() {
		?>
		<svg role="img" aria-label="WP Engine Logo" width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
			<path d="M11.3005 17.25C11.4322 17.25 11.5385 17.1437 11.5385 17.012V13.6398C11.5385 13.5137 11.4885 13.3923 11.3988 13.3035L10.4081 12.3127C10.3184 12.2231 10.1978 12.1731 10.0717 12.1731H7.9275C7.80137 12.1731 7.68 12.2231 7.59115 12.3127L6.60036 13.3035C6.51072 13.3931 6.46074 13.5137 6.46074 13.6398V17.012C6.46074 17.1437 6.56704 17.25 6.69873 17.25H11.3005Z" fill="#0ECAD4"/>
			<path d="M13.3027 6.60115L12.3119 7.59195C12.2223 7.68159 12.1723 7.80216 12.1723 7.92829V10.0725C12.1723 10.1986 12.2223 10.32 12.3119 10.4088L13.3027 11.3996C13.3923 11.4893 13.5129 11.5393 13.639 11.5393H17.0112C17.1429 11.5393 17.2492 11.433 17.2492 11.3013V6.70031C17.2492 6.56863 17.1429 6.46233 17.0112 6.46233H13.639C13.5129 6.46233 13.3915 6.51231 13.3027 6.60195V6.60115Z" fill="#0ECAD4"/>
			<path d="M6.69952 0.75C6.56784 0.75 6.46154 0.856298 6.46154 0.987981V4.36017C6.46154 4.4863 6.51151 4.60767 6.60115 4.69651L7.59195 5.68731C7.68159 5.77695 7.80216 5.82692 7.92829 5.82692H10.0725C10.1986 5.82692 10.32 5.77695 10.4088 5.68731L11.3996 4.69651C11.4893 4.60687 11.5393 4.4863 11.5393 4.36017V0.987981C11.5393 0.856298 11.433 0.75 11.3013 0.75H6.69952Z" fill="#0ECAD4"/>
			<path d="M17.012 12.1731H13.6398C13.5137 12.1731 13.3923 12.2231 13.3035 12.3127L12.3127 13.3035C12.2231 13.3931 12.1731 13.5137 12.1731 13.6398V17.012C12.1731 17.1437 12.2794 17.25 12.4111 17.25H17.012C17.1437 17.25 17.25 17.1437 17.25 17.012V12.4111C17.25 12.2794 17.1437 12.1731 17.012 12.1731Z" fill="#0ECAD4"/>
			<path d="M5.58894 0.75H2.21675C2.08983 0.75 1.96925 0.799976 1.87962 0.889615L0.889615 1.87962C0.799976 1.96925 0.75 2.08983 0.75 2.21675V5.58894C0.75 5.72062 0.856298 5.82692 0.987981 5.82692H4.36017C4.4863 5.82692 4.60767 5.77695 4.69651 5.68731L5.68731 4.69651C5.77695 4.60687 5.82692 4.4863 5.82692 4.36017V0.987981C5.82692 0.856298 5.72062 0.75 5.58894 0.75Z" fill="#0ECAD4"/>
			<path d="M12.1731 0.987981V4.36017C12.1731 4.4863 12.2231 4.60767 12.3127 4.69651L13.3035 5.68731C13.3931 5.77695 13.5137 5.82692 13.6398 5.82692H17.012C17.1437 5.82692 17.25 5.72062 17.25 5.58894V0.987981C17.25 0.856298 17.1437 0.75 17.012 0.75H12.4111C12.2794 0.75 12.1731 0.856298 12.1731 0.987981Z" fill="#0ECAD4"/>
			<path d="M9 10.2692C8.29875 10.2692 7.73077 9.70125 7.73077 9C7.73077 8.29875 8.29954 7.73077 9 7.73077C9.70046 7.73077 10.2692 8.29875 10.2692 9C10.2692 9.70125 9.70046 10.2692 9 10.2692Z" fill="#0ECAD4"/>
			<path d="M0.75 12.4111V17.012C0.75 17.1437 0.856298 17.25 0.987981 17.25H5.58894C5.72062 17.25 5.82692 17.1437 5.82692 17.012V13.6398C5.82692 13.5137 5.77695 13.3923 5.68731 13.3035L4.69651 12.3127C4.60687 12.2231 4.4863 12.1731 4.36017 12.1731H0.987981C0.856298 12.1731 0.75 12.2794 0.75 12.4111Z" fill="#0ECAD4"/>
			<path d="M5.68731 7.59115L4.69651 6.60036C4.60687 6.51072 4.4863 6.46074 4.36017 6.46074H0.987981C0.856298 6.46154 0.75 6.56784 0.75 6.69952V11.3005C0.75 11.4322 0.856298 11.5385 0.987981 11.5385H4.4149C4.54103 11.5385 4.6624 11.4885 4.75125 11.3988L5.68731 10.4636C5.77695 10.3739 5.82692 10.2534 5.82692 10.1272V7.92829C5.82692 7.80216 5.77695 7.68079 5.68731 7.59195V7.59115Z" fill="#0ECAD4"/>
		</svg>
		<?php
	}
	/**
	 * WP Engine SVG
	 *
	 * @return void
	 */
	public function wpe_logo_horizontal() {
		?>
		<svg role="img" aria-label="WP Engine Logo" width="104" height="20" viewBox="0 0 104 20" fill="none" xmlns="http://www.w3.org/2000/svg">
			<path d="M102.619 5.45312L102.252 6.32716L101.887 5.45312H101.628V6.6512H101.841V5.84447L102.196 6.6512H102.312L102.663 5.84736V6.6512H102.88V5.45312H102.619Z" fill="#002447"/>
			<path d="M100.477 5.64063H100.839V6.6512H101.055V5.64063H101.418V5.45312H100.477V5.64063Z" fill="#002447"/>
			<path d="M38.1461 5.38517H35.9298C35.8846 5.38517 35.8451 5.41594 35.8355 5.46017C35.6855 6.16017 34.5451 11.5111 34.4817 11.9602C34.4769 11.9938 34.4557 11.9938 34.4509 11.9602C34.3798 11.5111 33.1461 6.15248 32.9865 5.45825C32.9769 5.41498 32.9374 5.38421 32.8932 5.38421H30.825C30.7798 5.38421 30.7413 5.41402 30.7317 5.45825C30.5711 6.15152 29.3336 11.5111 29.2644 11.9602C29.2576 12.0015 29.2336 12.0015 29.2269 11.9602C29.1615 11.5111 28.0249 6.16113 27.8759 5.46017C27.8663 5.41594 27.8278 5.38421 27.7817 5.38421H25.5653C25.5019 5.38421 25.4557 5.44382 25.4721 5.50536L27.8826 14.5313C27.8942 14.5736 27.9317 14.6025 27.9759 14.6025H30.3067C30.3509 14.6025 30.3894 14.5727 30.3999 14.5294C30.5461 13.9131 31.5711 9.59382 31.8028 8.58325C31.8144 8.53325 31.8846 8.53325 31.8961 8.58325C32.1307 9.59286 33.1682 13.914 33.3153 14.5294C33.3259 14.5727 33.3644 14.6025 33.4086 14.6025H35.7336C35.7769 14.6025 35.8153 14.5736 35.8269 14.5313L38.2374 5.50536C38.2538 5.44479 38.2076 5.38421 38.1442 5.38421L38.1461 5.38517Z" fill="#002447"/>
			<path d="M45.3182 5.78709C44.8153 5.51882 44.2173 5.38517 43.524 5.38517H39.9403C39.8874 5.38517 39.8442 5.42844 39.8442 5.48132V14.5073C39.8442 14.5602 39.8874 14.6034 39.9403 14.6034H41.9317C41.9846 14.6034 42.0278 14.5602 42.0278 14.5073V11.7265H43.4615C44.1663 11.7265 44.7749 11.5948 45.2865 11.3304C45.798 11.0669 46.1923 10.6996 46.4682 10.2294C46.7442 9.75921 46.8826 9.20632 46.8826 8.57171C46.8826 7.93709 46.7471 7.38613 46.4769 6.90729C46.2067 6.42844 45.8201 6.05536 45.3173 5.78709H45.3182ZM44.6346 9.31979C44.5192 9.53421 44.3461 9.70152 44.1144 9.82075C43.8836 9.93998 43.5971 10.0006 43.2548 10.0006H42.0288V7.1544H43.249C43.5913 7.1544 43.8788 7.21209 44.1115 7.32748C44.3442 7.44286 44.5182 7.60729 44.6346 7.81979C44.7499 8.03229 44.8076 8.28325 44.8076 8.57171C44.8076 8.86017 44.7499 9.10633 44.6346 9.32075V9.31979Z" fill="#002447"/>
			<path d="M56.9923 6.52556C56.6336 6.16402 56.2298 5.89479 55.7807 5.71786C55.3317 5.54094 54.8644 5.45248 54.3788 5.45248C53.6076 5.45248 52.9307 5.65056 52.348 6.04671C51.7644 6.44286 51.3105 6.98613 50.9865 7.67748C50.6615 8.36882 50.4999 9.16113 50.4999 10.0525C50.4999 10.9438 50.6653 11.7342 50.9951 12.4198C51.3249 13.1063 51.7923 13.6429 52.3971 14.0313C53.0019 14.4188 53.7182 14.6131 54.5471 14.6131C55.1432 14.6131 55.6711 14.5169 56.1307 14.3236C56.5903 14.1313 56.9711 13.8765 57.2749 13.5592C57.5519 13.2698 57.7596 12.9592 57.898 12.6265C57.9192 12.5746 57.8903 12.5159 57.8374 12.4986L56.9971 12.2313C56.9499 12.2169 56.9009 12.24 56.8807 12.2842C56.7759 12.5131 56.6278 12.7256 56.4355 12.9217C56.2221 13.1409 55.9576 13.3169 55.6442 13.4486C55.3298 13.5804 54.9673 13.6467 54.5557 13.6467C53.949 13.6467 53.4221 13.5034 52.9759 13.215C52.5298 12.9275 52.1855 12.5236 51.9423 12.0034C51.7153 11.5179 51.5961 10.9563 51.5807 10.3207H58.0538C58.1067 10.3207 58.1499 10.2775 58.1499 10.2246V9.85344C58.1499 9.1044 58.0471 8.45248 57.8413 7.89767C57.6355 7.34382 57.3528 6.88517 56.9942 6.52363L56.9923 6.52556ZM51.9298 8.03325C52.1644 7.54767 52.4923 7.15729 52.9115 6.86113C53.3307 6.56594 53.8211 6.41786 54.3807 6.41786C54.9403 6.41786 55.4288 6.56306 55.8298 6.85344C56.2307 7.14382 56.5394 7.53998 56.7557 8.04094C56.9317 8.44863 57.0355 8.90055 57.0682 9.39479H51.5846C51.6124 8.90536 51.7269 8.45152 51.9298 8.03325Z" fill="#002447"/>
			<path d="M65.4634 5.82844C65.0124 5.57748 64.4855 5.45248 63.8836 5.45248C63.2134 5.45248 62.6182 5.62363 62.098 5.9669C61.6826 6.24094 61.3567 6.65344 61.1182 7.20056L61.1124 5.6669C61.1124 5.61402 61.0692 5.57075 61.0163 5.57075H60.1711C60.1182 5.57075 60.0749 5.61402 60.0749 5.6669V14.3265C60.0749 14.3794 60.1182 14.4227 60.1711 14.4227H61.0557C61.1086 14.4227 61.1519 14.3794 61.1519 14.3265V8.9044C61.1519 8.39767 61.2586 7.95825 61.4721 7.58613C61.6855 7.21402 61.9778 6.9294 62.3471 6.73132C62.7163 6.53325 63.1336 6.43421 63.598 6.43421C64.2788 6.43421 64.8221 6.64382 65.2288 7.06402C65.6355 7.48325 65.8384 8.05536 65.8384 8.77844V14.3275C65.8384 14.3804 65.8817 14.4236 65.9346 14.4236H66.8115C66.8644 14.4236 66.9076 14.3804 66.9076 14.3275V8.69959C66.9076 7.99767 66.7798 7.40632 66.524 6.92556C66.2682 6.44575 65.9144 6.0794 65.4634 5.8294V5.82844Z" fill="#002447"/>
			<path d="M76.1913 5.57075H75.3384C75.2855 5.57075 75.2422 5.61402 75.2422 5.6669V7.27267H75.1317C75.0153 7.00344 74.848 6.72748 74.6288 6.44575C74.4096 6.16306 74.1211 5.92748 73.7615 5.73709C73.4028 5.54671 72.9538 5.45248 72.4153 5.45248C71.6923 5.45248 71.0557 5.64286 70.5067 6.02267C69.9576 6.40248 69.5298 6.93229 69.224 7.61017C68.9182 8.28806 68.7644 9.0794 68.7644 9.98132C68.7644 10.8832 68.9201 11.6602 69.2317 12.3092C69.5432 12.9582 69.9721 13.4573 70.5182 13.8054C71.0644 14.1534 71.6884 14.3275 72.3903 14.3275C72.9182 14.3275 73.3615 14.2419 73.7201 14.0698C74.0788 13.8986 74.3711 13.6794 74.5951 13.4131C74.8192 13.1467 74.9894 12.8756 75.1057 12.6015H75.2086V14.589C75.2086 15.4015 74.9711 16.0025 74.4961 16.39C74.0211 16.7784 73.3932 16.9717 72.6115 16.9717C72.1201 16.9717 71.7057 16.9044 71.3682 16.7698C71.0307 16.6352 70.7548 16.4611 70.5403 16.2477C70.3528 16.0602 70.199 15.8679 70.0788 15.6698C70.0509 15.6246 69.9922 15.6092 69.9471 15.6361L69.2211 16.0765C69.1778 16.1034 69.1615 16.1592 69.1855 16.2044C69.3557 16.5198 69.5846 16.8015 69.8721 17.0506C70.1836 17.3198 70.5672 17.5323 71.024 17.6881C71.4807 17.8438 72.0096 17.9217 72.6115 17.9217C73.3192 17.9217 73.9499 17.8044 74.5038 17.5698C75.0576 17.3352 75.4932 16.9746 75.8105 16.489C76.1269 16.0034 76.2855 15.3861 76.2855 14.6361V5.6669C76.2855 5.61402 76.2423 5.57075 76.1894 5.57075H76.1913ZM74.9105 11.7823C74.699 12.2919 74.3942 12.6823 73.9961 12.9544C73.5971 13.2265 73.1163 13.3621 72.5509 13.3621C71.9855 13.3621 71.4874 13.2188 71.0865 12.9304C70.6855 12.6429 70.3788 12.2429 70.1682 11.7313C69.9567 11.2198 69.8519 10.6284 69.8519 9.95729C69.8519 9.28613 69.9557 8.70152 70.1644 8.16786C70.373 7.63517 70.6778 7.21113 71.0788 6.89671C71.4798 6.58229 71.9711 6.42556 72.5519 6.42556C73.1326 6.42556 73.6124 6.57748 74.0086 6.88036C74.4048 7.18421 74.7067 7.60056 74.9153 8.13132C75.124 8.66209 75.2278 9.26979 75.2278 9.95633C75.2278 10.6429 75.1221 11.2717 74.9115 11.7813L74.9105 11.7823Z" fill="#002447"/>
			<path d="M80.0394 5.57075H79.1548C79.1016 5.57075 79.0586 5.6138 79.0586 5.6669V14.3265C79.0586 14.3796 79.1016 14.4227 79.1548 14.4227H80.0394C80.0925 14.4227 80.1355 14.3796 80.1355 14.3265V5.6669C80.1355 5.6138 80.0925 5.57075 80.0394 5.57075Z" fill="#002447"/>
			<path d="M79.5971 4.31594C79.9332 4.31594 80.2057 4.04344 80.2057 3.70729C80.2057 3.37114 79.9332 3.09863 79.5971 3.09863C79.2609 3.09863 78.9884 3.37114 78.9884 3.70729C78.9884 4.04344 79.2609 4.31594 79.5971 4.31594Z" fill="#002447"/>
			<path d="M88.374 5.82844C87.923 5.57748 87.3961 5.45248 86.7942 5.45248C86.124 5.45248 85.5288 5.62363 85.0086 5.9669C84.5932 6.24094 84.2673 6.65344 84.0288 7.20056L84.023 5.6669C84.023 5.61402 83.9797 5.57075 83.9269 5.57075H83.0817C83.0288 5.57075 82.9855 5.61402 82.9855 5.6669V14.3265C82.9855 14.3794 83.0288 14.4227 83.0817 14.4227H83.9663C84.0192 14.4227 84.0624 14.3794 84.0624 14.3265V8.9044C84.0624 8.39767 84.1692 7.95825 84.3826 7.58613C84.5961 7.21402 84.8884 6.9294 85.2576 6.73132C85.6269 6.53325 86.0442 6.43421 86.5086 6.43421C87.1894 6.43421 87.7326 6.64382 88.1394 7.06402C88.5461 7.48325 88.749 8.05536 88.749 8.77844V14.3275C88.749 14.3804 88.7923 14.4236 88.8451 14.4236H89.7221C89.7749 14.4236 89.8182 14.3804 89.8182 14.3275V8.69959C89.8182 7.99767 89.6903 7.40632 89.4346 6.92556C89.1788 6.44575 88.8249 6.0794 88.374 5.8294V5.82844Z" fill="#002447"/>
			<path d="M98.2278 6.52556C97.8692 6.16402 97.4653 5.89479 97.0163 5.71786C96.5672 5.54094 96.0999 5.45248 95.6144 5.45248C94.8432 5.45248 94.1663 5.65056 93.5836 6.04671C92.9999 6.44286 92.5461 6.98613 92.2221 7.67748C91.8971 8.36882 91.7355 9.16113 91.7355 10.0525C91.7355 10.9438 91.9009 11.7342 92.2307 12.4198C92.5605 13.1063 93.0278 13.6429 93.6326 14.0313C94.2374 14.4188 94.9538 14.6131 95.7826 14.6131C96.3788 14.6131 96.9067 14.5169 97.3663 14.3236C97.8259 14.1313 98.2067 13.8765 98.5105 13.5592C98.7874 13.2698 98.9951 12.9592 99.1336 12.6265C99.1547 12.5746 99.1259 12.5159 99.073 12.4986L98.2326 12.2313C98.1855 12.2169 98.1365 12.24 98.1163 12.2842C98.0115 12.5131 97.8634 12.7256 97.6711 12.9217C97.4576 13.1409 97.1932 13.3169 96.8798 13.4486C96.5653 13.5804 96.2028 13.6467 95.7913 13.6467C95.1846 13.6467 94.6576 13.5034 94.2115 13.215C93.7653 12.9275 93.4211 12.5236 93.1778 12.0034C92.9509 11.5179 92.8317 10.9563 92.8163 10.3207H99.2894C99.3422 10.3207 99.3855 10.2775 99.3855 10.2246V9.85344C99.3855 9.1044 99.2826 8.45248 99.0769 7.89767C98.8711 7.34382 98.5884 6.88517 98.2297 6.52363L98.2278 6.52556ZM93.1644 8.03325C93.399 7.54767 93.7269 7.15729 94.1461 6.86113C94.5653 6.56594 95.0557 6.41786 95.6153 6.41786C96.1749 6.41786 96.6634 6.56306 97.0644 6.85344C97.4653 7.14382 97.774 7.53998 97.9903 8.04094C98.1663 8.44863 98.2701 8.90055 98.3028 9.39479H92.8192C92.8471 8.90536 92.9615 8.45152 93.1644 8.03325Z" fill="#002447"/>
			<path d="M13.6342 20C13.7938 20 13.9226 19.8712 13.9226 19.7115V15.624C13.9226 15.4712 13.862 15.324 13.7534 15.2163L12.5524 14.0154C12.4438 13.9067 12.2976 13.8462 12.1447 13.8462H9.5457C9.39282 13.8462 9.2457 13.9067 9.13801 14.0154L7.93705 15.2163C7.82839 15.325 7.76782 15.4712 7.76782 15.624V19.7115C7.76782 19.8712 7.89666 20 8.05628 20H13.6342Z" fill="#0ECAD4"/>
			<path d="M16.0611 7.09231L14.8601 8.29327C14.7515 8.40192 14.6909 8.54808 14.6909 8.70096V11.3C14.6909 11.4529 14.7515 11.6 14.8601 11.7077L16.0611 12.9087C16.1697 13.0173 16.3159 13.0779 16.4688 13.0779H20.5563C20.7159 13.0779 20.8447 12.949 20.8447 12.7894V7.2125C20.8447 7.05289 20.7159 6.92404 20.5563 6.92404H16.4688C16.3159 6.92404 16.1688 6.98462 16.0611 7.09327V7.09231Z" fill="#0ECAD4"/>
			<path d="M8.05724 0C7.89763 0 7.76878 0.128846 7.76878 0.288462V4.37596C7.76878 4.52885 7.82936 4.67596 7.93801 4.78365L9.13897 5.98462C9.24763 6.09327 9.39378 6.15385 9.54666 6.15385H12.1457C12.2986 6.15385 12.4457 6.09327 12.5534 5.98462L13.7544 4.78365C13.863 4.675 13.9236 4.52885 13.9236 4.37596V0.288462C13.9236 0.128846 13.7947 0 13.6351 0H8.05724Z" fill="#0ECAD4"/>
			<path d="M20.5572 13.8462H16.4697C16.3169 13.8462 16.1697 13.9067 16.062 14.0154L14.8611 15.2163C14.7524 15.325 14.6919 15.4712 14.6919 15.624V19.7115C14.6919 19.8712 14.8207 20 14.9803 20H20.5572C20.7169 20 20.8457 19.8712 20.8457 19.7115V14.1346C20.8457 13.975 20.7169 13.8462 20.5572 13.8462Z" fill="#0ECAD4"/>
			<path d="M6.71109 0H2.62359C2.46974 0 2.32359 0.0605769 2.21493 0.169231L1.01493 1.36923C0.90628 1.47788 0.845703 1.62404 0.845703 1.77788V5.86538C0.845703 6.025 0.974549 6.15385 1.13416 6.15385H5.22166C5.37455 6.15385 5.52166 6.09327 5.62936 5.98462L6.83032 4.78365C6.93897 4.675 6.99955 4.52885 6.99955 4.37596V0.288462C6.99955 0.128846 6.8707 0 6.71109 0Z" fill="#0ECAD4"/>
			<path d="M14.6919 0.288462V4.37596C14.6919 4.52885 14.7524 4.67596 14.8611 4.78365L16.062 5.98462C16.1707 6.09327 16.3169 6.15385 16.4697 6.15385H20.5572C20.7169 6.15385 20.8457 6.025 20.8457 5.86538V0.288462C20.8457 0.128846 20.7169 0 20.5572 0H14.9803C14.8207 0 14.6919 0.128846 14.6919 0.288462Z" fill="#0ECAD4"/>
			<path d="M10.8457 11.5385C9.9957 11.5385 9.30724 10.85 9.30724 10C9.30724 9.15 9.99666 8.46154 10.8457 8.46154C11.6947 8.46154 12.3842 9.15 12.3842 10C12.3842 10.85 11.6947 11.5385 10.8457 11.5385Z" fill="#0ECAD4"/>
			<path d="M0.845703 14.1346V19.7115C0.845703 19.8712 0.974549 20 1.13416 20H6.71109C6.8707 20 6.99955 19.8712 6.99955 19.7115V15.624C6.99955 15.4712 6.93897 15.324 6.83032 15.2163L5.62936 14.0154C5.5207 13.9067 5.37455 13.8462 5.22166 13.8462H1.13416C0.974549 13.8462 0.845703 13.975 0.845703 14.1346Z" fill="#0ECAD4"/>
			<path d="M6.83032 8.29231L5.62936 7.09135C5.5207 6.98269 5.37455 6.92212 5.22166 6.92212H1.13416C0.974549 6.92308 0.845703 7.05192 0.845703 7.21154V12.7885C0.845703 12.9481 0.974549 13.0769 1.13416 13.0769H5.28801C5.4409 13.0769 5.58801 13.0163 5.6957 12.9077L6.83032 11.774C6.93897 11.6654 6.99955 11.5192 6.99955 11.3663V8.70096C6.99955 8.54808 6.93897 8.40096 6.83032 8.29327V8.29231Z" fill="#0ECAD4"/>
		</svg>
		<?php
	}

	/**
	 * Theme Icon
	 *
	 * @return void
	 */
	public function icon_themes() {
		?>
		<svg role="img" aria-label="Themes" width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
			<path fill-rule="evenodd" clip-rule="evenodd" d="M17.3973 37.2116C15.1153 37.9459 13.2834 39.6655 12.6048 41.4218C12.0648 42.8195 11.609 44.9639 11.2524 47.261C10.9209 49.396 10.6867 51.5836 10.5452 53.2688C12.7553 53.0294 15.2177 52.7595 17.4843 52.3267C19.8956 51.8662 21.8967 51.2527 23.1095 50.4229C24.4358 49.5155 26.1988 47.7987 26.8963 45.7433C27.2352 44.7447 27.3209 43.6764 26.9993 42.5515C26.6759 41.42 25.9134 40.1373 24.4005 38.7648C22.1708 36.7419 19.6159 36.4978 17.3973 37.2116ZM16.6316 34.8318C19.5983 33.8773 23.1121 34.2204 26.0803 36.9133C27.8589 38.5268 28.925 40.1919 29.4031 41.8644C29.883 43.5434 29.7428 45.135 29.2637 46.5467C28.3254 49.3117 26.0844 51.4167 24.5212 52.4862C22.8514 53.6287 20.4057 54.3139 17.9532 54.7823C15.5449 55.2422 12.9472 55.5234 10.7325 55.7631C10.6565 55.7713 10.5809 55.7795 10.5058 55.7876C9.12726 55.937 7.91318 54.8089 8.02693 53.3887C8.16848 51.6215 8.4174 49.2258 8.78196 46.8775C9.14271 44.5538 9.63008 42.1844 10.2728 40.5208C11.2583 37.9703 13.7283 35.7659 16.6316 34.8318Z" fill="#006BD6"/>
			<path fill-rule="evenodd" clip-rule="evenodd" d="M52.4185 11.4805C51.196 10.258 49.2233 10.2286 47.9649 11.4141L24.6131 33.4133C24.1106 33.8867 23.3195 33.8631 22.8461 33.3606C22.3727 32.8581 22.3963 32.067 22.8988 31.5936L46.2507 9.59439C48.4929 7.48206 52.008 7.53446 54.1862 9.7127C56.371 11.8975 56.4162 15.4255 54.2882 17.6656L32.4929 40.6078C32.0174 41.1083 31.2262 41.1286 30.7257 40.6531C30.2252 40.1776 30.2049 39.3864 30.6804 38.8859L52.4757 15.9437C53.67 14.6866 53.6446 12.7066 52.4185 11.4805Z" fill="#006BD6"/>
			<path fill-rule="evenodd" clip-rule="evenodd" d="M27 55C27 54.4477 27.4477 54 28 54H48C48.5523 54 49 54.4477 49 55C49 55.5523 48.5523 56 48 56H28C27.4477 56 27 55.5523 27 55Z" fill="#0ECAD4"/>
			<path fill-rule="evenodd" clip-rule="evenodd" d="M50.9996 55C50.9996 54.4477 51.4473 54 51.9996 54L54.9996 54C55.5519 54 55.9996 54.4477 55.9996 55C55.9996 55.5523 55.5519 56 54.9996 56L51.9996 56C51.4473 56 50.9996 55.5523 50.9996 55Z" fill="#0ECAD4"/>
			</svg>
		<?php
	}

	/**
	 * Plugin Icon
	 *
	 * @return void
	 */
	public function icon_plugins() {
		?>
		<svg role="img" aria-label="Plugins" width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
			<path fill-rule="evenodd" clip-rule="evenodd" d="M49.2154 14.6417C49.6894 14.1398 50.4806 14.1172 50.9825 14.5912C55.7936 19.1351 58.0042 25.8782 57.874 32.5211C57.7437 39.1639 55.2707 45.8817 50.483 50.4083C45.7745 54.86 40.3754 56.9737 35.4753 57.4354C30.6129 57.8936 26.1357 56.7294 23.3392 54.4728C20.6193 52.2779 19.3616 49.6792 18.9207 47.48C18.7018 46.3883 18.6819 45.385 18.7899 44.5683C18.8364 44.2166 18.9087 43.8833 19.0079 43.5836C18.0409 42.4127 17.4887 41.1566 17.2812 39.8701C17.0348 38.343 17.2868 36.8562 17.7819 35.5158C18.5702 33.3816 20.0163 31.5032 21.3444 30.1831C21.1391 29.7195 21.2301 29.1575 21.6142 28.7824C22.1081 28.3 22.8995 28.3094 23.3819 28.8033L26.1818 31.6703L28.4517 29.4004C28.9399 28.9122 29.7314 28.9122 30.2195 29.4004C30.7077 29.8886 30.7077 30.68 30.2195 31.1682L27.9287 33.459L30.3161 35.9036L32.6355 33.5841C33.1236 33.096 33.9151 33.096 34.4032 33.5841C34.8914 34.0723 34.8914 34.8637 34.4032 35.3519L32.0629 37.6923L35.1245 40.8272C35.6068 41.3211 35.5974 42.1125 35.1035 42.5948C34.6877 43.001 34.0608 43.0585 33.5852 42.7716C32.2796 44.0591 30.4387 45.4431 28.3468 46.1913C27.0132 46.6682 25.5346 46.9014 24.0158 46.6418C23.0624 46.4788 22.1251 46.1268 21.2261 45.5573C21.2246 45.9749 21.2657 46.4592 21.3719 46.9885C21.7054 48.6518 22.6723 50.7221 24.9092 52.5272C27.0695 54.2706 30.8423 55.361 35.2407 54.9465C39.6014 54.5355 44.4739 52.6492 48.7654 48.5917C52.9777 44.6092 55.2547 38.5769 55.3744 32.472C55.4942 26.3672 53.4548 20.3649 49.2659 16.4088C48.764 15.9348 48.7414 15.1436 49.2154 14.6417ZM31.8099 41.0114L23.0358 32.0271C21.9216 33.1499 20.7468 34.7041 20.127 36.3821C19.7417 37.4251 19.5875 38.4695 19.7493 39.4719C19.9085 40.4591 20.3866 41.4882 21.4027 42.5042C22.4219 43.5234 23.4519 44.0091 24.4371 44.1776C25.4363 44.3484 26.473 44.2064 27.5049 43.8373C29.1666 43.243 30.7024 42.0993 31.8099 41.0114Z" fill="#006BD6"/>
			<path fill-rule="evenodd" clip-rule="evenodd" d="M14.4869 49.4052C13.987 49.8813 13.1957 49.862 12.7196 49.3621C7.43261 43.8106 5.67511 36.7654 6.23561 30.1978C6.79384 23.6565 9.65837 17.4834 13.7653 13.5926C23.6446 4.23305 34.8336 4.80086 40.8924 9.51332C43.8799 11.837 45.1306 14.4242 45.4567 16.6312C45.6176 17.7203 45.5509 18.7023 45.3685 19.4933C45.3026 19.7795 45.2144 20.0703 45.1017 20.3451C46.048 21.5044 46.5898 22.7463 46.795 24.0179C47.0414 25.545 46.7894 27.0319 46.2943 28.3722C45.5059 30.5064 44.0599 32.3849 42.7317 33.7049C42.9371 34.1685 42.846 34.7305 42.4619 35.1057C41.9681 35.588 41.1767 35.5786 40.6943 35.0847L40.1009 34.4771L28.9517 23.0609C28.4694 22.567 28.4787 21.7756 28.9726 21.2932C29.3885 20.887 30.0154 20.8295 30.491 21.1164C31.7966 19.8289 33.6375 18.4449 35.7294 17.6967C37.063 17.2198 38.5416 16.9866 40.0603 17.2462C41.0725 17.4192 42.0664 17.8053 43.0157 18.4384C43.062 18.0272 43.0637 17.539 42.9835 16.9967C42.7575 15.4667 41.87 13.4409 39.3575 11.4867C34.4163 7.6434 24.605 6.76696 15.4846 15.4074C11.8746 18.8275 9.2391 24.4044 8.72655 30.4104C8.21624 36.39 9.81715 42.6894 14.53 47.6379C15.0061 48.1379 14.9868 48.9291 14.4869 49.4052ZM32.2662 22.8767L41.0404 31.861C42.1546 30.7381 43.3293 29.1839 43.9492 27.5059C44.3345 26.4629 44.4886 25.4185 44.3269 24.4161C44.1676 23.4289 43.6895 22.3999 42.6735 21.3838C41.6543 20.3646 40.6243 19.8789 39.6391 19.7105C38.6399 19.5396 37.6032 19.6817 36.5713 20.0507C34.9096 20.645 33.3738 21.7888 32.2662 22.8767Z" fill="#0ECAD4"/>
		</svg>
		<?php
	}

	/**
	 * WordPress SVG
	 *
	 * @return void
	 */
	public function icon_wp() {
		?>
		<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 48 48" fill="none">
			<g clip-path="url(#clip0_88_3631)">
				<path d="M3.41211 24.0001C3.41211 32.1496 8.14813 39.1923 15.0157 42.5297L5.19463 15.6211C4.05225 18.1817 3.41211 21.0153 3.41211 24.0001Z" fill="#464342"/>
				<path d="M37.9001 22.9611C37.9001 20.4166 36.9861 18.6544 36.2022 17.2829C35.1586 15.5869 34.1803 14.1507 34.1803 12.4548C34.1803 10.5622 35.6158 8.80042 37.6376 8.80042C37.7289 8.80042 37.8155 8.81178 37.9044 8.81687C34.2414 5.46103 29.3613 3.41211 24.0012 3.41211C16.8084 3.41211 10.4802 7.10252 6.79883 12.6922C7.28187 12.7067 7.7371 12.7169 8.12377 12.7169C10.2773 12.7169 13.6108 12.4556 13.6108 12.4556C14.7207 12.3901 14.8515 14.0203 13.7428 14.1515C13.7428 14.1515 12.6275 14.2828 11.3864 14.3478L18.8839 36.6489L23.3896 23.1358L20.1819 14.347C19.0732 14.282 18.0228 14.1507 18.0228 14.1507C16.9134 14.0857 17.0434 12.3894 18.1529 12.4548C18.1529 12.4548 21.553 12.7161 23.5761 12.7161C25.7292 12.7161 29.0631 12.4548 29.0631 12.4548C30.1738 12.3894 30.3042 14.0195 29.1951 14.1507C29.1951 14.1507 28.0774 14.282 26.8387 14.347L34.2791 36.4793L36.3327 29.6168C37.2228 26.769 37.9001 24.7236 37.9001 22.9611Z" fill="#464342"/>
				<path d="M24.3609 25.8008L18.1836 43.751C20.028 44.2932 21.9786 44.5897 23.9997 44.5897C26.3973 44.5897 28.6966 44.1753 30.8368 43.4227C30.7815 43.3345 30.7314 43.2409 30.6902 43.139L24.3609 25.8008Z" fill="#464342"/>
				<path d="M42.0654 14.1211C42.1539 14.7769 42.2041 15.4809 42.2041 16.2382C42.2041 18.3275 41.8139 20.6761 40.6386 23.6127L34.3496 41.7956C40.4705 38.2263 44.5876 31.5949 44.5876 23.9994C44.5879 20.4199 43.6736 17.0538 42.0654 14.1211Z" fill="#464342"/>
				<path d="M24.0002 0C10.7668 0 0 10.766 0 23.9994C0 37.2343 10.7668 48 24.0002 48C37.2332 48 48.0016 37.2343 48.0016 23.9994C48.0012 10.766 37.2332 0 24.0002 0ZM24.0002 46.8999C11.3737 46.8999 1.10046 36.6267 1.10046 23.9994C1.10046 11.3729 11.3733 1.10046 24.0002 1.10046C36.6263 1.10046 46.8988 11.3729 46.8988 23.9994C46.8988 36.6267 36.6263 46.8999 24.0002 46.8999Z" fill="#464342"/>
			</g>
			<defs>
				<clipPath id="clip0_88_3631">
					<rect width="47.9988" height="48" fill="white"/>
				</clipPath>
			</defs>
		</svg>
		<?php
	}

	/**
	 * External Link Icon
	 *
	 * @return void
	 */
	public function icon_external_link() {
		?>
		<svg role="img" aria-label="opens in a new tab" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
			<path d="M12.8326 5.24909V1.74909C12.8326 1.42242 12.5701 1.16576 12.2493 1.16576C11.9226 1.16576 11.666 1.42242 11.666 1.74909V5.24909C11.666 5.56992 11.9226 5.83242 12.2493 5.83242C12.5701 5.83242 12.8326 5.56992 12.8326 5.24909ZM12.2493 1.16576H8.7493C8.42263 1.16576 8.16596 1.42242 8.16596 1.74909C8.16596 2.06992 8.42263 2.33242 8.7493 2.33242H12.2493C12.5701 2.33242 12.8326 2.06992 12.8326 1.74909C12.8326 1.42242 12.5701 1.16576 12.2493 1.16576ZM11.8351 1.33492L6.58513 6.57909C6.3518 6.80659 6.3518 7.17409 6.5793 7.40159C6.8068 7.62909 7.1743 7.62909 7.4018 7.40159L12.6518 2.15159C12.8793 1.91826 12.8793 1.55076 12.6518 1.32326C12.4185 1.08992 12.051 1.08992 11.8235 1.31742L11.8351 1.33492ZM5.8268 1.15992H4.54346C3.19596 1.15992 2.88096 1.18326 2.4318 1.41076C1.98846 1.63242 1.63263 1.98826 1.41096 2.42576C1.17763 2.86909 1.1543 3.18992 1.1543 4.53159V9.43159C1.1543 10.7733 1.17763 11.0883 1.40513 11.5374C1.6268 11.9749 1.98263 12.3308 2.42013 12.5524C2.86346 12.7799 3.1843 12.8033 4.52596 12.8033H9.42596C10.7676 12.8033 11.0826 12.7741 11.5318 12.5466C11.9693 12.3191 12.3251 11.9633 12.5468 11.5258C12.7743 11.0766 12.7976 10.7558 12.7976 9.41409V8.13075C12.7976 7.80409 12.5351 7.54742 12.2143 7.54742C11.8876 7.54742 11.631 7.80409 11.631 8.13075V9.41409C11.631 10.5166 11.6076 10.7849 11.5026 10.9891C11.386 11.2049 11.211 11.3858 10.9893 11.4966C10.7793 11.6016 10.5168 11.6191 9.40846 11.6191H4.50846C3.40013 11.6191 3.1318 11.5958 2.92763 11.4908C2.70596 11.3741 2.52513 11.1991 2.4143 10.9774C2.30346 10.7674 2.28596 10.5049 2.28596 9.39659V4.49659C2.28596 3.38826 2.30346 3.11992 2.40846 2.91576C2.5193 2.69409 2.6943 2.51326 2.91596 2.40242C3.12013 2.29159 3.38263 2.27409 4.49096 2.27409H5.7743C6.09513 2.27409 6.35763 2.01159 6.35763 1.69076C6.35763 1.36409 6.09513 1.10742 5.7743 1.10742L5.8268 1.15992Z" fill="white"/>
		</svg>
		<?php
	}
}

$wpe_admin_ux = new Wpe_Admin_Ux();
$wpe_admin_ux->register();
