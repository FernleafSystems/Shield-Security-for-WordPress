<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\LoadIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteLockdown\SiteBlockdownCfg;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageToolLockdown extends PageModeLandingBase {

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
		$isYourIpBypassed = ( new IpRuleStatus( $yourIp ) )->isBypass();

		$bypassLoader = new LoadIpRules();
		$bypassLoader->wheres = [
			sprintf( "`ir`.`type`='%s'", $con->db_con->ip_rules::T_MANUAL_BYPASS )
		];
		// The bypass list apart from your own address, which has a row of its own.
		$othersBypassed = \max( 0, $bypassLoader->countAll() - ( $isYourIpBypassed ? 1 : 0 ) );

		return \array_replace_recursive( parent::getRenderData(), [
			'strings' => [
				'lede'                => __( 'Shut the whole site to everyone except the addresses you allow, while you deal with an attack. Turn it off as soon as the emergency is over.', 'wp-simple-firewall' ),
				'lede_active'         => __( "While it's on, only the addresses below can reach the site.", 'wp-simple-firewall' ),
				'status_off_title'    => __( 'Lockdown is off', 'wp-simple-firewall' ),
				'status_off_desc'     => __( 'Your site is open to everyone as normal.', 'wp-simple-firewall' ),
				'status_off_pill'     => __( 'Off', 'wp-simple-firewall' ),
				'status_on_title'     => __( 'Your site is locked down', 'wp-simple-firewall' ),
				'status_on_desc'      => __( 'Everyone except the addresses below is blocked, including search engines. Turn it off as soon as you can.', 'wp-simple-firewall' ),
				'button_disable'      => __( 'Turn off lockdown', 'wp-simple-firewall' ),
				'started_label'       => __( 'Started', 'wp-simple-firewall' ),
				'activated_by_label'  => __( 'Turned on by', 'wp-simple-firewall' ),
				'your_address_label'  => __( 'Your address', 'wp-simple-firewall' ),
				'allowed'             => __( 'allowed', 'wp-simple-firewall' ),
				'not_allowed'         => __( 'not on the bypass list', 'wp-simple-firewall' ),
				'who_heading'         => __( 'Who still gets in', 'wp-simple-firewall' ),
				/* translators: %s: an IP address */
				'you_at'              => __( 'You, at %s', 'wp-simple-firewall' ),
				'you_not_bypassed'    => __( "Not on the bypass list yet. Without it you'd be locked out too.", 'wp-simple-firewall' ),
				'you_bypassed'        => __( "You're on the bypass list, so you keep your access.", 'wp-simple-firewall' ),
				'you_added'           => __( 'Added for this lockdown. It comes off the bypass list again when lockdown ends.', 'wp-simple-firewall' ),
				'add_my_ip'           => __( 'Add my IP', 'wp-simple-firewall' ),
				'add_my_ip_label'     => __( 'Add my IP address to the bypass list for this lockdown', 'wp-simple-firewall' ),
				'host_title'          => __( 'Your hosting server', 'wp-simple-firewall' ),
				'host_desc'           => __( 'So scheduled tasks and backups keep running.', 'wp-simple-firewall' ),
				/* translators: %s: how many IP addresses */
				'bypass_title'        => _n( '%s other address on the bypass list', '%s other addresses on the bypass list', $othersBypassed, 'wp-simple-firewall' ),
				'bypass_none'         => __( 'No other addresses on the bypass list', 'wp-simple-firewall' ),
				'bypass_desc'         => __( "Anyone you've already added in IP Rules.", 'wp-simple-firewall' ),
				'bypass_review'       => __( 'Review list', 'wp-simple-firewall' ),
				'everyone_title'      => __( 'Everyone else is blocked', 'wp-simple-firewall' ),
				'everyone_desc'       => __( 'Visitors, bots and search engines, including Google.', 'wp-simple-firewall' ),
				'after_title'         => __( 'When you turn it off', 'wp-simple-firewall' ),
				'after_desc'          => __( 'Clear your page cache, or visitors may keep seeing the block page.', 'wp-simple-firewall' ),
				'confirm_heading'     => __( 'Before you lock down', 'wp-simple-firewall' ),
				'confirm_consequences'=> __( 'I understand the consequences of blocking all traffic to this site.', 'wp-simple-firewall' ),
				'confirm_access'      => __( 'I know how to regain access if I become locked out, for example if my IP address changes.', 'wp-simple-firewall' ),
				'confirm_cache'       => __( "I know that if I use caching and don't clear it afterwards, it'll cause trouble for visitors.", 'wp-simple-firewall' ),
				'confirm_authority'   => __( 'I have authority to block all traffic to this site.', 'wp-simple-firewall' ),
				'locked_out_title'    => __( 'Locked out?', 'wp-simple-firewall' ),
				'locked_out_desc'     => __( 'Ask your host to rename the Shield plugin folder. That turns lockdown off.', 'wp-simple-firewall' ),
				'further_help'        => __( 'Further help', 'wp-simple-firewall' ),
				'bar_needs'           => __( 'Tick all four to lock the site down.', 'wp-simple-firewall' ),
				'bar_needs_ip'        => __( 'Tick all four, and add your IP, to lock the site down.', 'wp-simple-firewall' ),
				'bar_ready'           => __( 'Ready. Everyone but the addresses above will be blocked.', 'wp-simple-firewall' ),
				'button_lockdown'     => __( 'Lock down the site', 'wp-simple-firewall' ),
				'pro_note'            => __( 'Site Lockdown needs a Pro plan.', 'wp-simple-firewall' ),
				'compare_plans'       => __( 'Compare plans', 'wp-simple-firewall' ),
				'security_admin_html' => sprintf(
					__( 'To stop other admins using this page, turn on %s.', 'wp-simple-firewall' ),
					sprintf( '<a href="%s">%s</a>', esc_url( $con->plugin_urls->configureHome( 'control' ) ), __( 'Security Admin', 'wp-simple-firewall' ) )
				),
			],
			'flags'   => [
				'blockdown_active'       => $cfg->isLockdownActive(),
				'can_blockdown'          => $con->caps->canSiteBlockdown(),
				'is_your_ip_whitelisted' => $isYourIpBypassed,
				'your_ip_added'          => (string)$cfg->whitelist_me === $yourIp && $yourIp !== '',
			],
			'hrefs'   => [
				'ip_rules'      => $con->plugin_urls->adminIpRules(),
				'further_help'  => 'https://clk.shldscrty.com/lo',
				'compare_plans' => $con->plugin_urls->licenseCheck(),
			],
			'vars'    => [
				'your_ip'         => $yourIp,
				'others_bypassed' => $othersBypassed,
				'active_since' => Services::Request()
										  ->carbon()
										  ->setTimestamp( $cfg->activated_at )
										  ->diffForHumans(),
				'active_by'    => $cfg->activated_by,
			],
		] );
	}

	protected function getLandingTitle() :string {
		return __( 'Site Lockdown', 'wp-simple-firewall' );
	}

	protected function getLandingSubtitle() :string {
		return __( 'Block all access to the site except from IPs on the bypass/white list.', 'wp-simple-firewall' );
	}

	protected function getLandingIcon() :string {
		return 'sign-stop-fill';
	}

	protected function getLandingMode() :string {
		return PluginNavs::MODE_CONFIGURE;
	}

	protected function hasOperatorModeParent() :bool {
		return true;
	}
}
