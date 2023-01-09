<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Options;

class IpAutoBlock extends IpBase {

	public const SLUG = 'ip_autoblock';
	public const WEIGHT = 50;

	protected function isProtected() :bool {
		/** @var Options $opts */
		$opts = $this->getCon()->getModule_IPs()->getOptions();
		return parent::isProtected() && $opts->isEnabledAutoBlackList();
	}

	public function href() :string {
		return $this->getCon()->getModule_IPs()->isModOptEnabled() ?
			$this->link( 'transgression_limit' ) : parent::href();
	}

	public function title() :string {
		return __( 'IP Auto-Block', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		$mod = $this->getCon()->getModule_IPs();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return sprintf( __( 'Auto IP blocking is turned on with an offense limit of %s.', 'wp-simple-firewall' ),
			$opts->getOffenseLimit() );
	}

	public function descUnprotected() :string {
		return __( 'Auto IP blocking is switched-off as there is no offense limit provided.', 'wp-simple-firewall' );
	}
}