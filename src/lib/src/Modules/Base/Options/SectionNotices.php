<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies\Monolog;
use FernleafSystems\Wordpress\Plugin\Shield\Enum\EnumModules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\{
	Integrations\Lib\Bots\Common\BaseHandler,
	IPs\Lib\IpRules\IpRuleStatus,
	LoginGuard\Lib\TwoFactor\Utilties\PasskeyCompatibilityCheck,
	PluginControllerConsumer
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot\TestNotBotLoading;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Adhoc\WorldTimeApi;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Encrypt\CipherTests;

class SectionNotices {

	use PluginControllerConsumer;

	public function critical( string $section ) :array {
		$con = self::con();

		$critical = [];

		switch ( $section ) {
			case 'section_rename_wplogin':
				if ( ( new IpRuleStatus( $con->this_req->ip ) )->isBypass() ) {
					$critical[] = sprintf( __( "Your IP address is whitelisted! This setting doesn't apply to YOU, so you must always use the normal login page: %s" ),
						\basename( Services::WpGeneral()->getLoginUrl() ) );
				}
				break;
			default:
				break;
		}

		return $critical;
	}

	public function notices( string $section ) :array {
		$con = self::con();
		$opts = $con->opts;

		$notices = [];
		switch ( $section ) {

			case 'section_2fa_email':
				if ( $opts->optIs( 'enable_email_authentication', 'Y' ) && $opts->optGet( 'email_can_send_verified_at' ) < 1 ) {
					$notices[] = \implode( ' ', [
						__( "The ability of this site to send email hasn't been verified.", 'wp-simple-firewall' ),
						__( 'Please re-save your settings to trigger another verification email.', 'wp-simple-firewall' )
					] );
				}

				$notices[] = \implode( '<br/>', [
					__( 'Email-based 2-Factor Authentication needs a reliable email delivery system on your WordPress site.', 'wp-simple-firewall' ),
					__( 'This is a common problem and you may get locked out in the future if you ignore this.', 'wp-simple-firewall' )
					.' '.sprintf( '<a href="%s" target="_blank" class="alert-link">%s</a>', 'https://shsec.io/dd', \trim( __( 'Learn More.', 'wp-simple-firewall' ), '.' ) )
				] );

				break;

			case 'section_user_forms':
				if ( $con->comps->opts_lookup->enabledLoginGuardAntiBotCheck() ) {
					$locations = $opts->optGet( 'bot_protection_locations' );
					$locations = \array_intersect_key(
						\array_merge(
							\array_flip( $locations ),
							[
								'login'        => __( 'Login', 'wp-simple-firewall' ),
								'register'     => __( 'Registration', 'wp-simple-firewall' ),
								'password'     => __( 'Lost Password', 'wp-simple-firewall' ),
								'checkout_woo' => __( 'Checkout', 'wp-simple-firewall' ),
							]
						),
						\array_flip( $locations )
					);
					$locations = empty( $locations ) ? __( 'None', 'wp-simple-firewall' ) : \implode( ', ', $locations );

					$notices[] = sprintf( '%s: %s %s', __( 'Note', 'wp-simple-firewall' ),
						sprintf(
							__( "The following types of user forms are protected by AntiBot Detection: %s.", 'wp-simple-firewall' ),
							$locations
						),
						sprintf( '<a href="%s" target="_blank">%s</a>',
							$con->plugin_urls->modCfg( EnumModules::LOGIN ),
							__( 'Click here to review those settings.', 'wp-simple-firewall' ) )
					);
				}
				break;

			default:
				break;
		}
		return $notices;
	}

	public function warnings( string $section ) :array {
		$con = self::con();
		$optsLookup = $con->comps->opts_lookup;

		$warnings = [];

		switch ( $section ) {

			case 'section_localdb':
			case 'section_at_file':
			case 'section_traffic_options':
				try {
					( new Monolog() )->assess();
				}
				catch ( \Exception $e ) {
					$warnings[] = __( "Logging isn't currently available on this site.", 'wp-simple-firewall' )
								  .'<br/>'.sprintf( '%s: %s', __( 'Reason', 'wp-simple-firewall' ), $e->getMessage() );
				}

				if ( $section === 'section_traffic_options' ) {
					if ( $optsLookup->getTrafficLiveLogTimeRemaining() > 0 ) {
						$warnings[] = \implode( ' ', [
							__( 'Live traffic logging increases load on your database and is designed to be active only temporarily.', 'wp-simple-firewall' ),
							__( 'We recommend disabling it if you no longer need it running.', 'wp-simple-firewall' ),
						] );
					}
					if ( $optsLookup->enabledTrafficLimiter() ) {
						$warnings[] = \implode( ' ', [
							__( "To disable traffic logging, please first disable Traffic Rate Limiting.", 'wp-simple-firewall' ),
						] );
					}
				}
				break;

			case 'section_deprecated':
				if ( $con->opts->optIs( 'enable_antibot_check', 'Y' ) ) {
					$warnings[] = __( 'You must disable ADE protection if you want to enable these options.', 'wp-simple-firewall' );
				}
				break;

			case 'section_user_session_management':
				$source = Services::Request()->getIpDetector()->getPublicRequestSource();
				if ( $source !== 'REMOTE_ADDR' && \in_array( 'ip', $con->opts->optGet( 'session_lock' ) ) ) {
					$warnings[] = sprintf( '%s %s',
						sprintf( __( "Visitor IP addresses can be spoofed on your site, so the Session Lock feature may not work as well as expected.", 'wp-simple-firewall' ),
							sprintf( '<code>%s</code>', $source ) ),
						sprintf( '[<a href="%s">%s</a>]',
							$con->plugin_urls->modCfgOption( 'visitor_address_source' ), __( 'View IP Source Config', 'wp-simple-firewall' ) )
					);
				}
				break;

			case 'section_whitelabel':
				if ( !$con->comps->sec_admin->isEnabledSecAdmin() ) {
					$warnings[] = __( 'Please also supply a Security Admin PIN, as whitelabel settings are only applied when the Security Admin feature is active.', 'wp-simple-firewall' );
				}
				break;

			case 'section_2fa_email':
				$nonRoles = \array_diff(
					$optsLookup->getLoginGuardEmailAuth2FaRoles(),
					Services::WpUsers()->getAvailableUserRoles()
				);
				if ( \count( $nonRoles ) > 0 ) {
					$warnings[] = sprintf( '%s: %s',
						__( "Certain user roles are set for email authentication enforcement that aren't currently available" ),
						\implode( ', ', $nonRoles ) );
				}
				break;

			case 'section_2fa_otp':
				try {
					$diff = ( new WorldTimeApi() )->diffServerWithReal();
					if ( $diff > 10 ) {
						$warnings[] = __( 'It appears that your server time configuration is out of sync - Please contact your server admin, as features like Google Authenticator wont work.', 'wp-simple-firewall' );
					}
				}
				catch ( \Exception $e ) {
				}
				break;

			case 'section_2fa_passkeys':
				try {
					$passkeyChecker = new PasskeyCompatibilityCheck();
					if ( !$passkeyChecker->run() ) {
						$warnings[] = sprintf( __( 'To use Passkeys, your PHP installation must have 1 of the following extensions loaded: %s', 'wp-simple-firewall' ),
							'<code>'.\implode( '</code>, <code>', $passkeyChecker->requiredExtensions() ).'</code>' );
					}
				}
				catch ( \Exception $e ) {
				}
				break;

			case 'section_brute_force_login_protection':
				if ( empty( $con->opts->optGet( 'bot_protection_locations' ) ) ) {
					$warnings[] = __( "AntiBot detection isn't being applied to your site because you haven't selected any forms to protect, such as Login or Register.", 'wp-simple-firewall' );
				}
				elseif ( !$optsLookup->isModEnabled( EnumModules::IPS ) ) {
					$warnings[] = sprintf(
						__( "WordPress login forms aren't protected against bots because you've disabled %s, which controls the ADE Bot Detection system.", 'wp-simple-firewall' ),
						sprintf( '<a href="%s">%s</a>', $con->plugin_urls->modCfgSection( $con->modules[ EnumModules::IPS ], 'section_enable_plugin_feature_ips' ), 'the IP Blocking module' )
					);
				}

				$installedButNotEnabledProviders = \array_filter(
					$con->comps->forms_users->getInstalled(),
					function ( string $provider ) {
						return !( new $provider() )->isEnabled();
					}
				);

				if ( !empty( $installedButNotEnabledProviders ) ) {
					$warnings[] = sprintf( __( "%s has an integration available to protect the login forms of a 3rd party plugin you're using: %s", 'wp-simple-firewall' ),
						$con->getHumanName(),
						sprintf( '<a href="%s">%s</a>',
							$con->plugin_urls->modCfgSection( $con->modules[ EnumModules::INTEGRATIONS ], 'section_user_forms' ),
							sprintf( __( "View the available integrations.", 'wp-simple-firewall' ), $con->getHumanName() )
						)
					);
				}
				break;

			case 'section_user_forms':
				if ( !$optsLookup->isModEnabled( EnumModules::IPS ) ) {
					$warnings[] = sprintf(
						__( "WordPress login forms aren't protected against bots because you've disabled %s, which controls the ADE Bot Detection system.", 'wp-simple-firewall' ),
						sprintf( '<a href="%s">%s</a>', $con->plugin_urls->modCfgSection( EnumModules::IPS, 'section_enable_plugin_feature_ips' ), 'the IP Blocking module' )
					);
				}
				elseif ( !$optsLookup->enabledLoginGuardAntiBotCheck() ) {
					$warnings[] = sprintf( '%s: %s %s', __( 'Important', 'wp-simple-firewall' ),
						__( "Use of the AntiBot Detection Engine for user forms isn't turned on in the Login Guard module.", 'wp-simple-firewall' ),
						sprintf( '<a href="%s" target="_blank">%s</a>',
							$con->plugin_urls->modCfg( EnumModules::LOGIN ),
							__( 'Click here to review those settings.', 'wp-simple-firewall' ) )
					);
				}
				break;

			case 'section_spam':
				if ( !$optsLookup->isModEnabled( EnumModules::IPS ) ) {
					$warnings[] = sprintf(
						__( "WordPress login forms aren't protected against bots because you've disabled %s, which controls the ADE Bot Detection system.", 'wp-simple-firewall' ),
						sprintf( '<a href="%s">%s</a>', $con->plugin_urls->modCfgSection( EnumModules::IPS, 'section_enable_plugin_feature_ips' ), 'the IP Blocking module' )
					);
				}
				else {
					/** @var BaseHandler[] $installedButNotEnabledProviders */
					$installedButNotEnabledProviders = \array_filter(
						\array_map(
							function ( $provider ) {
								return new $provider();
							},
							$con->comps->forms_spam->enumProviders()
						),
						function ( $provider ) {
							return !$provider->isEnabled() && $provider::IsProviderAvailable();
						}
					);

					if ( !empty( $installedButNotEnabledProviders ) ) {
						$warnings[] = sprintf( __( "%s has an integration available to protect the forms of a 3rd party plugin you're using: %s", 'wp-simple-firewall' ),
							$con->getHumanName(),
							\implode( ', ', \array_map(
								function ( $provider ) {
									return $provider->getHandlerName();
								}, $installedButNotEnabledProviders
							) )
						);
					}
				}
				break;

			case 'section_auto_black_list':
				if ( $optsLookup->isModEnabled( EnumModules::IPS ) && !$optsLookup->enabledIpAutoBlock() ) {
					$warnings[] = sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ),
						__( 'IP blocking is turned-off because the offenses limit is set to 0.', 'wp-simple-firewall' ) );
				}
				break;

			case 'section_antibot':
				if ( $optsLookup->isModEnabled( EnumModules::IPS ) && !$optsLookup->enabledAntiBotEngine() ) {
					$warnings[] = sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ),
						sprintf( __( "The AntiBot Detection Engine is disabled when set to a minimum score of %s.", 'wp-simple-firewall' ), '0' ) );
				}
				elseif ( !( new TestNotBotLoading() )->test() ) {
					$warnings[] = sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ),
						sprintf( __( "Shield couldn't determine whether the NotBot JS was loading correctly on your site.", 'wp-simple-firewall' ), '0' ) );
				}
				break;

			case 'section_bot_behaviours':
				if ( $optsLookup->isModEnabled( EnumModules::IPS ) && !$optsLookup->enabledIpAutoBlock() ) {
					$warnings[] = __( 'Since the offenses limit is set to 0, these options have no effect.', 'wp-simple-firewall' );
				}
				if ( \strlen( Services::Request()->getUserAgent() ) == 0 ) {
					$warnings[] = __( "Your User Agent appears to be empty. We don't recommend turning on the useragent option.", 'wp-simple-firewall' );
				}
				break;

			case 'section_file_guard':
				if ( !$con->cache_dir_handler->exists() ) {
					$warnings[] = __( "Plugin/Theme file scanners are unavailable because we couldn't create a temporary directory to store files.", 'wp-simple-firewall' );
				}

				if ( $con->isPremiumActive() ) {
					$canHandshake = $con->comps->shieldnet->canHandshake();
					if ( !$canHandshake ) {
						$warnings[] = __( 'Not available as your site cannot handshake with ShieldNET API.', 'wp-simple-firewall' );
					}
				}

				$enc = Services::Encrypt();
				if ( !$enc->isSupportedOpenSslDataEncryption() ) {
					$warnings[] = sprintf( __( "FileLocker can't be used because the PHP %s extension isn't available.", 'wp-simple-firewall' ), 'OpenSSL' );
				}
				elseif ( \count( ( new CipherTests() )->findAvailableCiphers() ) === 0 ) {
					$warnings[] = sprintf( __( "FileLocker can't be used because there is no encryption cipher isn't available.", 'wp-simple-firewall' ), 'OpenSSL' );
				}

				break;

			case 'section_traffic_limiter':
				if ( $con->caps->canTrafficRateLimit() ) {
					$source = Services::Request()->getIpDetector()->getPublicRequestSource();
					if ( $source !== 'REMOTE_ADDR' ) {
						$warnings[] = sprintf( '%s %s',
							sprintf( __( "We don't recommend running Traffic Rate Limiting while your IP address source isn't set to %s.", 'wp-simple-firewall' ),
								sprintf( '<code>%s</code>', $source ) ),
							sprintf( '[<a href="%s" target="_blank">%s</a>]',
								$con->plugin_urls->modCfgOption( 'visitor_address_source' ), __( 'View Config', 'wp-simple-firewall' ) )
						);
					}
				}
				break;

			default:
				break;
		}

		return $warnings;
	}
}