<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestIP;
use FernleafSystems\Wordpress\Services\Services;

class IsForceOff extends Base {

	use RequestIP;

	public const SLUG = 'is_force_off';

	protected function execConditionCheck() :bool {
		$con = self::con();
		return $con->this_req->is_force_off ?? $con->this_req->is_force_off = $this->findForceOffFile() !== false;
	}

	/**
	 * @return false|string
	 */
	private function findForceOffFile() {
		$con = self::con();
		if ( !isset( $con->file_forceoff ) ) {
			$FS = Services::WpFs();
			$file = $FS->findFileInDir( 'forceoff', $con->getRootDir(), false );
			$con->file_forceoff = empty( $file ) ? false : $file;
		}
		return $con->file_forceoff;
	}
}