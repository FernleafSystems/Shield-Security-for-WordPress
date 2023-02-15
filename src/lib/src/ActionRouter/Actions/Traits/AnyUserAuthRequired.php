<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits;

trait AnyUserAuthRequired {

	protected function getMinimumUserAuthCapability() :string {
		return 'read';
	}
}