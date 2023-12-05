<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class IsForceOff extends Base {

	use Traits\RequestIP;

	public const SLUG = 'is_force_off';

	public function getName() :string {
		return __( 'Is the Shield plugin in "forceoff" state.', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		return $this->findForceOffFile() !== false;
	}

	protected function getPreviousResult() :?bool {
		return self::con()->this_req->is_force_off;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		self::con()->this_req->is_force_off = $result;
	}

	/**
	 * @return false|string
	 */
	private function findForceOffFile() {
		$con = self::con();
		if ( !isset( $con->file_forceoff ) ) {
			$file = Services::WpFs()->findFileInDir( 'forceoff', $con->getRootDir(), false );
			$con->file_forceoff = empty( $file ) ? false : $file;
		}
		return $con->file_forceoff;
	}
}