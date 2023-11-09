<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class ModCon extends BaseShield\ModCon {

	public const SLUG = 'user_management';

	private $userSuspendCon;

	public function getUserSuspendCon() :Lib\Suspend\UserSuspendController {
		return $this->userSuspendCon ?? $this->userSuspendCon = new Lib\Suspend\UserSuspendController();
	}

	/**
	 * @deprecated 18.5
	 */
	public function preProcessOptions() {
	}
}