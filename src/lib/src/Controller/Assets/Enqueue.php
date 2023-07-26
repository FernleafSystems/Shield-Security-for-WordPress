<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Enqueue {

	use PluginControllerConsumer;
	use ExecOnce;

	public const CSS = 'css';
	public const JS = 'js';

	private $adminHookSuffix = '';

	protected function canRun() :bool {
		$WP = Services::WpGeneral();
		return !$WP->isAjax() && !$WP->isCron() && !empty( $this->con()->cfg->includes[ 'register' ] );
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
			$this->adminHookSuffix = $hook_suffix;
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
	}

	/**
	 * @param \WP_Styles|\WP_Scripts $depContainer
	 */
	private function removeConflictingAdminAssets( $depContainer ) {
		$toDequeue = [];

		if ( $this->con()->getIsPage_PluginAdmin() ) {
			$default = [
				'cerber_css',
				'bootstrap',
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
				if ( \strpos( $handle, $this->con()->prefix() ) === false
					 && \preg_match( sprintf( '/(%s)/i', $conflictHandlesRegEx ), $handle ) ) {
					$toDequeue[] = $handle;
				}
			}
		}
		$depContainer->dequeue( $toDequeue );
	}

	protected function dequeue() {
		$customDequeues = \apply_filters( 'shield/custom_dequeues', [
			self::CSS => [],
			self::JS  => [],
		] );
		foreach ( $customDequeues as $type => $assets ) {
			foreach ( $assets as $asset ) {
				$handle = $this->normaliseHandle( $asset );
				$type == self::CSS ? wp_dequeue_style( $handle ) : wp_dequeue_script( $handle );
			}
		}
	}

	protected function enqueue() {

		// Register all plugin assets
		$this->registerAssets();

		// Get standard enqueues
		$assets = current_action() == 'admin_enqueue_scripts' ? $this->getAdminAssetsToEnq() : $this->getFrontendAssetsToEnq();

		// Get custom enqueues from modules or elsewhere
		$customAssets = apply_filters( 'shield/custom_enqueues', [
			self::CSS => [],
			self::JS  => [],
		], $this->adminHookSuffix );

		// Combine enqueues and enqueue assets
		foreach ( [ self::CSS, self::JS ] as $type ) {
			if ( !empty( $customAssets[ $type ] ) ) {
				$assets[ $type ] = \array_unique( \array_merge( $assets[ $type ], $customAssets[ $type ] ) );
			}
			$this->runEnqueueOnAssets( $type, $assets[ $type ] );
		}

		// Get module localisations
		$this->localise();
	}

	private function localise() {
		foreach ( apply_filters( 'shield/custom_localisations', [], $this->adminHookSuffix ) as $local ) {
			if ( \is_array( $local ) && \count( $local ) === 3 ) {
				wp_localize_script( $this->normaliseHandle( $local[ 0 ] ), $local[ 1 ], $local[ 2 ] );
			}
			else {
				\error_log( 'Invalid localisation: '.\var_export( $local, true ) );
			}
		}
	}

	/**
	 * Registers all assets in the plugin with the global population of assets (if not already included)
	 * This allows us to easily share assets amongst plugins and especially to use the Foundation Classes
	 * plugin to cater for most shared assets.
	 */
	private function registerAssets() {
		$con = $this->con();

		$assetKeys = [
			self::CSS => [],
			self::JS  => [],
		];

		$incl = $con->cfg->includes[ 'register' ];

		$includesService = Services::Includes();
		foreach ( \array_keys( $assetKeys ) as $type ) {

			foreach ( $incl[ $type ] as $key => $spec ) {
				if ( !\in_array( $key, $assetKeys[ $type ] ) ) {

					$deps = $spec[ 'deps' ] ?? [];

					$handle = $this->normaliseHandle( $key );
					if ( $type === self::CSS ) {
						$reg = wp_register_style(
							$handle,
							$con->urls->forCss( $key ),
							$this->prefixKeys( $deps ),
							$con->getVersion()
						);
					}
					else {
						if ( \strpos( $key, 'jquery/' ) ) {
							\array_unshift( $deps, 'wp-jquery' );
						}

						$reg = wp_register_script(
							$handle,
							$con->urls->forJs( $key ),
							$this->prefixKeys( $deps ),
							$con->getVersion(),
							$spec[ 'footer' ] ?? false
						);
					}

					if ( !empty( $spec[ 'attributes' ] ) ) {
						foreach ( $spec[ 'attributes' ] as $attribute => $value ) {
							$includesService->addIncludeAttribute( $handle, $attribute, $value );
						}
					}

					if ( $reg ) {
						$assetKeys[ $type ][] = $handle;
					}
				}
			}
		}
	}

	private function prefixKeys( array $keys ) :array {
		return \array_map( function ( $handle ) {
			return \strpos( $handle, 'wp-' ) === 0 ? \preg_replace( '#^wp-#', '', $handle ) : $this->normaliseHandle( $handle );
		}, $keys );
	}

	private function normaliseHandle( string $handle ) :string {
		return \str_replace( '/', '-', $this->con()->prefix(
			\FernleafSystems\Wordpress\Services\Utilities\File\Paths::RemoveExt( $handle )
		) );
	}

	private function getAdminAssetsToEnq() {
		$con = $this->con();
		$admin = $con->cfg->includes[ 'admin' ];
		if ( $con->getIsPage_PluginAdmin() ) {
			$plugin = $con->cfg->includes[ 'plugin_admin' ];
			$admin[ 'css' ] = \array_unique( \array_merge( $admin[ 'css' ], $plugin[ 'css' ] ) );
			$admin[ 'js' ] = \array_unique( \array_merge( $admin[ 'js' ], $plugin[ 'js' ] ) );
		}
		return $admin;
	}

	private function getFrontendAssetsToEnq() :array {
		return $this->con()->cfg->includes[ 'frontend' ] ?? [];
	}

	private function runEnqueueOnAssets( string $type, array $asset ) {
		\array_map(
			function ( $asset ) use ( $type ) {
				if ( $type == self::CSS ) {
					wp_enqueue_style( $asset );
				}
				else {
					wp_enqueue_script( $asset );
				}
			},
			$this->prefixKeys( $asset )
		);
	}
}