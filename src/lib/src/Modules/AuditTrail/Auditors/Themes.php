<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Services\Services;

class Themes extends Base {

	protected function run() {
		add_action( 'switch_theme', [ $this, 'auditSwitchTheme' ] );
		add_action( 'wp_ajax_edit-theme-plugin-file', [ $this, 'auditEditedFile' ], -1 ); // they hook on 1
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

	public function auditEditedFile() {
		$req = Services::Request();
		if ( !empty( $req->post( 'theme' ) ) ) {
			$this->getCon()->fireEvent(
				'theme_file_edited',
				[ 'audit_params' => [ 'file' => sanitize_text_field( $req->post( 'file' ) ) ] ]
			);
		}
	}
}