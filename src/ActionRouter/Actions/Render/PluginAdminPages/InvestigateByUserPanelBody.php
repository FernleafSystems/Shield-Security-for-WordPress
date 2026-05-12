<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

class InvestigateByUserPanelBody extends PageInvestigateByUser {

	public const SLUG = 'render_investigate_by_user_panel_body';
	public const TEMPLATE = '/wpadmin/components/investigate/user_body.twig';
}
