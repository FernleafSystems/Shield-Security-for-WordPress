<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Services\Services;

class Themes extends Base {

	private $slugs;

	protected function run() {
		$this->slugs = Services::WpThemes()->getInstalledStylesheets();
		add_action( 'upgrader_process_complete', [ $this, 'auditInstall' ], 10, 0 );
		add_action( 'switch_theme', [ $this, 'auditSwitchTheme' ] );
		add_action( 'wp_ajax_edit-theme-plugin-file', [ $this, 'auditEditedFile' ], -1 ); // they hook on 1
	}

	public function auditInstall() {
		$current = Services::WpThemes()->getInstalledStylesheets();
		foreach ( array_diff( $current, $this->slugs ) as $new ) {
			$this->getCon()->fireEvent(
				'theme_installed',
				[ 'audit_params' => [ 'theme' => $new ] ]
			);
		}
		$this->slugs = $current;
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
			$this->getCon()->fireEvent(
				'theme_file_edited',
				[ 'audit_params' => [ 'file' => sanitize_text_field( $req->post( 'file' ) ) ] ]
			);
		}
	}
}