<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteLockdown\SiteBlockdownCfg;
use FernleafSystems\Wordpress\Services\Services;

class PageToolLockdown extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_tools_lockdown';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/tool_lockdown.twig';

	protected function getPageContextualHrefs_Help() :array {
		return [
			'title'      => sprintf( '%s: %s', CommonDisplayStrings::get( 'help_label' ), __( 'Site Lockdown', 'wp-simple-firewall' ) ),
			'href'       => 'https://help.getshieldsecurity.com/article/769-what-is-the-site-lockdown-feature-and-how-to-use-it',
			'new_window' => true,
		];
	}

	protected function getRenderData() :array {
		$con = self::con();
		$cfg = ( new SiteBlockdownCfg() )->applyFromArray( $con->comps->opts_lookup->getBlockdownCfg() );
		$yourIp = $con->this_req->ip;
		$moreHelpUrl = 'https://clk.shldscrty.com/lo';
		return [
			'imgs'    => [
				'inner_page_title_icon' => self::con()->svgs->raw( 'sign-stop-fill' ),
			],
			'strings' => [
				'inner_page_title'    => __( 'Site Lockdown', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'Block all access to the site except from IPs on the bypass/white list.', 'wp-simple-firewall' ),
				'lockdown_active_title'       => __( 'Site Is In Lockdown', 'wp-simple-firewall' ),
				'lockdown_active_description' => __( 'Your site is currently in lockdown.', 'wp-simple-firewall' ),
				'lockdown_started_label'      => __( 'Lockdown started:', 'wp-simple-firewall' ),
				'lockdown_activated_label'    => __( 'Lockdown activated by:', 'wp-simple-firewall' ),
				'button_disable'              => __( 'Disable Site Lockdown', 'wp-simple-firewall' ),
				'warning_title'               => __( 'Proceed With Extreme Caution', 'wp-simple-firewall' ),
				'warning_block_all'           => __( 'Switching-on this feature will block all traffic to your site. Absolutely everything will be blocked (including Google Bots).', 'wp-simple-firewall' ),
				'warning_whitelist_only'      => __( 'Only traffic originating from whitelisted IP addresses and the hosting server itself will be permitted.', 'wp-simple-firewall' ),
				'warning_ip_not_whitelisted'  => __( "Your IP address is not whitelisted, so you'll be locked-out if you enable this without whitelisting your IP.", 'wp-simple-firewall' ),
				'warning_note_security_admin' => __( 'Note: To prevent other admins from using this tool, consider switching on the Security Admin feature.', 'wp-simple-firewall' ),
				'warning_more_help_html'      => sprintf(
					__( 'Proceed with caution. [%s]', 'wp-simple-firewall' ),
					sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $moreHelpUrl ), __( 'further help', 'wp-simple-firewall' ) )
				),
				'whitelist_heading'          => __( 'Whitelist Me', 'wp-simple-firewall' ),
				'whitelist_label'            => __( 'Add my IP address (%s) to the bypass/whitelist', 'wp-simple-firewall' ),
				'whitelist_label_note'       => __( '(when lockdown is disabled, the IP address will automatically be removed from the whitelist)', 'wp-simple-firewall' ),
				'confirm_heading'            => __( 'Confirmation', 'wp-simple-firewall' ),
				'confirm_cache'              => __( "I know that if I use caching and I don't clear it afterward, it'll cause trouble for visitors.", 'wp-simple-firewall' ),
				'confirm_access'             => __( 'I know how to regain access if I become locked-out (e.g. my IP address changes).', 'wp-simple-firewall' ),
				'confirm_consequences'       => __( 'I understand the consequences of blocking all traffic to this site.', 'wp-simple-firewall' ),
				'confirm_authority'          => __( 'I have authority to block all traffic to this site.', 'wp-simple-firewall' ),
				'button_lockdown'            => __( 'Lockdown The Site', 'wp-simple-firewall' ),
				'button_upgrade'             => sprintf( __( 'Upgrade Your %s Membership', 'wp-simple-firewall' ), self::con()->labels->Name ),
			],
			'flags'   => [
				'blockdown_active'       => $cfg->isLockdownActive(),
				'can_blockdown'          => $con->caps->canSiteBlockdown(),
				'is_your_ip_whitelisted' => ( new IpRuleStatus( $con->this_req->ip ) )->isBypass(),
			],
			'vars'    => [
				'your_ip'      => $yourIp,
				'active_since' => Services::Request()
										  ->carbon()
										  ->setTimestamp( $cfg->activated_at )
										  ->diffForHumans(),
				'active_by'    => $cfg->activated_by,
			],
		];
	}
}
