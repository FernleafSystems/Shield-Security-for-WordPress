<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\Core;

abstract class RequestStatusBase extends BuildRuleCoreShieldBase {

	protected function getDescription() :string {
		return sprintf( '%s - %s', __( 'Request Status' ), $this->getName() );
	}
}