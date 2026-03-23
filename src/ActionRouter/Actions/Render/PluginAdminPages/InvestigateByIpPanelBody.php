<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

class InvestigateByIpPanelBody extends PageInvestigateByIp {

	public const SLUG = 'render_investigate_by_ip_panel_body';
	public const TEMPLATE = '/wpadmin/components/investigate/ip_body.twig';
}
