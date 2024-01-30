<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Comms;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

/**
 * @deprecated 19.1
 */
class ModCon extends BaseShield\ModCon {

	public const SLUG = 'comms';

	public function isModOptEnabled() :bool {
		return false;
	}
}