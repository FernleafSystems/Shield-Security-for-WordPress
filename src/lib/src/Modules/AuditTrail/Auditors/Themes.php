<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes\ZoneReportThemes;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\DiffVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Snapper\SnapThemes;
use FernleafSystems\Wordpress\Services\Services;

class Themes extends Base {

	/**
	 * @var array
	 */
	private $themeVersions;

	private $slugs;

	public function canSnapRealtime() :bool {
		return true;
	}

	protected function initAuditHooks() :void {
		$this->themeVersions = \array_map( function ( $theme ) {
			return $theme->get( 'Version' );
		}, Services::WpThemes()->getThemes() );
		$this->slugs = Services::WpThemes()->getInstalledStylesheets();

		add_action( 'upgrader_process_complete', [ $this, 'auditInstall' ], 10, 0 );
		add_action( 'switch_theme', [ $this, 'auditSwitchTheme' ] );
		add_action( 'wp_ajax_edit-theme-plugin-file', [ $this, 'auditEditedFile' ], -1 ); // they hook on 1
		add_action( 'deleted_theme', [ $this, 'auditUninstalled' ], 10, 2 );

		add_action( 'upgrader_process_complete', [ $this, 'auditUpgrades' ], 10, 2 );
		add_filter( 'upgrader_post_install', [ $this, 'auditUpgrade2' ], 10, 2 );
	}

	public function auditInstall() {
		$current = Services::WpThemes()->getInstalledStylesheets();
		foreach ( \array_diff( $current, $this->slugs ) as $newStylesheet ) {
			$vo = Services::WpThemes()->getThemeAsVo( $newStylesheet );
			if ( !empty( $vo ) ) {
				$this->fireAuditEvent( 'theme_installed', [
					'theme'   => $vo->stylesheet,
					'version' => \ltrim( $vo->Version, 'v' ),
					'name'    => $vo->Name,
				] );
			}
		}
		$this->slugs = $current;
	}

	/**
	 * @param string $themeName
	 */
	public function auditSwitchTheme( $themeName ) {
		if ( !empty( $themeName ) ) {
			$theme = Services::WpThemes()->getCurrent();
			if ( $theme instanceof \WP_Theme ) {
				$vo = Services::WpThemes()->getThemeAsVo( $theme->get_stylesheet() );
				if ( !empty( $vo ) ) {
					$this->fireAuditEvent( 'theme_activated', [
						'theme'   => $vo->stylesheet,
						'version' => $vo->Version,
						'name'    => $vo->Name,
					] );
				}
			}
		}
	}

	/**
	 * @param string $stylesheet
	 */
	public function auditUninstalled( $stylesheet, $wasDeleted ) {
		if ( $wasDeleted && !empty( $stylesheet ) ) {
			$vo = Services::WpThemes()->getThemeAsVo( $stylesheet );
			if ( !empty( $vo ) ) {
				$this->fireAuditEvent( 'theme_uninstalled', [
					'theme'   => $vo->stylesheet,
					'version' => $vo->Version,
					'name'    => $vo->Name,
				] );
			}
		}
	}

	/**
	 * @see \wp_edit_theme_plugin_file()
	 */
	public function auditEditedFile() {
		$req = Services::Request();

		$theme = (string)$req->post( 'theme' );
		if ( $req->isPost()
			 && !empty( $theme )
			 && Services::WpThemes()->isInstalled( $theme )
			 && current_user_can( 'edit_themes' )
			 && wp_verify_nonce( $req->post( 'nonce' ), 'edit-theme_'.$theme.'_'.$req->post( 'file' ) )
		) {
			self::con()->fireEvent(
				'theme_file_edited',
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
			 && ( $data[ 'type' ] ?? null ) === 'theme'
			 && !empty( $data[ 'themes' ] )
			 && \is_array( $data[ 'themes' ] )
		) {
			foreach ( $data[ 'themes' ] as $item ) {
				$this->handleThemeUpgrades( $item );
			}
		}
	}

	public function auditUpgrade2( $true, $hooksExtra ) {
		add_action( self::con()->prefix( 'pre_plugin_shutdown' ),
			function () use ( $hooksExtra ) {
				if ( !empty( $hooksExtra[ 'theme' ] ) ) {
					$this->handleThemeUpgrades( $hooksExtra[ 'theme' ] );
				}
			}
		);
		return $true;
	}

	/**
	 * uses "isset()" to prevent duplicates.
	 */
	private function handleThemeUpgrades( string $item ) {
		if ( isset( $this->themeVersions[ $item ] ) ) {
			$vo = Services::WpThemes()->getThemeAsVo( $item, true );
			if ( !empty( $vo ) ) {
				$this->fireAuditEvent( 'theme_upgraded', [
					'theme' => $vo->stylesheet,
					'from'  => $this->themeVersions[ $item ],
					'to'    => $vo->Version,
				] );
				unset( $this->themeVersions[ $item ] );
			}
		}
	}

	/**
	 * @snapshotDiff
	 */
	public function snapshotDiffForThemes( DiffVO $diff ) {
		$WPP = Services::WpThemes();
		foreach ( $diff->added as $added ) {
			$vo = $WPP->getThemeAsVo( $added[ 'uniq' ] );
			$this->fireAuditEvent( 'theme_installed', [
				'theme'   => $vo->stylesheet,
				'version' => \ltrim( $vo->Version, 'v' ),
				'name'    => $vo->Name,
			] );
		}

		foreach ( $diff->removed as $removed ) {
			$this->fireAuditEvent( 'theme_uninstalled', [
				'plugin'  => $removed[ 'uniq' ],
				'version' => \ltrim( $removed[ 'version' ], 'v' ),
				'name'    => $removed[ 'name' ],
			] );
		}

		foreach ( $diff->changed as $changed ) {
			$old = $changed[ 'old' ];
			$new = $changed[ 'new' ];
			$vo = $WPP->getThemeAsVo( $old[ 'uniq' ], true );
			$pluginData = [
				'plugin'  => $vo->stylesheet,
				'version' => \ltrim( $vo->Version, 'v' ),
				'name'    => $vo->Name,
			];

			if ( !$old[ 'is_active' ] && $new[ 'is_active' ] ) {
				$this->fireAuditEvent( 'theme_activated', $pluginData );
			}

			$versionCompare = \version_compare( $old[ 'version' ], $new[ 'version' ] );
			if ( $versionCompare === -1 ) {
				$this->fireAuditEvent( 'theme_upgraded', [
					'theme' => $vo->stylesheet,
					'from'  => $old[ 'version' ],
					'to'    => $new[ 'version' ],
				] );
			}
			elseif ( $versionCompare === 1 ) {
				$this->fireAuditEvent( 'theme_downgraded', [
					'theme' => $vo->stylesheet,
					'from'  => $old[ 'version' ],
					'to'    => $new[ 'version' ],
				] );
			}
		}
	}

	public function getReporter() :ZoneReportThemes {
		return new ZoneReportThemes();
	}

	public function getSnapper() :SnapThemes {
		return new SnapThemes();
	}
}