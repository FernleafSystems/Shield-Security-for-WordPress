<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

class Plugins extends Base {

	protected function run() {
		add_action( 'deactivated_plugin', [ $this, 'auditDeactivatedPlugin' ] );
		add_action( 'activated_plugin', [ $this, 'auditActivatedPlugin' ] );
		add_action( 'check_admin_referer', [ $this, 'auditEditedFile' ], 10, 2 );
	}

	/**
	 * @param string $plugin
	 */
	public function auditActivatedPlugin( $plugin ) {
		if ( !empty( $plugin ) ) {
			$this->getCon()->fireEvent(
				'plugin_activated',
				[ 'audit' => [ 'plugin' => $plugin ] ]
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
				[ 'audit' => [ 'plugin' => $plugin ] ]
			);
		}
	}

	/**
	 * @param string $sAction
	 * @param bool   $bResult
	 */
	public function auditEditedFile( $sAction, $bResult ) {
		$sStub = 'edit-plugin_';
		if ( strpos( $sAction, $sStub ) === 0 ) {
			$this->getCon()->fireEvent(
				'plugin_file_edited',
				[ 'audit' => [ 'file' => str_replace( $sStub, '', $sAction ) ] ]
			);
		}
	}
}