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
			'audit_trail_days' => $con->comps->activity_log->getAutoCleanDays(),
			'strings'          => [
				'heading_security'           => __( 'Security', 'wp-simple-firewall' ),
				'security_sentence_1'        => __( 'Our website uses specialist security software - %s.', 'wp-simple-firewall' ),
				'security_sentence_2'        => __( 'This helps to ensure data breaches do not occur and our website and data are protected against hacking attempts and intrusion.', 'wp-simple-firewall' ),
				'security_sentence_3'        => __( '%s protects site visitors and works to block potential hacks while monitoring web traffic and filesystem changes.', 'wp-simple-firewall' ),
				'learn_more_intro'           => __( 'To learn more about %s,', 'wp-simple-firewall' ),
				'learn_more_link'            => __( 'please follow this link', 'wp-simple-firewall' ),
				'heading_cookies'            => __( 'Cookies', 'wp-simple-firewall' ),
				'cookies_item_1'             => __( 'The %s plugin never stores any sensitive, personally identifiable information in any cookie at any time.', 'wp-simple-firewall' ),
				'cookies_item_2'             => __( 'In the case that the %s plugin needs to redirect a visitor or any request, it may use a cookie to prevent repeated/infinite redirect loops.', 'wp-simple-firewall' ),
				'cookies_item_3'             => __( 'For registered/logged-in users, the %s plugin uses a cookie to track user sessions and control display of certain in-plugin admin notices.', 'wp-simple-firewall' ),
				'cookies_item_4_sentence_1'  => __( 'The %s plugin does not normally use Cookies for unregistered site visitors.', 'wp-simple-firewall' ),
				'cookies_item_4_sentence_2'  => __( 'It may however use a cookie to register the closure of the %s security badge to prevent repeated display.', 'wp-simple-firewall' ),
				'heading_sessions'           => __( 'Data Storage: User Sessions', 'wp-simple-firewall' ),
				'sessions_sentence_1'        => __( 'For logged-in users, the %s plugin stores information on the username, the IP address and the time of last login and last activity.', 'wp-simple-firewall' ),
				'sessions_sentence_2'        => __( 'This information is purged upon logout or data cleanup.', 'wp-simple-firewall' ),
				'heading_audit'              => __( 'Data Storage: Audit Trail', 'wp-simple-firewall' ),
				'audit_intro'                => __( 'The %s plugin has an Audit Trail feature that will log the following information:', 'wp-simple-firewall' ),
				'audit_item_1'               => __( 'Audit Trail message that may include email addresses', 'wp-simple-firewall' ),
				'audit_item_2'               => __( 'Logged-in username (where applicable)', 'wp-simple-firewall' ),
				'audit_item_3'               => __( 'Originating IP address of the request', 'wp-simple-firewall' ),
				'audit_explanation_1'        => __( 'For logged-in users this represents information that may be used to locate (by IP address) and identify individuals and their activity on the site.', 'wp-simple-firewall' ),
				'audit_explanation_2'        => __( 'This information is stored for security purposes by the site administrator.', 'wp-simple-firewall' ),
				'audit_retention_sentence_1' => __( 'This data will be retained and then automatically purged from the database after a fixed time period, as determined by the site administrator.', 'wp-simple-firewall' ),
				'audit_retention_sentence_2' => __( '(Currently this is set to %s days.)', 'wp-simple-firewall' ),
			],
		];
	}
}
