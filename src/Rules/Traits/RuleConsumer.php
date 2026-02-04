<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Traits;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\RuleVO;

trait RuleConsumer {

	/**
	 * @var RuleVO
	 */
	protected $rule;

	/**
	 * @return $this
	 */
	public function setRule( RuleVO $rule ) {
		$this->rule = $rule;
		return $this;
	}
}