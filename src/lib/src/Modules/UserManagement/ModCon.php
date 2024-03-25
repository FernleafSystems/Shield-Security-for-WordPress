<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon {

	public const SLUG = 'user_management';

	private $userSuspendCon;

	/**
	 * @deprecated 19.1
	 */
	public function getUserSuspendCon() :Lib\Suspend\UserSuspendController {
		return self::con()->comps !== null ? self::con()->comps->user_suspend :
			( $this->userSuspendCon ?? $this->userSuspendCon = new Lib\Suspend\UserSuspendController() );
	}
}