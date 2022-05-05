<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Strings extends Base\Strings {

	/**
	 * @inheritDoc
	 */
	public function getEventStrings() :array {
		return [
			'conn_kill'               => [
				'name'  => __( 'Connection Killed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Visitor found on the Black List and their connection was killed.', 'wp-simple-firewall' ),
				],
			],
			'conn_not_kill_high_rep'  => [
				'name'  => __( 'Connection Not Killed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'IP address has a high reputation so connection allowed.', 'wp-simple-firewall' ),
				],
			],
			'ip_offense'              => [
				'name'  => __( 'Offense Triggered', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Auto Black List offenses counter was incremented from {{from}} to {{to}}.', 'wp-simple-firewall' ),
				],
			],
			'ip_blocked'              => [
				'name'  => __( 'IP Blocked', 'wp-simple-firewall' ),
				'audit' => [
					__( 'IP blocked after incrementing offenses from {{from}} to {{to}}.', 'wp-simple-firewall' ),
				],
			],
			'ip_unblock'              => [
				'name'  => __( 'IP Unblocked', 'wp-simple-firewall' ),
				'audit' => [
					__( 'IP removed from block list.', 'wp-simple-firewall' ),
				],
			],
			'ip_unblock_flag'         => [
				'name'  => __( 'IP Unblocked (Flag File)', 'wp-simple-firewall' ),
				'audit' => [
					__( "IP address '{{ip}}' removed from blacklist using 'unblock' file flag.", 'wp-simple-firewall' ),
				],
			],
			'ip_block_auto'           => [
				'name'  => __( 'IP Block List Add (Auto)', 'wp-simple-firewall' ),
				'audit' => [
					__( "IP address '{{ip}}' automatically added to block list as an offender.", 'wp-simple-firewall' )
					.' '.__( "The IP may not be blocked yet.", 'wp-simple-firewall' ),
				],
			],
			'ip_block_manual'         => [
				'name'  => __( 'IP Block List Add (Manual)', 'wp-simple-firewall' ),
				'audit' => [
					__( "IP address '{{ip}}' manually added to block list.", 'wp-simple-firewall' ),
				],
			],
			'ip_bypass_add'           => [
				'name'  => __( 'IP Bypass List Add (Manual)', 'wp-simple-firewall' ),
				'audit' => [
					__( "IP address '{{ip}}' manually added to bypass list.", 'wp-simple-firewall' ),
				],
			],
			'ip_bypass_remove'        => [
				'name'  => __( 'IP Bypass List Removed (Manual)', 'wp-simple-firewall' ),
				'audit' => [
					__( "IP address '{{ip}}' manually removed from the bypass list.", 'wp-simple-firewall' ),
				],
			],
			'bottrack_notbot'         => [
				'name'  => __( 'NotBot Registration', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Visitor registered using NotBot.', 'wp-simple-firewall' ),
				],
			],
			'bottrack_404'            => [
				'name'  => sprintf( '%s: %s', __( 'Bot Detection', 'wp-simple-firewall' ), '404' ),
				'audit' => [
					__( '404 detected at "{{path}}".', 'wp-simple-firewall' ),
				],
			],
			'bottrack_fakewebcrawler' => [
				'name'  => sprintf( '%s: %s', __( 'Bot Detection', 'wp-simple-firewall' ),
					__( 'Fake Web Crawler', 'wp-simple-firewall' ) ),
				'audit' => [
					__( 'Fake Web Crawler detected at "{{path}}".', 'wp-simple-firewall' ),
					__( 'Fake Crawler misrepresented itself as "{{crawler}}".', 'wp-simple-firewall' ),
				],
			],
			'bottrack_linkcheese'     => [
				'name'  => sprintf( '%s: %s', __( 'Bot Detection', 'wp-simple-firewall' ),
					__( 'Link Cheese', 'wp-simple-firewall' ) ),
				'audit' => [
					__( 'Link cheese access detected at "{{path}}".', 'wp-simple-firewall' ),
				],
			],
			'bottrack_loginfailed'    => [
				'name'  => sprintf( '%s: %s', __( 'Bot Detection', 'wp-simple-firewall' ),
					__( 'Failed Login', 'wp-simple-firewall' ) ),
				'audit' => [
					__( 'Attempted login failed by user "{{user_login}}".', 'wp-simple-firewall' ),
				],
			],
			'bottrack_logininvalid'   => [
				'name'  => sprintf( '%s: %s', __( 'Bot Detection', 'wp-simple-firewall' ),
					__( 'Invalid Username Login', 'wp-simple-firewall' ) ),
				'audit' => [
					__( 'Attempted login with invalid user "{{user_login}}".', 'wp-simple-firewall' ),
				],
			],
			'bottrack_useragent'      => [
				/** TODO **/
				'name'  => sprintf( '%s: %s', __( 'Bot Detection', 'wp-simple-firewall' ),
					__( 'Invalid User-Agent', 'wp-simple-firewall' ) ),
				'audit' => [
					__( 'Invalid user agent detected at "{{useragent}}".', 'wp-simple-firewall' ),
				],
			],
			'bottrack_xmlrpc'         => [
				'name'  => sprintf( '%s: %s', __( 'Bot Detection', 'wp-simple-firewall' ),
					__( 'XML-RPC', 'wp-simple-firewall' ) ),
				'audit' => [
					__( 'Access to XML-RPC detected at "{{path}}".', 'wp-simple-firewall' ),
				],
			],
			'bottrack_invalidscript'  => [
				'name'  => sprintf( '%s: %s', __( 'Bot Detection', 'wp-simple-firewall' ),
					__( 'Invalid Script Load', 'wp-simple-firewall' ) ),
				'audit' => [
					__( 'Tried to load an invalid WordPress PHP script "{{script}}".', 'wp-simple-firewall' ),
				],
			],
			'comment_markspam'        => [
				'name'  => __( 'Mark Comment SPAM (Manual)', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Comment manually marked as SPAM.', 'wp-simple-firewall' ),
				],
			],
			'comment_unmarkspam'      => [
				'name'  => __( 'Mark Comment Not SPAM (Manual)', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Comment manually marked as not SPAM.', 'wp-simple-firewall' ),
				],
			],
			'custom_offense'          => [
				'name'  => __( 'Mark Comment Not SPAM (Manual)', 'wp-simple-firewall' ),
				'audit' => [
					__( 'A custom offense was registered on the site.', 'wp-simple-firewall' ),
					sprintf( '%s: {{message}}', __( 'Message', 'wp-simple-firewall' ) ),
				],
			],
		];
	}

	public function getSectionStrings( string $section ) :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$sPlugName = $this->getCon()->getHumanName();
		$sModName = $mod->getMainFeatureName();

		switch ( $section ) {

			case 'section_enable_plugin_feature_ips' :
				$titleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$title = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $sModName );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'The IP Manager allows you to whitelist, blacklist and configure auto-blacklist rules.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'IP Manager', 'wp-simple-firewall' ) ) )
					.'<br />'.__( 'You should also carefully review the automatic black list settings.', 'wp-simple-firewall' )
				];
				break;

			case 'section_auto_black_list' :
				$title = __( 'Auto IP Blocking Rules', 'wp-simple-firewall' );
				$titleShort = __( 'Auto Blocking Rules', 'wp-simple-firewall' );
				$summary = [
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

			case 'section_logins':
				$titleShort = __( 'Login Bots', 'wp-simple-firewall' );
				$title = __( 'Detect & Capture Login Bots', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Summary', 'wp-simple-firewall' ),
						__( "Certain bots are designed to test your logins and this feature lets you decide how to handle them.", 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ),
						__( "Enable as many options as possible.", 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Warning', 'wp-simple-firewall' ),
						__( "Legitimate users may get their password wrong, so take care not to block this.", 'wp-simple-firewall' ) ),
				];
				break;

			case 'section_probes':
				$titleShort = __( 'Probing Bots', 'wp-simple-firewall' );
				$title = __( 'Detect & Capture Probing Bots', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Summary', 'wp-simple-firewall' ),
						__( "Bots are designed to probe and this feature is dedicated to detecting probing bots.", 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ),
						__( "Enable as many options as possible.", 'wp-simple-firewall' ) ),
				];
				break;

			case 'section_behaviours':
				$titleShort = __( 'Bot Behaviours', 'wp-simple-firewall' );
				$title = __( 'Detect Behaviours Common To Bots', 'wp-simple-firewall' );
				$summary = [
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
			'title'       => $title,
			'title_short' => $titleShort,
			'summary'     => $summary,
		];
	}

	/**
	 * @throws \Exception
	 */
	public function getOptionStrings( string $key ) :array {
		$pluginName = $this->getCon()->getHumanName();
		$modName = $this->getMod()->getMainFeatureName();

		switch ( $key ) {

			case 'enable_ips' :
				$name = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $modName );
				$summary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $modName );
				$desc = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $modName );
				break;

			case 'transgression_limit' :
				$name = __( 'Offense Limit', 'wp-simple-firewall' );
				$summary = __( 'The number of permitted offenses before an IP address will be blocked', 'wp-simple-firewall' );
				$desc = [
					sprintf( __( 'An offense is registered against an IP address each time a visitor trips the defenses of the %s plugin.', 'wp-simple-firewall' ), $pluginName ),
					__( 'When the number of these offenses exceeds the limit, they are automatically blocked from accessing the site.', 'wp-simple-firewall' ),
					sprintf( __( 'Set this to "0" to turn off the %s feature.', 'wp-simple-firewall' ), __( 'Automatic IP Black List', 'wp-simple-firewall' ) )
				];
				break;

			case 'auto_expire' :
				$name = __( 'Auto Block Expiration', 'wp-simple-firewall' );
				$summary = __( 'After 1 "X" a black listed IP will be removed from the black list', 'wp-simple-firewall' );
				$desc = [
					__( 'Blocked IP addresses are eventually removed.', 'wp-simple-firewall' )
					.'<br/>'.__( 'This option lets you specify how long they should be kept.', 'wp-simple-firewall' ),
					__( 'Large, permanent IP Block Lists will degrade site performance.', 'wp-simple-firewall' ),
					__( 'Shorter IP black lists are more efficient and a more intelligent use of an IP-based blocking system.', 'wp-simple-firewall' )
				];
				break;

			case 'user_auto_recover' :
				$name = __( 'User Auto Unblock', 'wp-simple-firewall' );
				$summary = __( 'Allow Visitors To Unblock Their IP', 'wp-simple-firewall' );
				$desc = __( 'Allow visitors blocked by the plugin to automatically unblock themselves.', 'wp-simple-firewall' );
				break;

			case 'request_whitelist' :
				$name = __( 'Request Path Whitelist', 'wp-simple-firewall' );
				$summary = __( 'Request Path Whitelist', 'wp-simple-firewall' );
				$desc = __( 'A list of request paths that will never trigger an offense.', 'wp-simple-firewall' )
						.'<br />- '.__( 'This is an advanced option and should be used with great care.', 'wp-simple-firewall' )
						.'<br />- '.__( 'Take a new line for each whitelisted path.', 'wp-simple-firewall' )
						.'<br />- '.__( "All characters will be treated as case-insensitive.", 'wp-simple-firewall' )
						.'<br />- '.__( "The paths are compared against only the request path, not the query portion.", 'wp-simple-firewall' )
						.'<br />- '.__( "If a path you add matches your website root (/), it'll be removed automatically.", 'wp-simple-firewall' );

				break;

			case 'antibot_minimum' :
				$name = __( 'AntiBot Minimum Score', 'wp-simple-firewall' );
				$summary = __( 'AntiBot Minimum Score (Percentage)', 'wp-simple-firewall' );
				$desc = [
					__( "Every IP address accessing your site gets its own unique visitor score - the higher the score, the better the visitor i.e. the more likely it's human.", 'wp-simple-firewall' ),
					__( "A score of '100' would mean it's almost certainly good, a score of '0' means it's highly likely to be a bad bot.", 'wp-simple-firewall' ),
					__( 'When a bot tries to login, or post a comment, we test its visitor score.', 'wp-simple-firewall' )
					.' '.__( 'If the visitor score fails to meet your Minimum AntiBot Score, we prevent the request. If its higher, we allow it.', 'wp-simple-firewall' ),
					__( "This means: choose a higher minimum score to be more strict and capture more bots (but potentially block someone that appears to be a bot, but isn't).", 'wp-simple-firewall' )
					.' '.__( "Or choose a lower minimum score to perhaps allow through more bots (but reduce the chances of accidentally blocking legitimate visitors).", 'wp-simple-firewall' ),
				];
				break;

			case 'antibot_high_reputation_minimum' :
				$name = __( 'High Reputation Bypass', 'wp-simple-firewall' );
				$summary = __( 'Prevent Visitors With A High Reputation Scores From Being Blocked', 'wp-simple-firewall' );
				$desc = [
					__( "Visitors that have accumulated a high IP reputation and AntiBot score should ideally never be blocked.", 'wp-simple-firewall' ),
					__( "This option ensures that visitors with a high reputation never have their IP blocked by Shield.", 'wp-simple-firewall' ),
				];
				break;

			case 'force_notbot' :
				$name = __( 'Force NotBot JS', 'wp-simple-firewall' );
				$summary = __( 'Force Loading Of NotBot JS', 'wp-simple-firewall' );
				$desc = [
					sprintf( __( '%s uses Javascript to help identify bots versus legitimate visitors.', 'wp-simple-firewall' ),
						$pluginName )
					.' '.__( "However, caching plugins often interfere, preventing it loading for your visitors.", 'wp-simple-firewall' ),
					__( "This may cause some of your legitimate users to be identified as bots, when they're not.", 'wp-simple-firewall' ),
					__( "Turn this option on if you're using an aggressive caching system, to ensure NotBot JS is loaded for all visitors.", 'wp-simple-firewall' ),
					__( "When this option is disabled we'll automatically optimise loading of the Javascript so it's only loaded where it's required.", 'wp-simple-firewall' )
					.' '.__( "You should test your site and keep a lookout for user login issues after disabling this option.", 'wp-simple-firewall' )
				];
				break;

			case 'text_loginfailed' :
				$name = __( 'Login Failed', 'wp-simple-firewall' );
				$summary = __( 'Visitor Triggers The IP Offense System Through A Failed Login', 'wp-simple-firewall' );
				$desc = __( 'This message is displayed if the visitor fails a login attempt.', 'wp-simple-firewall' );
				break;

			case 'track_404' :
				$name = __( '404 Detect', 'wp-simple-firewall' );
				$summary = __( 'Identify A Bot When It Hits A 404', 'wp-simple-firewall' );
				$desc = [
					__( 'Detect when a visitor tries to load a non-existent page.', 'wp-simple-firewall' ),
					__( "Care should be taken to ensure that your website doesn't generate 404 errors for normal visitors.", 'wp-simple-firewall' ),
					sprintf( '%s: <br/><strong>%s</strong>',
						__( "404 errors generated for the following file types won't trigger an offense", 'wp-simple-firewall' ),
						implode( ', ', $this->getOptions()->getDef( 'allowable_ext_404s' ) )
					),
				];
				break;

			case 'track_xmlrpc' :
				$name = __( 'XML-RPC Access', 'wp-simple-firewall' );
				$summary = __( 'Identify A Bot When It Accesses XML-RPC', 'wp-simple-firewall' );
				$desc = [
					__( "If you don't use XML-RPC, there's no reason anything should be accessing it.", 'wp-simple-firewall' ),
					__( "Be careful to ensure you don't block legitimate XML-RPC traffic if your site needs it.", 'wp-simple-firewall' ),
					__( "We recommend logging here in-case of blocking valid request unless you're sure.", 'wp-simple-firewall' )
				];
				break;

			case 'track_linkcheese' :
				$name = __( 'Link Cheese', 'wp-simple-firewall' );
				$summary = __( 'Tempt A Bot With A Fake Link To Follow', 'wp-simple-firewall' );
				$desc = [
					__( "Detect a bot when it follows a fake 'no-follow' link.", 'wp-simple-firewall' ),
					__( "This works because legitimate web crawlers respect 'robots.txt' and 'nofollow' directives.", 'wp-simple-firewall' )
				];
				break;

			case 'track_logininvalid' :
				$name = __( 'Invalid Usernames', 'wp-simple-firewall' );
				$summary = __( "Detect Attempted Logins With Usernames That Don't Exist", 'wp-simple-firewall' );
				$desc = [
					__( "Identify a Bot when it tries to login with a non-existent username.", 'wp-simple-firewall' ),
					__( "This includes the default 'admin' if you've removed that account.", 'wp-simple-firewall' )
				];
				break;

			case 'track_loginfailed' :
				$name = __( 'Failed Login', 'wp-simple-firewall' );
				$summary = __( 'Detect Failed Login Attempts For Users That Exist', 'wp-simple-firewall' );
				$desc = __( "Penalise a visitor when they try to login using a valid username, but it fails.", 'wp-simple-firewall' );
				break;

			case 'track_invalidscript' :
				$name = __( 'Invalid Script Load', 'wp-simple-firewall' );
				$summary = __( 'Identify Bot Attempts To Load WordPress In A Non-Standard Way', 'wp-simple-firewall' );
				$desc = [
					__( "Detect when a bot tries to load WordPress directly from a file that isn't normally used to load WordPress.", 'wp-simple-firewall' ),
					__( 'WordPress should only be loaded in a limited number of ways.', 'wp-simple-firewall' ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ),
						sprintf( __( 'Set this option to "%s" and monitor the Audit Trail, since some plugins, themes, or custom integrations may trigger this.', 'wp-simple-firewall' ), __( 'Audit Log Only', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'track_fakewebcrawler' :
				$name = __( 'Fake Web Crawler', 'wp-simple-firewall' );
				$summary = __( 'Detect Fake Search Engine Crawlers', 'wp-simple-firewall' );
				$desc = [
					__( "Identify a visitor as a Bot when it presents as an official web crawler, but analysis shows it's fake.", 'wp-simple-firewall' ),
					__( "Many bots pretend to be a Google Bot.", 'wp-simple-firewall' )
					.'<br/>'.__( "We can then know that a bot isn't here for anything good and block them.", 'wp-simple-firewall' ),
				];
				break;

			case 'track_useragent' :
				$name = __( 'Empty User Agents', 'wp-simple-firewall' );
				$summary = __( 'Detect Requests With Empty User Agents', 'wp-simple-firewall' );
				$desc = [
					__( "Identify a bot when the user agent is not provided.", 'wp-simple-firewall' ),
					sprintf( '%s:<br/><code>%s</code>',
						__( 'For example, your browser user agent is', 'wp-simple-firewall' ), Services::Request()
																									   ->getUserAgent() )
				];
				break;

			default:
				return parent::getOptionStrings( $key );
		}

		return [
			'name'        => $name,
			'summary'     => $summary,
			'description' => $desc,
		];
	}

	public function getBotSignalName( $field ) :string {
		return $this->getBotSignalNames()[ str_replace( '_at', '', $field ) ] ?? 'Unknown';
	}

	/**
	 * @return string[]
	 */
	public function getBotSignalNames() :array {
		return [
			'created'         => __( 'New Visitor Bonus', 'wp-simple-firewall' ),
			'known'           => __( 'A Known Service Provider/Bot', 'wp-simple-firewall' ),
			'notbot'          => __( 'Not Bot Registration', 'wp-simple-firewall' ),
			'frontpage'       => __( 'Any Frontend Page Visited', 'wp-simple-firewall' ),
			'loginpage'       => __( 'Login Page Visited', 'wp-simple-firewall' ),
			'bt404'           => __( '404 Triggered', 'wp-simple-firewall' ),
			'btauthorfishing' => __( 'Username Fishing', 'wp-simple-firewall' ),
			'btfake'          => __( 'Fake Web Crawler', 'wp-simple-firewall' ),
			'btcheese'        => __( 'Link Cheese', 'wp-simple-firewall' ),
			'btloginfail'     => __( 'Login Fail', 'wp-simple-firewall' ),
			'btua'            => __( 'Invalid User Agent', 'wp-simple-firewall' ),
			'btxml'           => __( 'XMLRPC Access', 'wp-simple-firewall' ),
			'btlogininvalid'  => __( 'Invalid Login Username', 'wp-simple-firewall' ),
			'btinvalidscript' => __( 'Invalid Script Access', 'wp-simple-firewall' ),
			'cooldown'        => __( 'Cooldown Triggered', 'wp-simple-firewall' ),
			'humanspam'       => __( 'Comment Triggered Human SPAM Detection', 'wp-simple-firewall' ),
			'markspam'        => __( 'Comment Marked As SPAM', 'wp-simple-firewall' ),
			'unmarkspam'      => __( 'Comment Unmarked As SPAM', 'wp-simple-firewall' ),
			'auth'            => __( 'Authenticated With Site', 'wp-simple-firewall' ),
			'ratelimit'       => __( 'Rate Limit Exceeded', 'wp-simple-firewall' ),
			'captchapass'     => __( 'Captcha Verification Passed', 'wp-simple-firewall' ),
			'captchafail'     => __( 'Captcha Verification Failed', 'wp-simple-firewall' ),
			'firewall'        => __( 'Firewall Triggered', 'wp-simple-firewall' ),
			'offense'         => __( 'Offense Triggered', 'wp-simple-firewall' ),
			'blocked'         => __( 'IP Blocked', 'wp-simple-firewall' ),
			'unblocked'       => __( 'IP Unblocked', 'wp-simple-firewall' ),
			'bypass'          => __( 'IP Bypassed', 'wp-simple-firewall' ),
		];
	}
}