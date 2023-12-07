<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\RulesEnum;

trait TypeUser {

	public function getType() :string {
		return RulesEnum::TYPE_USER;
	}
}