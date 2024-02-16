<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon {

	public const SLUG = 'user_management';

	private $userSuspendCon;

	public function getUserSuspendCon() :Lib\Suspend\UserSuspendController {
		return $this->userSuspendCon ?? $this->userSuspendCon = new Lib\Suspend\UserSuspendController();
	}
}