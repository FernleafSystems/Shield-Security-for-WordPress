<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes\ZoneReportPlugins;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\DiffVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Snapper\SnapPlugins;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Capturing plugin deactivated (while also support Snapshot checking) is complicated because
 * unlike the hook 'activated_plugin', the 'deactivated_plugin' hook is fired BEFORE the
 * call to update_option( 'active_plugins' ). This means the status of "activeness" of a plugin isn't
 * stored correctly when we examine it for deactivated plugins. So we must wait for the call to
 * update the 'active_plugins' WP option before we can fire any events associated with deactivated plugins.
 */
class Plugins extends Base {

	/**
	 * @var array
	 */
	private $pluginVersions;

	private $slugs;

	private $deactivatedPlugins = [];

	public function canSnapRealtime() :bool {
		return true;
	}

	protected function initAuditHooks() :void {
		$this->pluginVersions = \array_map( function ( $plugin ) {
			return $plugin[ 'Version' ];
		}, Services::WpPlugins()->getPlugins() );
		$this->slugs = \array_keys( $this->pluginVersions );

		add_action( 'activated_plugin', [ $this, 'auditActivatedPlugin' ] );
		add_action( 'deactivated_plugin', [ $this, 'auditDeactivatedPlugin' ] );
		add_action( 'update_option_active_plugins', [ $this, 'auditDeactivatedPluginsPart2' ], 9, 2 );
		add_action( 'upgrader_process_complete', [ $this, 'auditInstall' ], 10, 0 );
		add_action( 'pre_uninstall_plugin', [ $this, 'auditUninstalled' ] );
		add_action( 'deleted_plugin', [ $this, 'auditUninstalled' ] );
		add_action( 'wp_ajax_edit-theme-plugin-file', [ $this, 'auditEditedFile' ], -1 ); // they hook on 1

		add_action( 'upgrader_process_complete', [ $this, 'auditUpgrades' ], 10, 2 );
		add_filter( 'upgrader_post_install', [ $this, 'auditUpgrade2' ], 10, 2 );
	}

	public function auditInstall() {
		$current = Services::WpPlugins()->getInstalledPluginFiles();
		foreach ( \array_diff( $current, $this->slugs ) as $new ) {
			$vo = Services::WpPlugins()->getPluginAsVo( $new, true );
			if ( !empty( $vo ) ) {
				$this->fireAuditEvent( 'plugin_installed', [
					'plugin'  => $new,
					'version' => \ltrim( $vo->Version, 'v' ),
					'name'    => $vo->Name,
				] );
			}
		}
		$this->slugs = $current;
	}

	/**
	 * @param string $plugin
	 */
	public function auditActivatedPlugin( $plugin ) {
		if ( !empty( $plugin ) ) {
			$vo = Services::WpPlugins()->getPluginAsVo( $plugin, true );
			if ( !empty( $vo ) ) {
				$this->fireAuditEvent( 'plugin_activated', [
					'plugin'  => $vo->file,
					'version' => \ltrim( $vo->Version, 'v' ),
					'name'    => $vo->Name,
				] );
			}
		}
	}

	/**
	 * @param string $plugin
	 */
	public function auditDeactivatedPlugin( $plugin ) {
		if ( !empty( $plugin ) ) {
			$this->deactivatedPlugins[] = $plugin;
		}
	}

	public function auditDeactivatedPluginsPart2( $oldValue, $newValue ) {
		if ( !empty( $this->deactivatedPlugins ) ) {
			foreach ( $this->deactivatedPlugins as $deactivatedPlugin ) {
				if ( \in_array( $deactivatedPlugin, $oldValue ) && !\in_array( $deactivatedPlugin, $newValue ) ) {
					$vo = Services::WpPlugins()->getPluginAsVo( $deactivatedPlugin, true );
					if ( !empty( $vo ) ) {
						$this->fireAuditEvent( 'plugin_deactivated', [
							'plugin'  => $vo->file,
							'version' => \ltrim( $vo->Version, 'v' ),
							'name'    => $vo->Name,
						] );
					}
				}
			}
			$this->deactivatedPlugins = [];
		}
	}

	public function auditUninstalled( $plugin ) {
		$vo = Services::WpPlugins()->getPluginAsVo( $plugin );
		if ( !empty( $vo ) ) {
			if ( \function_exists( 'wp_clean_plugins_cache' ) ) {
				wp_clean_plugins_cache();
			}
			$this->fireAuditEvent( 'plugin_uninstalled', [
				'plugin'  => $vo->file,
				'version' => \ltrim( $vo->Version, 'v' ),
				'name'    => $vo->Name,
			] );
		}
	}

	/**
	 * @see \wp_edit_theme_plugin_file()
	 */
	public function auditEditedFile() {
		$req = Services::Request();
		if ( $req->isPost()
			 && !empty( $req->post( 'plugin' ) )
			 && Services::WpPlugins()->isInstalled( $req->post( 'plugin' ) )
			 && current_user_can( 'edit_plugins' )
			 && wp_verify_nonce( $req->post( 'nonce' ), 'edit-plugin_'.$req->post( 'file' ) )
		) {
			self::con()->fireEvent(
				'plugin_file_edited',
				[ 'audit_params' => [ 'file' => sanitize_text_field( $req->post( 'file' ) ) ] ]
			);
		}
	}

	/**
	 * @param \WP_Upgrader $handler
	 * @param array        $data
	 */
	public function auditUpgrades( $handler, $data ) {
		if ( ( $data[ 'action' ] ?? null ) === 'update'
			 && ( $data[ 'type' ] ?? null ) === 'plugin'
			 && !empty( $data[ 'plugins' ] )
			 && \is_array( $data[ 'plugins' ] )
		) {
			foreach ( $data[ 'plugins' ] as $item ) {
				$this->handlePluginUpgrade( $item );
			}
		}
	}

	public function auditUpgrade2( $true, $hooksExtra ) {
		add_action( self::con()->prefix( 'pre_plugin_shutdown' ),
			function () use ( $hooksExtra ) {
				if ( !empty( $hooksExtra[ 'plugin' ] ) ) {
					$this->handlePluginUpgrade( $hooksExtra[ 'plugin' ] );
				}
			}
		);
		return $true;
	}

	private function handlePluginUpgrade( string $file ) {
		if ( isset( $this->pluginVersions[ $file ] ) ) {
			$vo = Services::WpPlugins()->getPluginAsVo( $file, true );
			if ( !empty( $vo ) ) {
				$this->fireAuditEvent( 'plugin_upgraded', [
					'plugin' => $vo->file,
					'from'   => $this->pluginVersions[ $file ],
					'to'     => $vo->Version,
				] );
				unset( $this->pluginVersions[ $file ] );
			}
		}
	}

	/**
	 * @snapshotDiff
	 */
	public function snapshotDiffForPlugins( DiffVO $diff ) {
		$WPP = Services::WpPlugins();
		foreach ( $diff->added as $added ) {
			$vo = $WPP->getPluginAsVo( $added[ 'uniq' ] );
			$this->fireAuditEvent( 'plugin_installed', [
				'plugin'  => $vo->file,
				'version' => \ltrim( $vo->Version, 'v' ),
				'name'    => $vo->Name,
			] );
		}

		foreach ( $diff->removed as $removed ) {
			$this->fireAuditEvent( 'plugin_uninstalled', [
				'plugin'  => $removed[ 'uniq' ],
				'version' => \ltrim( $removed[ 'version' ], 'v' ),
				'name'    => $removed[ 'name' ],
			] );
		}

		foreach ( $diff->changed as $changed ) {
			$old = $changed[ 'old' ];
			$new = $changed[ 'new' ];
			$vo = $WPP->getPluginAsVo( $old[ 'uniq' ], true );
			$pluginData = [
				'plugin'  => $vo->file,
				'version' => \ltrim( $vo->Version, 'v' ),
				'name'    => $vo->Name,
			];

			if ( !$old[ 'is_active' ] && $new[ 'is_active' ] ) {
				$this->fireAuditEvent( 'plugin_activated', $pluginData );
			}
			elseif ( $old[ 'is_active' ] && !$new[ 'is_active' ] ) {
				$this->fireAuditEvent( 'plugin_deactivated', $pluginData );
			}

			$versionCompare = \version_compare( $old[ 'version' ], $new[ 'version' ] );
			if ( $versionCompare === -1 ) {
				$this->fireAuditEvent( 'plugin_upgraded', [
					'plugin' => $vo->file,
					'from'   => $old[ 'version' ],
					'to'     => $new[ 'version' ],
				] );
			}
			elseif ( $versionCompare === 1 ) {
				$this->fireAuditEvent( 'plugin_downgraded', [
					'plugin' => $vo->file,
					'from'   => $old[ 'version' ],
					'to'     => $new[ 'version' ],
				] );
			}
		}
	}

	public function getReporter() :ZoneReportPlugins {
		return new ZoneReportPlugins();
	}

	public function getSnapper() :SnapPlugins {
		return new SnapPlugins();
	}
}