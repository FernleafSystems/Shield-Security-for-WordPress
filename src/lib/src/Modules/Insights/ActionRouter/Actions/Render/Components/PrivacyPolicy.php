<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Traits\SecurityAdminNotRequired;

class PrivacyPolicy extends BasePlugin {

	use SecurityAdminNotRequired;

	public const SLUG = 'render_privacy_policy';
	public const TEMPLATE = '/snippets/privacy_policy.twig';

	protected function getRenderData() :array {
		$con = $this->getCon();
		if ( $con->getModule_SecAdmin()->getWhiteLabelController()->isEnabled() ) {
			$name = $con->getHumanName();
			$href = $this->labels->PluginURI;
		}
		else {
			$name = $con->cfg->menu[ 'title' ];
			$href = $con->cfg->meta[ 'privacy_policy_href' ];
		}

		/** @var AuditTrail\Options $optsAT */
		$optsAT = $con->getModule_AuditTrail()->getOptions();

		return [
			'name'             => $name,
			'href'             => $href,
			'audit_trail_days' => $optsAT->getAutoCleanDays()
		];
	}
}