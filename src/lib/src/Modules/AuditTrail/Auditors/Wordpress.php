<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Services\Services;

class Wordpress extends Base {

	public function run() {
		add_action( '_core_updated_successfully', [ $this, 'auditCoreUpdated' ] );
		add_action( 'update_option_permalink_structure', [ $this, 'auditPermalinkStructure' ], 10, 2 );
	}

	/**
	 * @param string $sNewCoreVersion
	 */
	public function auditCoreUpdated( $sNewCoreVersion ) {
		$this->getCon()->fireEvent(
			'core_updated',
			[
				'old' => Services::WpGeneral()->getVersion(),
				'new' => $sNewCoreVersion,
			]
		);
	}

	/**
	 * @param string $sOld
	 * @param string $sNew
	 */
	public function auditPermalinkStructure( $sOld, $sNew ) {
		$this->getCon()->fireEvent(
			'permalinks_structure',
			[
				'old' => $sOld,
				'new' => $sNew,
			]
		);
	}
}