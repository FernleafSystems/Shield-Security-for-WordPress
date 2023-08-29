<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

class PrivacyPolicy extends BaseRender {

	use SecurityAdminNotRequired;

	public const SLUG = 'render_privacy_policy';
	public const TEMPLATE = '/snippets/privacy_policy.twig';

	protected function getRenderData() :array {
		$con = self::con();
		if ( $con->getModule_SecAdmin()->getWhiteLabelController()->isEnabled() ) {
			$name = $con->getHumanName();
			$href = $con->labels->PluginURI;
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