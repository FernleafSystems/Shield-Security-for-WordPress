<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\Options;

class IsXmlrpcDisabled extends Base {

	const SLUG = 'is_xmlrpc_disabled';

	protected function execConditionCheck() :bool {
		/** @var Options $opts */
		$opts = $this->getCon()->getModule_Lockdown()->getOptions();
		return $opts->isXmlrpcDisabled();
	}
}