<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class HookAddAction extends HookAddFilter {

	public const SLUG = 'hook_add_action';

	public function execResponse() :void {
		add_action(
			$this->params[ 'hook' ],
			$this->params[ 'callback' ],
			$this->params[ 'priority' ],
			$this->params[ 'args' ]
		);
	}
}