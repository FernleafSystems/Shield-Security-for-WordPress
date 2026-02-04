<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteLockdown;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property int    $activated_at
 * @property string $activated_by
 * @property int    $disabled_at
 * @property array  $exclusions
 * @property string $whitelist_me
 */
class SiteBlockdownCfg extends DynPropertiesClass {

	public function isLockdownActive() :bool {
		return $this->activated_at > 0 && $this->activated_at > $this->disabled_at;
	}
}