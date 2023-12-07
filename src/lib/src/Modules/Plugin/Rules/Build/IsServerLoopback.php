<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions,
	Constants
};
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 18.5.8
 */
class IsServerLoopback extends BuildRuleCoreShieldBase {

	public const SLUG = 'shield/is_server_loopback';

	protected function getName() :string {
		return 'Is Server Loopback';
	}

	protected function getDescription() :string {
		return 'Is Server Loopback request.';
	}

	protected function getConditions() :array {
		return [];
	}
}