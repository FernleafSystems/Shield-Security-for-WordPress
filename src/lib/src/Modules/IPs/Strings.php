<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Strings extends Base\Strings {

	/**
	 * @param string $section
	 * @return array
	 * @throws \Exception
	 */
	public function getSectionStrings( $section ) {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		$sPlugName = $this->getCon()->getHumanName();
		$sModName = $oMod->getMainFeatureName();

		switch ( $section ) {

			case 'section_enable_plugin_feature_ips' :
				$sTitleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $sModName );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'The IP Manager allows you to whitelist, blacklist and configure auto-blacklist rules.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'IP Manager', 'wp-simple-firewall' ) ) )
					.'<br />'.__( 'You should also carefully review the automatic black list settings.', 'wp-simple-firewall' )
				];
				break;

			case 'section_auto_black_list' :
				$sTitle = __( 'Auto IP Blocking Rules', 'wp-simple-firewall' );
				$sTitleShort = __( 'Auto Blocking Rules', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'The Automatic IP Black List system will block the IP addresses of naughty visitors after a specified number of offenses.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Automatic IP Black List', 'wp-simple-firewall' ) ) ),
					__( "Think of 'offenses' as just a counter for the number of times a visitor does something bad.", 'wp-simple-firewall' )
					.' '.sprintf(
						__( 'When the counter reaches the limit below (default: %s), %s will block that IP completely.', 'wp-simple-firewall' ),
						$this->getOptions()->getOptDefault( 'transgression_limit' ),
						$sPlugName
					)
				];
				break;

			case 'section_enable_plugin_feature_bottrap' :
				$sTitleShort = __( 'Bot-Trap', 'wp-simple-firewall' );
				$sTitle = __( 'Identify And Capture Bots Based On Their Site Activity', 'wp-simple-firewall' );
				$aSummary = [
					__( "A bot doesn't know what's real and what's not, so it probes many different avenues until it finds something it recognises.", 'wp-simple-firewall' ),
					__( "Bot-Trap monitors a set of typical bot behaviours to help identify probing bots.", 'wp-simple-firewall' ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Enable as many mouse traps as possible.', 'wp-simple-firewall' ) )
				];
				break;

			case 'section_logins':
				$sTitleShort = __( 'Login Bots', 'wp-simple-firewall' );
				$sTitle = __( 'Detect & Capture Login Bots', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Summary', 'wp-simple-firewall' ),
						__( "Certain bots are designed to test your logins and this feature lets you decide how to handle them.", 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ),
						__( "Enable as many options as possible.", 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Warning', 'wp-simple-firewall' ),
						__( "Legitimate users may get their password wrong, so take care not to block this.", 'wp-simple-firewall' ) ),
				];
				break;

			case 'section_probes':
				$sTitleShort = __( 'Probing Bots', 'wp-simple-firewall' );
				$sTitle = __( 'Detect & Capture Probing Bots', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Summary', 'wp-simple-firewall' ),
						__( "Bots are designed to probe and this feature is dedicated to detecting probing bots.", 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ),
						__( "Enable as many options as possible.", 'wp-simple-firewall' ) ),
				];
				break;

			case 'section_behaviours':
				$sTitleShort = __( 'Bot Behaviours', 'wp-simple-firewall' );
				$sTitle = __( 'Detect Behaviours Common To Bots', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Summary', 'wp-simple-firewall' ),
						__( "Detect characteristics and behaviour commonly associated with illegitimate bots.", 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ),
						__( "Enable as many options as possible.", 'wp-simple-firewall' ) ),
				];
				break;

			default:
				return parent::getSectionStrings( $section );
		}

		return [
			'title'       => $sTitle,
			'title_short' => $sTitleShort,
			'summary'     => ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [],
		];
	}

	/**
	 * @param string $key
	 * @return array
	 * @throws \Exception
	 */
	public function getOptionStrings( $key ) {

		$sPlugName = $this->getCon()->getHumanName();
		$sModName = $this->getMod()->getMainFeatureName();

		switch ( $key ) {

			case 'enable_ips' :
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $sModName );
				$sSummary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $sModName );
				$sDescription = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $sModName );
				break;

			case 'transgression_limit' :
				$sName = __( 'Offense Limit', 'wp-simple-firewall' );
				$sSummary = __( 'Visitor IP address will be Black Listed after X bad actions on your site', 'wp-simple-firewall' );
				$sDescription = sprintf( __( 'A black mark is set against an IP address each time a visitor trips the defenses of the %s plugin.', 'wp-simple-firewall' ), $sPlugName )
								.'<br />'.__( 'When the number of these offenses exceeds the limit, they are automatically blocked from accessing the site.', 'wp-simple-firewall' )
								.'<br />'.sprintf( __( 'Set this to "0" to turn off the %s feature.', 'wp-simple-firewall' ), __( 'Automatic IP Black List', 'wp-simple-firewall' ) );
				break;

			case 'auto_expire' :
				$sName = __( 'Auto Block Expiration', 'wp-simple-firewall' );
				$sSummary = __( 'After 1 "X" a black listed IP will be removed from the black list', 'wp-simple-firewall' );
				$sDescription = __( 'Permanent and lengthy IP Black Lists are harmful to performance.', 'wp-simple-firewall' )
								.'<br />'.__( 'You should allow IP addresses on the black list to be eventually removed over time.', 'wp-simple-firewall' )
								.'<br />'.__( 'Shorter IP black lists are more efficient and a more intelligent use of an IP-based blocking system.', 'wp-simple-firewall' );
				break;

			case 'user_auto_recover' :
				$sName = __( 'User Auto Unblock', 'wp-simple-firewall' );
				$sSummary = __( 'Allow Visitors To Unblock Their IP', 'wp-simple-firewall' );
				$sDescription = __( 'Allow visitors blocked by the plugin to automatically unblock themselves.', 'wp-simple-firewall' );
				break;

			case 'request_whitelist' :
				$sName = __( 'Request Path Whitelist', 'wp-simple-firewall' );
				$sSummary = __( 'Request Path Whitelist', 'wp-simple-firewall' );
				$sDescription = __( 'A list of request paths that will never trigger an offense.', 'wp-simple-firewall' )
								.'<br />- '.__( 'This is an advanced option and should be used with great care.', 'wp-simple-firewall' )
								.'<br />- '.__( 'Take a new line for each whitelisted path.', 'wp-simple-firewall' )
								.'<br />- '.__( "All characters will be treated as case-insensitive.", 'wp-simple-firewall' )
								.'<br />- '.__( "The paths are compared against only the request path, not the query portion.", 'wp-simple-firewall' )
								.'<br />- '.__( "If a path you add matches your website root (/), it'll be removed automatically.", 'wp-simple-firewall' );

				break;

			case 'text_loginfailed' :
				$sName = __( 'Login Failed', 'wp-simple-firewall' );
				$sSummary = __( 'Visitor Triggers The IP Offense System Through A Failed Login', 'wp-simple-firewall' );
				$sDescription = __( 'This message is displayed if the visitor fails a login attempt.', 'wp-simple-firewall' );
				break;

			case 'text_remainingtrans' :
				$sName = __( 'Remaining Offenses', 'wp-simple-firewall' );
				$sSummary = __( 'Visitor Triggers The IP Offenses System Through A Firewall Block', 'wp-simple-firewall' );
				$sDescription = __( 'This message is displayed if the visitor triggered the IP Offense system and reports how many offenses remain before being blocked.', 'wp-simple-firewall' );
				break;

			case 'track_404' :
				$sName = __( '404 Detect', 'wp-simple-firewall' );
				$sSummary = __( 'Identify A Bot When It Hits A 404', 'wp-simple-firewall' );
				$sDescription = __( "Detect when a visitor tries to load a non-existent page.", 'wp-simple-firewall' )
								.'<br/>'.__( "Care should be taken to ensure you don't have legitimate links on your site that are 404s.", 'wp-simple-firewall' );
				break;

			case 'track_xmlrpc' :
				$sName = __( 'XML-RPC Access', 'wp-simple-firewall' );
				$sSummary = __( 'Identify A Bot When It Accesses XML-RPC', 'wp-simple-firewall' );
				$sDescription = __( "If you don't use XML-RPC, there's no reason anything should be accessing it.", 'wp-simple-firewall' )
								.'<br/>'.__( "Be careful the ensure you don't block legitimate XML-RPC traffic if your site needs it.", 'wp-simple-firewall' )
								.'<br/>'.__( "We recommend logging here in-case of blocking valid request unless you're sure.", 'wp-simple-firewall' );
				break;

			case 'track_linkcheese' :
				$sName = __( 'Link Cheese', 'wp-simple-firewall' );
				$sSummary = __( 'Tempt A Bot With A Fake Link To Follow', 'wp-simple-firewall' );
				$sDescription = __( "Detect a bot when it follows a fake 'no-follow' link.", 'wp-simple-firewall' )
								.'<br/>'.__( "This works because legitimate web crawlers respect 'robots.txt' and 'nofollow' directives.", 'wp-simple-firewall' );
				break;

			case 'track_logininvalid' :
				$sName = __( 'Invalid Usernames', 'wp-simple-firewall' );
				$sSummary = __( "Detect Attempted Logins With Usernames That Don't Exist", 'wp-simple-firewall' );
				$sDescription = __( "Identify a Bot when it tries to login with a non-existent username.", 'wp-simple-firewall' )
								.'<br/>'.__( "This includes the default 'admin' if you've removed that account.", 'wp-simple-firewall' );
				break;

			case 'track_loginfailed' :
				$sName = __( 'Failed Login', 'wp-simple-firewall' );
				$sSummary = __( 'Detect Failed Login Attempts Using Valid Usernames', 'wp-simple-firewall' );
				$sDescription = __( "Penalise a visitor when they try to login using a valid username, but it fails.", 'wp-simple-firewall' );
				break;

			case 'track_fakewebcrawler' :
				$sName = __( 'Fake Web Crawler', 'wp-simple-firewall' );
				$sSummary = __( 'Detect Fake Search Engine Crawlers', 'wp-simple-firewall' );
				$sDescription = __( "Identify a Bot when it presents as an official web crawler, but analysis shows it's fake.", 'wp-simple-firewall' );
				break;

			case 'track_useragent' :
				$sName = __( 'Empty User Agents', 'wp-simple-firewall' );
				$sSummary = __( 'Detect Requests With Empty User Agents', 'wp-simple-firewall' );
				$sDescription = __( "Identify a bot when the user agent is not provided.", 'wp-simple-firewall' )
								.'<br />'.sprintf( '%s: <code>%s</code>',
						__( 'Your user agent is', 'wp-simple-firewall' ), Services::Request()->getUserAgent() );
				break;

			default:
				return parent::getOptionStrings( $key );
		}

		return [
			'name'        => $sName,
			'summary'     => $sSummary,
			'description' => $sDescription,
		];
	}

	/**
	 * @return string[][]
	 */
	protected function getAuditMessages() {
		return [
			'custom_offense'          => [
				sprintf(
					__( 'A custom %s offense was registered on the site.', 'wp-simple-firewall' ),
					$this->getCon()->getHumanName()
				),
				str_replace( '{{MESSAGE}}', __( 'Message', 'wp-simple-firewall' ), '{{MESSAGE}}: "%s"' ),
			],
			'conn_kill'               => [
				__( 'Visitor found on the Black List and their connection was killed.', 'wp-simple-firewall' )
			],
			'ip_offense'              => [
				__( 'Auto Black List offenses counter was incremented from %s to %s.', 'wp-simple-firewall' )
			],
			'ip_blocked'              => [
				__( 'IP blocked after incrementing offenses from %s to %s.', 'wp-simple-firewall' )
			],
			'ip_unblock_flag'         => [
				__( "IP address '%s' removed from blacklist using 'unblock' file flag.", 'wp-simple-firewall' )
			],
			'bottrack_404'            => [
				__( '404 detected at "%s".', 'wp-simple-firewall' )
			],
			'bottrack_fakewebcrawler' => [
				__( 'Fake Web Crawler detected at "%s".', 'wp-simple-firewall' )
			],
			'bottrack_linkcheese'     => [
				__( 'Link cheese access detected at "%s".', 'wp-simple-firewall' )
			],
			'bottrack_loginfailed'    => [
				__( 'Attempted login failed by user "%s".', 'wp-simple-firewall' )
			],
			'bottrack_logininvalid'   => [
				__( 'Attempted login with invalid user "%s".', 'wp-simple-firewall' )
			],
			'bottrack_useragent'      => [
				__( 'Empty user agent detected at "%s".', 'wp-simple-firewall' )
			],
			'bottrack_xmlrpc'         => [
				__( 'Access to XML-RPC detected at "%s".', 'wp-simple-firewall' )
			],
		];
	}
}