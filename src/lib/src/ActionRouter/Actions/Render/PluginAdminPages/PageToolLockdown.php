<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteLockdown\SiteBlockdownCfg;
use FernleafSystems\Wordpress\Services\Services;

class PageToolLockdown extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_tools_lockdown';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/tool_lockdown.twig';

	protected function getPageContextualHrefs_Help() :array {
		return [
			'title'      => sprintf( '%s: %s', __( 'Help', 'wp-simple-firewall' ), __( 'Site Lockdown', 'wp-simple-firewall' ) ),
			'href'       => 'https://help.getshieldsecurity.com/article/769-what-is-the-site-lockdown-feature-and-how-to-use-it',
			'new_window' => true,
		];
	}

	protected function getRenderData() :array {
		$con = self::con();
		$cfg = ( new SiteBlockdownCfg() )->applyFromArray( $con->comps->opts_lookup->getBlockdownCfg() );
		return [
			'imgs'    => [
				'inner_page_title_icon' => self::con()->svgs->raw( 'sign-stop-fill' ),
			],
			'strings' => [
				'inner_page_title'    => __( 'Site Lockdown', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'Block all access to the site except from IPs on the bypass/white list.', 'wp-simple-firewall' ),
			],
			'flags'   => [
				'blockdown_active'       => $cfg->isLockdownActive(),
				'can_blockdown'          => $con->caps->canSiteBlockdown(),
				'is_your_ip_whitelisted' => ( new IpRuleStatus( $con->this_req->ip ) )->isBypass(),
			],
			'vars'    => [
				'your_ip'      => $con->this_req->ip,
				'active_since' => Services::Request()
										  ->carbon()
										  ->setTimestamp( $cfg->activated_at )
										  ->diffForHumans(),
				'active_by'    => $cfg->activated_by,
			],
		];
	}
}