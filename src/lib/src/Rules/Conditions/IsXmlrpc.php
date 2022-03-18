<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class IsXmlrpc extends Base {

	const CONDITION_SLUG = 'is_xmlrpc';

	protected function execConditionCheck() :bool {
		$pathMatch = ( new MatchRequestPath() )->setCon( $this->getCon() );
		$pathMatch->request_path = Services::Request()->getPath();
		$pathMatch->match_paths = [ '/xmlrpc\.php$' ];
		$pathMatch->is_match_regex = true;

		$detected = Services::WpGeneral()->isXmlrpc() || $pathMatch->run();
		$this->conditionTriggerMeta = $pathMatch->getTriggerMetaData();
		return $detected;
	}
}