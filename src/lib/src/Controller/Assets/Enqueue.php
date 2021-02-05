<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Enqueue {

	use PluginControllerConsumer;
	use ExecOnce;

	const CSS = 'css';
	const JS = 'js';

	protected function canRun() :bool {
		$WP = Services::WpGeneral();
		return !$WP->isAjax() && !$WP->isCron()
			   && !empty( $this->getCon()->cfg->includes[ 'register' ] );
	}

	protected function run() {
		add_action( 'wp_enqueue_scripts', function () {
			$this->enqueue();
		}, 1000 );
		add_action( 'admin_enqueue_scripts', function () {
			$this->enqueue();
		}, 1000 );
	}

	protected function enqueue() {

		// Register all plugin assets
		$this->registerAssets();

		// Get standard enqueues
		if ( current_action() == 'admin_enqueue_scripts' ) {
			$assets = $this->getAdminAssetsToEnq();
		}
		else {
			$assets = $this->getFrontendAssetsToEnq();
		}

		// Get custom enqueues from modules
		$customAssets = $this->getCustomEnqueues();

		// Combine enqueues and enqueue assets
		foreach ( [ self::CSS, self::JS ] as $type ) {
			if ( !empty( $customAssets[ $type ] ) ) {
				$assets[ $type ] = array_unique( array_merge( $assets[ $type ], $customAssets[ $type ] ) );
			}
			$this->runEnqueueOnAssets( $type, $assets[ $type ] );
		}

		// Get module localisations
		$this->localise();
	}

	private function localise() {
		foreach ( $this->getCon()->modules as $module ) {
			foreach ( $module->getScriptLocalisations() as $localisation ) {
				if ( is_array( $localisation ) && count( $localisation ) === 3 ) { //sanity
					wp_localize_script( $localisation[ 0 ], $localisation[ 1 ], $localisation[ 2 ] );
				}
				else {
					error_log( 'Invalid localisation: '.var_export( $localisation, true ) );
				}
			}
		}
	}

	/**
	 * Registers all assets in the plugin with the global population of assets (if not already included)
	 * This allows us to easily share assets amongst plugins and especially to use the Foundation Classes
	 * plugin to cater for most shared assets.
	 */
	private function registerAssets() {
		$con = $this->getCon();

		$assetKeys = [
			self::CSS => [],
			self::JS  => [],
		];

		$incl = $con->cfg->includes[ 'register' ];

		foreach ( array_keys( $assetKeys ) as $type ) {

			foreach ( $incl[ $type ] as $key => $spec ) {
				if ( !in_array( $key, $assetKeys[ $type ] ) ) {

					$handle = $this->normaliseHandle( $key );
					if ( $type === self::CSS ) {
						$url = $spec[ 'url' ] ?? $con->urls->forCss( $key );
						$reg = wp_register_style(
							$handle,
							$url,
							$this->prefixKeys( $spec[ 'deps' ] ?? [] ),
							$con->getVersion()
						);
					}
					else {
						$url = $spec[ 'url' ] ?? $con->urls->forJs( $key );
						$reg = wp_register_script(
							$handle,
							$url,
							$this->prefixKeys( $spec[ 'deps' ] ?? [] ),
							$con->getVersion()
						);
					}

					if ( $reg ) {
						$assetKeys[ $type ][] = $handle;
					}
				}
			}
		}
	}

	private function getCustomEnqueues() :array {
		$enqueues = [
			self::CSS => [],
			self::JS  => [],
		];
		foreach ( $this->getCon()->modules as $module ) {
			$custom = $module->getCustomScriptEnqueues();
			foreach ( array_keys( $enqueues ) as $type ) {
				if ( !empty( $custom[ $type ] ) ) {
					$enqueues[ $type ] = array_merge( $enqueues[ $type ], $custom[ $type ] );
				}
			}
		}
		return $enqueues;
	}

	private function prefixKeys( array $keys ) :array {
		return array_map( function ( $handle ) {
			return strpos( $handle, 'wp-' ) === 0 ?
				preg_replace( '#^wp-#', '', $handle )
				: $this->normaliseHandle( $handle );
		}, $keys );
	}

	private function normaliseHandle( string $handle ) :string {
		return str_replace( '/', '-', $this->getCon()->prefix( $handle ) );
	}

	private function getAdminAssetsToEnq() {
		$con = $this->getCon();
		return $con->cfg->includes[ $con->getIsPage_PluginAdmin() ? 'plugin_admin' : 'admin' ];
	}

	private function getFrontendAssetsToEnq() :array {
		return $this->getCon()->cfg->includes[ 'frontend' ] ?? [];
	}

	private function runEnqueueOnAssets( string $type, array $asset ) {
		array_map(
			$type == self::CSS ? 'wp_enqueue_style' : 'wp_enqueue_script',
			$this->prefixKeys( $asset )
		);
	}
}
