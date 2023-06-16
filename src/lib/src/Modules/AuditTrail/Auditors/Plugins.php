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
		add_action( 'pre_uninstall_plugin', [ $this, 'auditUninstalled' ] );
	}

	public function auditInstall() {
		$current = Services::WpPlugins()->getInstalledPluginFiles();
		foreach ( \array_diff( $current, $this->slugs ) as $new ) {
			$vo = Services::WpPlugins()->getPluginAsVo( $new );
			if ( !empty( $vo ) ) {
				$this->con()->fireEvent(
					'plugin_installed',
					[
						'audit_params' => [
							'plugin'  => $new,
							'version' => \ltrim( $vo->Version, 'v' ),
							'name'    => $vo->Name,
						]
					]
				);
			}
		}
		$this->slugs = $current;
	}

	/**
	 * @param string $plugin
	 */
	public function auditActivatedPlugin( $plugin ) {
		if ( !empty( $plugin ) ) {
			$vo = Services::WpPlugins()->getPluginAsVo( $plugin );
			if ( !empty( $vo ) ) {
				$this->con()->fireEvent(
					'plugin_activated',
					[
						'audit_params' => [
							'plugin'  => $plugin,
							'version' => \ltrim( $vo->Version, 'v' ),
							'name'    => $vo->Name,
						]
					]
				);
			}
		}
	}

	public function auditUninstalled( $plugin ) {
		$vo = Services::WpPlugins()->getPluginAsVo( $plugin );
		if ( !empty( $vo ) ) {
			$this->con()->fireEvent(
				'plugin_uninstalled',
				[
					'audit_params' => [
						'plugin'  => $plugin,
						'version' => \ltrim( $vo->Version, 'v' ),
						'name'    => $vo->Name,
					]
				]
			);
		}
	}

	/**
	 * @param string $plugin
	 */
	public function auditDeactivatedPlugin( $plugin ) {
		if ( !empty( $plugin ) ) {
			$vo = Services::WpPlugins()->getPluginAsVo( $plugin );
			if ( !empty( $vo ) ) {
				$this->con()->fireEvent(
					'plugin_deactivated',
					[
						'audit_params' => [
							'plugin'  => $plugin,
							'version' => \ltrim( $vo->Version, 'v' ),
							'name'    => $vo->Name,
						]
					]
				);
			}
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
			$this->con()->fireEvent(
				'plugin_file_edited',
				[ 'audit_params' => [ 'file' => sanitize_text_field( $req->post( 'file' ) ) ] ]
			);
		}
	}
}