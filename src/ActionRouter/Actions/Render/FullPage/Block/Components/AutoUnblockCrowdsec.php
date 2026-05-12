<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\IpAutoUnblockCrowdsecVisitor;

class AutoUnblockCrowdsec extends AutoUnblockShield {

	public const SLUG = 'render_autounblock_crowdsec';

	protected function isAutoUnblockAvailable() :bool {
		return self::con()->comps->opts_lookup->enabledCrowdSecAutoUnblock();
	}

	/**
	 * @return class-string
	 */
	protected function getAutoUnblockActionClass() :string {
		return IpAutoUnblockCrowdsecVisitor::class;
	}
}
