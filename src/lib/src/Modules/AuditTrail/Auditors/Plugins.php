<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Services\Services;

class Plugins extends Base {

	private $slugs;

	protected function run() {
		$this->slugs = Services::WpPlugins()->getInstalledPluginFiles();
		add_action( 'upgrader_process_complete', [ $this, 'auditInstall' ], 10, 0 );
		add_action( 'deactivated_plugin', [ $this, 'auditDeactivatedPlugin' ] );
		add_action( 'activated_plugin', [ $this, 'auditActivatedPlugin' ] );
		add_action( 'wp_ajax_edit-theme-plugin-file', [ $this, 'auditEditedFile' ], -1 ); // they hook on 1
	}

	public function auditInstall() {
		$current = Services::WpPlugins()->getInstalledPluginFiles();
		foreach ( array_diff( $current, $this->slugs ) as $new ) {
			$this->getCon()->fireEvent(
				'plugin_installed',
				[ 'audit_params' => [ 'plugin' => $new ] ]
			);
		}
		$this->slugs = $current;
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
			$this->getCon()->fireEvent(
				'plugin_file_edited',
				[ 'audit_params' => [ 'file' => sanitize_text_field( $req->post( 'file' ) ) ] ]
			);
		}
	}
}