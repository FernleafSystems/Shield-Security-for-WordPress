<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

class Plugins extends Base {

	public function run() {
		add_action( 'deactivated_plugin', [ $this, 'auditDeactivatedPlugin' ] );
		add_action( 'activated_plugin', [ $this, 'auditActivatedPlugin' ] );
		add_action( 'check_admin_referer', [ $this, 'auditEditedFile' ], 10, 2 );
	}

	/**
	 * @param string $sPlugin
	 */
	public function auditActivatedPlugin( $sPlugin ) {
		if ( !empty( $sPlugin ) ) {
			$this->getCon()->fireEvent(
				'plugin_activated',
				[ 'audit' => [ 'plugin' => $sPlugin ] ]
			);
		}
	}

	/**
	 * @param string $sPlugin
	 */
	public function auditDeactivatedPlugin( $sPlugin ) {
		if ( !empty( $sPlugin ) ) {
			$this->getCon()->fireEvent(
				'plugin_deactivated',
				[ 'audit' => [ 'plugin' => $sPlugin ] ]
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