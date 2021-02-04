<?php

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

		$this->registerAssets();

		if ( current_action() == 'admin_enqueue_scripts' ) {
			$this->enqueueAdminType( self::TYPE_JS );
			$this->enqueueAdminType( self::TYPE_CSS );
		}
		else {
			$this->enqueueFrontendType( self::TYPE_JS );
			$this->enqueueFrontendType( self::TYPE_CSS );
		}
	}

	/**
	 * Registers all assets in the plugin with the global population of assets (if not already included)
	 * This allows us to easily share assets amongst plugins and especially to use the Foundation Classes
	 * plugin to cater for most shared assets.
	 */
	private function registerAssets() {
		$this->registerAssetsType( self::TYPE_CSS )
			 ->registerAssetsType( self::TYPE_JS );
	}

	private function registerAssetsType( string $type ) :self {
		$con = $this->getCon();
		$incl = $con->cfg->includes[ 'register' ];

		$assetKeys = [
			self::TYPE_CSS => [],
			self::TYPE_JS  => [],
		];

		if ( !empty( $incl[ $type ] ) ) {

			foreach ( $incl[ $type ] as $key => $depends ) {

				if ( !in_array( $key, $assetKeys[ $type ] ) ) {

					$handle = $con->prefix( $key );

					if ( $type === self::TYPE_CSS ) {

						$reg = wp_register_style(
							$handle,
							$con->getPluginUrl_Css( $key ),
							$this->prefixKeys( $depends ),
							$con->getVersion()
						);
					}
					else {
						$reg = wp_register_script(
							$handle,
							$con->getPluginUrl_Js( $key ),
							$this->prefixKeys( $depends ),
							$con->getVersion()
						);
					}

					if ( $reg ) {
						$assetKeys[ $type ][] = $handle;
					}
				}
			}
		}

		return $this;
	}

	private function prefixKeys( array $keys ) :array {
		return array_map( function ( $dependency ) {
			return strpos( $dependency, 'wp-' ) === 0 ?
				str_replace( 'wp-', '', $dependency )
				: $this->getCon()->prefix( $dependency );
		}, $keys );
	}

	private function enqueueFrontendType( string $type ) {
		$assets = $this->getCon()->cfg->includes[ 'frontend' ][ $type ] ?? [];
		$this->runEnqueueOnAssets( $type, $assets );
	}

	private function enqueueAdminType( string $type ) {
		$con = $this->getCon();

		$assets = $con->cfg->includes[ 'admin' ][ $type ];
		if ( $con->getIsPage_PluginAdmin() ) {
			$assets = array_merge(
				$assets,
				$con->cfg->includes[ 'plugin_admin' ][ $type ]
			);
		}

		$this->runEnqueueOnAssets( $type, $assets );
	}

	private function runEnqueueOnAssets( string $type, array $asset ) {
		array_map(
			$type == self::TYPE_CSS ? 'wp_enqueue_style' : 'wp_enqueue_script',
			$this->prefixKeys( $asset )
		);
	}
}
