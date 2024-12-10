<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;

class PrivacyPolicy extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	use SecurityAdminNotRequired;

	public const SLUG = 'render_privacy_policy';
	public const TEMPLATE = '/snippets/privacy_policy.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$white = $con->comps->whitelabel->isEnabled();
		return [
			'name'             => $white ? $con->labels->Name : $con->cfg->menu[ 'title' ],
			'href'             => $white ? $con->labels->PluginURI : $con->cfg->meta[ 'privacy_policy_href' ],
			'audit_trail_days' => $con->comps->activity_log->getAutoCleanDays()
		];
	}
}