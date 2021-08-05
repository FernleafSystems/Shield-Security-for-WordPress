<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Services\Services;

class Wordpress extends Base {

	protected function run() {
		add_action( '_core_updated_successfully', [ $this, 'auditCoreUpdated' ] );
		add_action( 'update_option_permalink_structure', [ $this, 'auditPermalinkStructure' ], 10, 2 );
	}

	/**
	 * @param string $newVersion
	 */
	public function auditCoreUpdated( $newVersion ) {
		$this->getCon()->fireEvent(
			'core_updated',
			[
				'audit' => [
					'from' => Services::WpGeneral()->getVersion(),
					'to'   => $newVersion,
				]
			]
		);
	}

	/**
	 * @param string $old
	 * @param string $new
	 */
	public function auditPermalinkStructure( $old, $new ) {
		$this->getCon()->fireEvent(
			'permalinks_structure',
			[
				'audit' => [
					'from' => $old,
					'to'   => $new,
				]
			]
		);
	}
}