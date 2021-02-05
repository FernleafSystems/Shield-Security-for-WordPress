<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Enqueue {

	use PluginControllerConsumer;
	use ExecOnce;

	const TYPE_CSS = 'css';
	const TYPE_JS = 'js';

	/**
	 * @var string - unique string to separate our enqueues from everyone else's
	 */
	private static $sPrefix;

	/**
	 * @var array[] - the complete population of assets available to our plugins for enqueue
	 */
	private static $aAssetKeys;

	/**
	 * @var array[] - the complete population of assets available to our plugins for enqueue
	 */
	private static $aAdhocKeys;

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
		foreach ( [ self::TYPE_CSS, self::TYPE_JS ] as $type ) {
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
			self::TYPE_CSS => [],
			self::TYPE_JS  => [],
		];

		$incl = $con->cfg->includes[ 'register' ];
		foreach ( array_keys( $assetKeys ) as $type ) {

			foreach ( $incl[ $type ] as $key => $spec ) {
				if ( !in_array( $key, $assetKeys[ $type ] ) ) {

					$handle = $con->prefix( $key );
//					error_log( $handle );
					if ( $type === self::TYPE_CSS ) {
						$url = $spec[ 'url' ] ?? $con->getPluginUrl_Css( $key );
						$reg = wp_register_style(
							$handle,
							$url,
							$this->prefixKeys( $spec[ 'deps' ] ?? [] ),
							$con->getVersion()
						);
					}
					else {
						$url = $spec[ 'url' ] ?? $con->getPluginUrl_Js( $key );
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

	private function registerAssetsByType( string $type ) :self {

		return $this;
	}

	private function getCustomEnqueues() :array {
		$enqueues = [
			self::TYPE_CSS => [],
			self::TYPE_JS  => [],
		];
		foreach ( $this->getCon()->modules as $module ) {
			$custom = $module->getCustomEnqueues();
			foreach ( array_keys( $enqueues ) as $type ) {
				if ( !empty( $custom[ $type ] ) ) {
					$enqueues[ $type ] = array_merge( $enqueues[ $type ], $custom[ $type ] );
				}
			}
		}
		return $enqueues;
	}

	private function getCustomRegistrations() :array {
	}

	private function prefixKeys( array $keys ) :array {
		return array_map( function ( $dependency ) {
			return strpos( $dependency, 'wp-' ) === 0 ?
				preg_replace( '#^wp-#', '', $dependency )
				: $this->getCon()->prefix( $dependency );
		}, $keys );
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
			$type == self::TYPE_CSS ? 'wp_enqueue_style' : 'wp_enqueue_script',
			$this->prefixKeys( $asset )
		);
	}
}
