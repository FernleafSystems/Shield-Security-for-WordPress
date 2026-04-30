<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

abstract class ShieldUser2faBase extends Base {

	use Traits\TypeShield;

	protected function getUserFromSession() :?\WP_User {
		if ( !isset( $this->req->session ) ) {
			$this->req->session = self::con()->comps->session->current();
		}
		return $this->req->session->valid ?
			Services::WpUsers()->getUserById( $this->req->session->shield[ 'user_id' ] ?? 0 )
			: null;
	}

	protected function get2faProviderForParamDef() :array {
		$providers = [];
		foreach ( self::con()->comps->mfa->collateMfaProviderClasses() as $provider ) {
			$providers[ $provider::ProviderSlug() ] = $provider::ProviderName();
		}
		return $providers;
	}
}
