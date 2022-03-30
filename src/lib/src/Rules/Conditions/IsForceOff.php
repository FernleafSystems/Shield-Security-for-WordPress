<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestIP;
use FernleafSystems\Wordpress\Services\Services;

class IsForceOff extends Base {

	use RequestIP;

	const SLUG = 'is_force_off';

	protected function execConditionCheck() :bool {
		$con = $this->getCon();
		if ( is_null( $con->this_req->is_force_off ) ) {
			$con->this_req->is_force_off = !is_null( $con->file_forceoff ) || ( $this->getForceOffFilePath() !== false );
		}
		return $con->this_req->is_force_off;
	}

	/**
	 * @return false|string
	 */
	private function getForceOffFilePath() {
		$con = $this->getCon();
		if ( !isset( $con->file_forceoff ) ) {
			$FS = Services::WpFs();
			$file = $FS->findFileInDir( 'forceoff', $con->getRootDir(), false, false );
			$con->file_forceoff = empty( $file ) ? false : $file;
		}
		return $con->file_forceoff;
	}
}