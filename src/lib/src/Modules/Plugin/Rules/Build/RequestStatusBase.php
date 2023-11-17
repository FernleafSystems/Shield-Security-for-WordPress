<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build;

abstract class RequestStatusBase extends \FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\BuildRuleCoreShieldBase {

	protected function getDescription() :string {
		return sprintf( '%s - %s', __( 'Request Status' ), $this->getName() );
	}
}