<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan\Checks;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class Base {

	use ModConsumer;

	protected $check;

	/**
	 * @param string $check
	 * @return $this
	 */
	public function setCheck( string $check ) {
		$this->check = $check;
		return $this;
	}

	protected function getFirewallPatterns() :array {
		return $this->getOptions()->getDef( 'firewall_patterns' )[ $this->check ] ?? [];
	}

	protected function getFirewallPatterns_Regex() :array {
		return array_map(
			function ( $regex ) {
				return '/'.$regex.'/i';
			},
			$this->getFirewallPatterns()[ 'regex' ] ?? []
		);
	}

	protected function getFirewallPatterns_Simple() :array {
		return $this->getFirewallPatterns()[ 'simple' ] ?? [];
	}
}