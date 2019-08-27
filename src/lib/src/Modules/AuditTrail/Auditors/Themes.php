<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

class Themes extends Base {

	public function run() {
		add_action( 'switch_theme', [ $this, 'auditSwitchTheme' ] );
		add_action( 'check_admin_referer', [ $this, 'auditEditedThemeFile' ], 10, 2 );
	}

	/**
	 * @param string $sThemeName
	 */
	public function auditSwitchTheme( $sThemeName ) {
		if ( !empty( $sThemeName ) ) {
			$this->getCon()->fireEvent(
				'theme_activated',
				[ 'audit' => [ 'theme' => $sThemeName ] ]
			);
		}
	}

	/**
	 * @param string $sAction
	 * @param bool   $bResult
	 */
	public function auditEditedThemeFile( $sAction, $bResult ) {
		$sStub = 'edit-theme_';
		if ( strpos( $sAction, $sStub ) === 0 ) {
			$this->getCon()->fireEvent(
				'theme_file_edited',
				[ 'audit' => [ 'file' => str_replace( $sStub, '', $sAction ) ] ]
			);
		}
	}
}