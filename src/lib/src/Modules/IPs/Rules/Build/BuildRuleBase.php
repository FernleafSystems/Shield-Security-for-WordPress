<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules;

class BuildRuleBase {

	use Shield\Modules\ModConsumer;

	const LOGIC_AND = 'AND';
	const LOGIC_OR = 'OR';
}