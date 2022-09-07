<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\RuleTraits;

trait InstantExec {

	protected function isInstantExecResponse() :bool {
		return true;
	}
}