<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Paths;

class Enqueue {

	use PluginControllerConsumer;
	use ExecOnce;

	public const CSS = 'css';
	public const JS = 'js';
	public const PLUGIN_ADMIN_HOOK_SUFFIX = 'toplevel_page_icwp-wpsf-plugin';

	private $adminHookSuffix = '';

	private $enqueuedHandles = [];

	protected function canRun() :bool {
		$WP = Services::WpGeneral();
		return !$WP->isAjax() && !$WP->isCron() && !empty( self::con()->cfg->includes[ 'dist' ] );
	}

	protected function run() {

		add_action( 'login_enqueue_scripts', function () {
			$this->enqueue();
			add_action( 'login_footer', function () {
				$this->dequeue();
			}, -1000 );
		}, 1000 );

		add_action( 'wp_enqueue_scripts', function () {
			$this->enqueue();
			add_action( 'wp_footer', function () {
				$this->dequeue();
			}, -1000 );
		}, 1000 );

		add_action( 'admin_enqueue_scripts', function ( $hook_suffix ) {
			$this->adminHookSuffix = (string)$hook_suffix;
			/** ALWAYS cast to string when setting this property */
			$this->enqueue();
			add_action( 'admin_footer', function () {
				$this->dequeue();
			}, -1000 );
		}, 1000 );

		add_action( 'admin_enqueue_scripts', function () {
			global $wp_scripts;
			global $wp_styles;
			$this->removeConflictingAdminAssets( $wp_scripts );
			$this->removeConflictingAdminAssets( $wp_styles );
		}, \PHP_INT_MAX );

		$this->compatibility();
	}

	private function compatibility() {
		/** https://kb.mailpoet.com/article/365-how-to-solve-3rd-party-css-menu-conflict */
		add_filter( 'mailpoet_conflict_resolver_whitelist_script', function ( $scripts ) {
			$scripts[] = \dirname( self::con()->base_file );
			return $scripts;
		} );
		add_filter( 'mailpoet_conflict_resolver_whitelist_style', function ( $scripts ) {
			$scripts[] = \dirname( self::con()->base_file );
			return $scripts;
		} );
	}

	/**
	 * @param \WP_Styles|\WP_Scripts $depContainer
	 */
	private function removeConflictingAdminAssets( $depContainer ) {
		$toDequeue = [];

		if ( self::con()->getIsPage_PluginAdmin() ) {
			$default = [
				'cerber_css',
				'bootstrap',
				'convesio-caching-custom-styles', // fixed-admin-menu
				'custom-admin-style', // fixed-admin-menu
				'wp-notes',
				'wpforo',
				'fs_common',
				'this-day-in-history',
				'mo_oauth_admin_settings_style',
				'mo_oauth_admin_settings_phone_style',
				'mo_oauth_admin_settings_datatable',
				'workreap',
				'core_functions', //workreap
				'wc_connect_banner',
				'wc-stripe-blocks-checkout-style',
				'wcpay-admin-css',
				'ce4wp',
				'mailjet',
				'monsterinsights',
				'udb-admin',
			];
		}
		else {
			$default = [];
		}

		$filtered = \apply_filters( 'shield/conflict_assets_to_dequeue', $default, $depContainer );
		$conflictHandlesRegEx = \implode( '|', \array_map( 'preg_quote', \is_array( $filtered ) ? $filtered : $default ) );

		if ( !empty( $conflictHandlesRegEx ) ) {
			foreach ( $depContainer->queue as $script ) {
				$handle = (string)$depContainer->registered[ $script ]->handle;
				if ( \strpos( $handle, self::con()->prefix() ) === false
					 && \preg_match( sprintf( '/(%s)/i', $conflictHandlesRegEx ), $handle ) ) {
					$toDequeue[] = $handle;
				}
			}
		}
		$depContainer->dequeue( $toDequeue );
	}

	protected function dequeue() {
		foreach ( \apply_filters( 'shield/custom_dequeues', [] ) as $handle ) {
			$handle = $this->normaliseHandle( $handle );
			wp_dequeue_style( $handle );
			wp_dequeue_script( $handle );
		}
	}

	protected function enqueue() {

		// Register all plugin assets
		$this->registerAssets();
		$assets = $this->buildAssetsToEnqueue();

		// Combine enqueues and enqueue assets
		foreach ( [ self::CSS, self::JS ] as $type ) {
			foreach ( $assets[ $type ] as $asset ) {
				$asset = $this->normaliseHandle( $asset );
				$type == self::CSS ? wp_enqueue_style( $asset ) : wp_enqueue_script( $asset );
			}
		}

		// Get module localisations
		$this->localise();
	}

	/**
	 * Registers all assets in the plugin with the global population of assets (if not already included)
	 * This allows us to easily share assets amongst plugins and especially to use the Foundation Classes
	 * plugin to cater for most shared assets.
	 */
	private function registerAssets() {
		$con = self::con();
		foreach ( [ self::CSS, self::JS ] as $type ) {

			foreach ( $con->cfg->includes[ 'tp' ] as $tpKey => $includes ) {
				$url = $includes[ $type ] ?? null;
				if ( !empty( $url ) ) {
					$this->register(
						sprintf( 'shield/tp/%s', $tpKey ),
						$url,
						$this->normaliseHandles( $dist[ 'deps' ] ?? [] )
					);
				}
			}

			foreach ( $con->cfg->includes[ 'dist' ] as $dist ) {
				$adminOnly = !empty( $dist[ 'flags' ][ 'admin_only' ] );
				if ( !$adminOnly || is_admin() || is_network_admin() ) {
					$this->register(
						$this->normaliseHandle( $dist[ 'handle' ] ),
						$con->urls->forAsset( sprintf( 'dist/shield-%s.bundle.%s', $dist[ 'handle' ], $type ) ),
						$this->normaliseHandles( $dist[ 'deps' ] ?? [] )
					);
				}
			}
		}
	}

	private function register( string $handle, string $url, array $deps = [] ) :void {
		$path = \wp_parse_url( $url, \PHP_URL_PATH );
		if ( !empty( $path ) ) {
			Paths::Ext( $path ) === 'js' ?
				wp_register_script( $handle, $url, $deps, self::con()->cfg->version(), true )
				: wp_register_style( $handle, $url, [], self::con()->cfg->version() );
		}
	}

	/**
	 * We use the idea of "zones" into which a collection of handles will be enqueued, or you can enqueue directly
	 * using the handle (adjusted using the filter).
	 */
	private function buildAssetsToEnqueue() :array {

		$enqueueZones = [];
		switch ( current_action() ) {
			case 'admin_enqueue_scripts':
				if ( $this->adminHookSuffix === self::PLUGIN_ADMIN_HOOK_SUFFIX ) {
					$enqueueZones[] = 'plugin_admin';
				}
				else {
					$enqueueZones[] = 'wp_admin';
				}
				break;
			case 'wp_enqueue_scripts':
				$enqueueZones[] = 'badge';
				break;
			case 'login_enqueue_scripts':
				$enqueueZones[] = 'page_login';
				break;
			default:
				break;
		}

		$customAssets = apply_filters( 'shield/custom_enqueue_assets', [], $this->adminHookSuffix );

		$assets = [
			self::JS  => [],
			self::CSS => [],
		];
		foreach ( self::con()->cfg->includes[ 'dist' ] as $dist ) {

			if ( \count( \array_intersect( $enqueueZones, $dist[ 'zones' ] ?? [] ) ) > 0
				 || \in_array( $dist[ 'handle' ], $customAssets ) ) {

				$this->enqueuedHandles[] = $dist[ 'handle' ];

				foreach ( \array_keys( $assets ) as $type ) {
					if ( \in_array( $type, $dist[ 'types' ] ) ) {
						$assets[ $type ][] = $this->normaliseHandle( $dist[ 'handle' ] );
					}
				}
			}
		}

		$this->enqueuedHandles = \array_unique( $this->enqueuedHandles );

		return $assets;
	}

	private function localise() {
		$locals = apply_filters( 'shield/custom_localisations', [], $this->adminHookSuffix, $this->enqueuedHandles );
		foreach ( \is_array( $locals ) ? $locals : [] as $local ) {
			if ( \is_array( $local ) && \count( $local ) === 3 ) {
				wp_localize_script( $this->normaliseHandle( $local[ 0 ] ), $local[ 1 ], $local[ 2 ] );
			}
			else {
				\error_log( 'Invalid localisation: '.\var_export( $local, true ) );
			}
		}
	}

	private function normaliseHandles( array $handles ) :array {
		return \array_map(
			function ( $handle ) {
				return $this->normaliseHandle( $handle );
			},
			$handles
		);
	}

	private function normaliseHandle( string $handle ) :string {
		if ( \str_starts_with( $handle, 'wp-' ) ) {
			$handle = \preg_replace( '#^wp-#', '', $handle );
		}
		elseif ( !\str_starts_with( $handle, 'shield/tp/' ) ) {
			$handle = self::con()->prefix( $handle );
		}
		return $handle;
	}
}