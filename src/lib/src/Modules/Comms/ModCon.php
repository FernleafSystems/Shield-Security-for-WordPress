<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Comms;

/**
 * @deprecated 19.0
 */
class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\ModCon {

	public const SLUG = 'comms';

	public function isModOptEnabled() :bool {
		return false;
	}
}