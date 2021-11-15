<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Services\Services;

class Plugins extends Base {

	protected function run() {
		add_action( 'deactivated_plugin', [ $this, 'auditDeactivatedPlugin' ] );
		add_action( 'activated_plugin', [ $this, 'auditActivatedPlugin' ] );
		add_action( 'wp_ajax_edit-theme-plugin-file', [ $this, 'auditEditedFile' ], -1 ); // they hook on 1
	}

	/**
	 * @param string $plugin
	 */
	public function auditActivatedPlugin( $plugin ) {
		if ( !empty( $plugin ) ) {
			$this->getCon()->fireEvent(
				'plugin_activated',
				[ 'audit_params' => [ 'plugin' => $plugin ] ]
			);
		}
	}

	/**
	 * @param string $plugin
	 */
	public function auditDeactivatedPlugin( $plugin ) {
		if ( !empty( $plugin ) ) {
			$this->getCon()->fireEvent(
				'plugin_deactivated',
				[ 'audit_params' => [ 'plugin' => $plugin ] ]
			);
		}
	}

	public function auditEditedFile() {
		$req = Services::Request();
		if ( !empty( $req->post( 'plugin' ) ) ) {
			$this->getCon()->fireEvent(
				'plugin_file_edited',
				[ 'audit_params' => [ 'file' => sanitize_text_field( $req->post( 'file' ) ) ] ]
			);
		}
	}
}