<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

class Themes extends Base {

	protected function run() {
		add_action( 'switch_theme', [ $this, 'auditSwitchTheme' ] );
		add_action( 'check_admin_referer', [ $this, 'auditEditedThemeFile' ] );
	}

	/**
	 * @param string $themeName
	 */
	public function auditSwitchTheme( $themeName ) {
		if ( !empty( $themeName ) ) {
			$this->getCon()->fireEvent(
				'theme_activated',
				[ 'audit_params' => [ 'theme' => $themeName ] ]
			);
		}
	}

	/**
	 * @param string $action
	 */
	public function auditEditedThemeFile( $action ) {
		$stub = 'edit-theme_';
		if ( strpos( $action, $stub ) === 0 ) {
			$this->getCon()->fireEvent(
				'theme_file_edited',
				[ 'audit_params' => [ 'file' => str_replace( $stub, '', $action ) ] ]
			);
		}
	}
}