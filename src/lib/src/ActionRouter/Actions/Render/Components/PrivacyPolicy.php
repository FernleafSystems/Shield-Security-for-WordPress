<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;

class PrivacyPolicy extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	use SecurityAdminNotRequired;

	public const SLUG = 'render_privacy_policy';
	public const TEMPLATE = '/snippets/privacy_policy.twig';

	protected function getRenderData() :array {
		$con = self::con();
		if ( $con->comps->whitelabel->isEnabled() ) {
			$name = $con->getHumanName();
			$href = $con->labels->PluginURI;
		}
		else {
			$name = $con->cfg->menu[ 'title' ];
			$href = $con->cfg->meta[ 'privacy_policy_href' ];
		}
		return [
			'name'             => $name,
			'href'             => $href,
			'audit_trail_days' => $con->comps->activity_log->getAutoCleanDays()
		];
	}
}