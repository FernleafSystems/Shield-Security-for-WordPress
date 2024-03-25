<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

abstract class ShieldUser2faBase extends Base {

	use Traits\TypeShield;

	protected function getUserFromSession() :?\WP_User {
		return Services::WpUsers()->getUserById( $this->req->session->shield[ 'user_id' ] );
	}

	protected function get2faProviderForParamDef() :array {
		$providers = [];
		$mfa = self::con()->comps === null ? self::con()->getModule_LoginGuard()->getMfaController()
			: self::con()->comps->mfa;
		foreach ( $mfa->collateMfaProviderClasses() as $provider ) {
			$providers[ $provider::ProviderSlug() ] = $provider::ProviderName();
		}
		return $providers;
	}
}