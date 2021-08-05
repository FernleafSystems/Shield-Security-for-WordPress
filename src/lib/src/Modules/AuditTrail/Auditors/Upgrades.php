<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Services\Services;

class Upgrades extends Base {

	/**
	 * @var array
	 */
	private $plugins;

	/**
	 * @var array
	 */
	private $themes;

	protected function run() {
		$this->init();
		add_action( 'upgrader_process_complete', [ $this, 'auditUpgrades' ], 10, 2 );
		add_filter( 'upgrader_post_install', [ $this, 'auditUpgrade2' ], 10, 2 );
	}

	public function auditUpgrade2( $true, $hooksExtra ) {
		add_action( $this->getCon()->prefix( 'pre_plugin_shutdown' ),
			function () use ( $hooksExtra ) {
				if ( !empty( $hooksExtra[ 'plugin' ] ) ) {
					$this->handlePlugin( $hooksExtra[ 'plugin' ] );
				}
				elseif ( !empty( $hooksExtra[ 'theme' ] ) ) {
					$this->handleTheme( $hooksExtra[ 'theme' ] );
				}
			}
		);
		return $true;
	}

	private function init() {
		$this->plugins = array_map( function ( $plugin ) {
			return $plugin[ 'Version' ];
		}, Services::WpPlugins()->getPlugins() );
		$this->themes = array_map( function ( $theme ) {
			return $theme->get( 'Version' );
		}, Services::WpThemes()->getThemes() );
	}

	/**
	 * @param \WP_Upgrader $handler
	 * @param array        $data
	 */
	public function auditUpgrades( $handler, $data ) {
		$action = $data[ 'action' ] ?? null;
		$type = $data[ 'type' ] ?? null;
		if ( $action === 'update' && in_array( $type, [ 'plugin', 'theme' ] ) ) {
			if ( !empty( $data[ 'plugins' ] ) && is_array( $data[ 'plugins' ] ) ) {
				foreach ( $data[ 'plugins' ] as $item ) {
					if ( isset( $this->plugins[ $item ] ) ) {
						$this->handlePlugin( $item );
					}
				}
			}
			elseif ( !empty( $data[ 'themes' ] ) && is_array( $data[ 'themes' ] ) ) {
				foreach ( $data[ 'themes' ] as $item ) {
					if ( isset( $this->themes[ $item ] ) ) {
						$this->handleTheme( $item );
					}
				}
			}
		}
	}

	private function handlePlugin( string $item ) {
		$WPP = Services::WpPlugins();
		$VO = $WPP->getPluginAsVo( $item, true );
		if ( !empty( $VO ) ) {
			$this->getCon()->fireEvent(
				'plugin_upgraded',
				[
					'audit' => [
						'plugin' => $VO->file, // was 'file'
						'from'   => $this->plugins[ $item ],
						'to'     => $VO->Version,
					]
				]
			);
		}
	}

	/**
	 * Hooked into 'shutdown' to ensure that the latest theme data is avaiable
	 * so that we can get the "upgraded to" version correctly.
	 * @param string $item
	 */
	private function handleTheme( string $item ) {
		$WPT = Services::WpThemes();
		$VO = $WPT->getThemeAsVo( $item, true );
		if ( !empty( $VO ) ) {
			$this->getCon()->fireEvent(
				'theme_upgraded',
				[
					'audit' => [
						'theme' => $VO->stylesheet, // was 'file'
						'from'  => $this->themes[ $item ],
						'to'    => $VO->Version,
					]
				]
			);
		}
	}
}