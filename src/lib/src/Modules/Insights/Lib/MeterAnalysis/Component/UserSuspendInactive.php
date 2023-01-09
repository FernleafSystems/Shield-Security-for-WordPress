<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

class UserSuspendInactive extends Base {

	public const SLUG = 'user_suspend_inactive';
	public const WEIGHT = 20;

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_UserManagement();
		return $mod->isModOptEnabled() && $mod->getOptions()->getOpt( 'auto_idle_days' ) > 0;
	}

	public function href() :string {
		$mod = $this->getCon()->getModule_UserManagement();
		return $mod->isModOptEnabled() ? $this->link( 'auto_idle_days' ) : $this->link( 'enable_user_management' );
	}

	public function title() :string {
		return __( 'Inactive User Accounts', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return sprintf( __( 'Inactive user accounts are automatically suspended after %s.', 'wp-simple-firewall' ),
			$this->getCon()->getModule_UserManagement()->getOptions()->getOpt( 'auto_idle_days' ) );
	}

	public function descUnprotected() :string {
		return __( 'There is currently no control over how inactive user accounts are handled.', 'wp-simple-firewall' );
	}
}