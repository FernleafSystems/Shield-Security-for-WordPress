<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class HookAddAction extends HookAddFilter {

	public const SLUG = 'hook_add_action';

	public function execResponse() :void {
		add_action(
			$this->p->hook,
			$this->p->callback,
			$this->p->priority,
			$this->p->args
		);
	}
}