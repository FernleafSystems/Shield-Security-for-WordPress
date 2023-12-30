<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumConditions;

trait TypePhp {

	public function getType() :string {
		return EnumConditions::CONDITION_TYPE_PHP;
	}
}