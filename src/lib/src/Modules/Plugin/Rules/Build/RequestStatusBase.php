<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\BuildRuleCoreShieldBase;

abstract class RequestStatusBase extends BuildRuleCoreShieldBase {

	protected function getDescription() :string {
		return sprintf( '%s - %s', __( 'Request Status' ), $this->getName() );
	}
}