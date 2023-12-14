<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumRules;

trait TypeRequest {

	public function getType() :string {
		return EnumRules::CONDITION_TYPE_REQUEST;
	}
}