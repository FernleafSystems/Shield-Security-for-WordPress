<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Ssl;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\{
	AuditTrail,
	CommentsFilter,
	Firewall,
	HackGuard,
	Headers,
	Integrations,
	IPs,
	Lockdown,
	LoginGuard,
	Plugin,
	SecurityAdmin,
	Traffic,
	UserManagement
};
use ZxcvbnPhp\Zxcvbn;

class Components {

	use PluginControllerConsumer;

	private static $components;

	/**
	 * @throws \Exception
	 */
	public function getComponent( string $slug ) :array {
		$c = $this->components();
		if ( !isset( $c[ $slug ] ) ) {
			throw new \Exception( 'Component does not exist: '.$slug );
		}
		if ( is_callable( $c[ $slug ] ) ) {
			self::$components[ $slug ] = $c[ $slug ]();
		}

		return $this->postBuildComponent( self::$components[ $slug ] );
	}

	private function postBuildComponent( array $component ) :array {
		$component[ 'new_window' ] = !strpos( $component[ 'href' ] ?? '', 'iCWP_WPSF_OffCanvas' );
		return $component;
	}

	/**
	 * @return array[]
	 * @throws \Exception
	 */
	public function getComponents( array $slugs ) :array {
		$components = [];
		foreach ( $slugs as $slug ) {
			$components[ $slug ] = $this->getComponent( $slug );
		}
		return $components;
	}

	public function getAllComponentsSlugs() :array {
		return array_keys( $this->build() );
	}

	/**
	 * @return \Closure[]
	 */
	private function components() :array {
		if ( !isset( self::$components ) ) {
			self::$components = $this->build();
		}
		return self::$components;
	}

	/**
	 * @return \Closure[]
	 */
	private function build() :array {
		$modFW = $this->getCon()->getModule_Firewall();
		/** @var Firewall\Options $optsFW */
		$optsFW = $modFW->getOptions();
		/** @var Firewall\Strings $stringsFW */
		$stringsFW = $modFW->getStrings();

		$fireModEnabled = $modFW->isModOptEnabled();
		$firewallComponents = [];
		foreach (
			[
				'dir_traversal',
				'wordpress_terms',
				'field_truncation',
				'php_code',
				'exe_file_uploads',
				'aggressive'
			] as $firewallBlockKey
		) {
			$firewallComponents[ 'fwb_'.$firewallBlockKey ] = [
				'title'            => $stringsFW->getFirewallCategoryName( $firewallBlockKey ),
				'desc_protected'   => __( 'Firewall is configured to block this category of requests.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "Firewall isn't configured to block this category of requests.", 'wp-simple-firewall' ),
				'href'             => $fireModEnabled ? $this->getJumpLink( 'block_'.$firewallBlockKey ) : $this->getJumpLink( 'enable_firewall' ),
				'protected'        => $fireModEnabled && $optsFW->isOpt( 'block_'.$firewallBlockKey, 'Y' ),
				'weight'           => 20,
			];
		}

		return array_merge(
			$firewallComponents,
			[
				'all'                      => function () {
					$con = $this->getCon();
					$allMeterGauge = ( new MeterAll() )->setCon( $this->getCon() );
					$meter = $allMeterGauge->buildMeterComponents();

					$weight = 200;
					return [
						'title'            => sprintf( __( 'Overall %s Configuration Summary', 'wp-simple-firewall' ), $con->getHumanName() ),
						'desc_protected'   => sprintf( __( 'The cumulative score for your entire %s configuration', 'wp-simple-firewall' ), $con->getHumanName() ),
						'desc_unprotected' => sprintf( __( 'The cumulative score for your entire %s configuration', 'wp-simple-firewall' ), $con->getHumanName() ),
						'href'             => '',
						'protected'        => $meter[ 'totals' ][ 'percentage' ] > 75,
						'score'            => $meter[ 'totals' ][ 'percentage' ]*$weight/100,
						'weight'           => $weight,
						'original_score'   => $meter[ 'totals' ][ 'percentage' ],
						'letter_score'     => $allMeterGauge->letterScoreFromPercentage( $meter[ 'totals' ][ 'percentage' ] ),
					];
				},
				'shieldpro'                => function () {
					$con = $this->getCon();
					return [
						'title'            => __( 'ShieldPRO Premium Security', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Your site benefits from additional security protection provided by ShieldPRO.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Your site doesn't benefit from the additional security protection provided by ShieldPRO.", 'wp-simple-firewall' ),
						'href'             => $con->getModule_Insights()->getUrl_SubInsightsPage( 'license' ),
						'protected'        => $con->isPremiumActive(),
						'weight'           => 35,
					];
				},
				'comment_spam_antibot'     => function () {
					$modComments = $this->getCon()->getModule_Comments();
					/** @var CommentsFilter\Options $optsComments */
					$optsComments = $modComments->getOptions();
					return [
						'title'            => __( 'Bot Comment SPAM', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Your site is protected against automated Comment SPAM by Bots.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Your site isn't protected against automated Comment SPAM by Bots.", 'wp-simple-firewall' ),
						'href'             => $modComments->isModOptEnabled() ? $this->getJumpLink( 'enable_antibot_comments' ) : $this->getJumpLink( 'enable_comments_filter' ),
						'protected'        => $modComments->isModOptEnabled() && $optsComments->isEnabledAntiBot(),
						'weight'           => 75,
					];
				},
				'comment_spam_human'       => function () {
					$modComments = $this->getCon()->getModule_Comments();
					/** @var CommentsFilter\Options $optsComments */
					$optsComments = $modComments->getOptions();
					return [
						'title'            => __( 'Human Comment SPAM', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Your site is protected against Comment SPAM by humans.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Your site isn't protected against Comment SPAM by humans.", 'wp-simple-firewall' ),
						'href'             => $modComments->isModOptEnabled() ? $this->getJumpLink( 'enable_comments_human_spam_filter' ) : $this->getJumpLink( 'enable_comments_filter' ),
						'protected'        => $modComments->isModOptEnabled() && $optsComments->isEnabledHumanCheck(),
						'weight'           => 25,
					];
				},
				'tp_login_forms'           => function () {
					$modIntegrations = $this->getCon()->getModule_Integrations();
					/** @var Integrations\Lib\Bots\Common\BaseHandler[] $installedButNotEnabledProviders */
					$installedButNotEnabledProviders = array_filter(
						array_map(
							function ( $providerClass ) use ( $modIntegrations ) {
								return ( new $providerClass() )->setMod( $modIntegrations );
							},
							$modIntegrations->getController_UserForms()->enumProviders()
						),
						function ( $provider ) {
							/** @var Integrations\Lib\Bots\Common\BaseHandler $provider */
							return !$provider->isEnabled() && $provider::IsProviderInstalled();
						}
					);

					$names = empty( $installedButNotEnabledProviders ) ? '' :
						implode( ', ', array_map(
							function ( $provider ) {
								return $provider->getHandlerName();
							}, $installedButNotEnabledProviders
						) );

					return [
						'title'            => __( '3rd Party Login Forms', 'wp-simple-firewall' ),
						'desc_protected'   => __( "It appears that any 3rd party login forms you're using are protected against Bots.", 'wp-simple-firewall' ),
						'desc_unprotected' => sprintf( __( "It appears that certain 3rd party login forms aren't protected against Bots: %s", 'wp-simple-firewall' ), $names ),
						'href'             => $this->getJumpLink( 'user_form_providers' ),
						'protected'        => empty( $names ),
						'weight'           => 30,
					];
				},
				'contact_forms_spam'       => function () {
					$modIntegrations = $this->getCon()->getModule_Integrations();
					/** @var Integrations\Lib\Bots\Common\BaseHandler[] $installedButNotEnabledProviders */
					$installedButNotEnabledProviders = array_filter(
						array_map(
							function ( $providerClass ) use ( $modIntegrations ) {
								return ( new $providerClass() )->setMod( $modIntegrations );
							},
							$modIntegrations->getController_SpamForms()->enumProviders()
						),
						function ( $provider ) {
							/** @var Integrations\Lib\Bots\Common\BaseHandler $provider */
							return !$provider->isEnabled() && $provider::IsProviderInstalled();
						}
					);

					$names = empty( $installedButNotEnabledProviders ) ? '' :
						implode( ', ', array_map(
							function ( $provider ) {
								return $provider->getHandlerName();
							}, $installedButNotEnabledProviders
						) );

					return [
						'title'            => __( '3rd Party Contact Form SPAM', 'wp-simple-firewall' ),
						'desc_protected'   => __( "It appears that any contact forms you're using are protected against Bot SPAM.", 'wp-simple-firewall' ),
						'desc_unprotected' => sprintf( __( "It appears that certain contact forms aren't protected against Bot SPAM: %s", 'wp-simple-firewall' ), $names ),
						'href'             => $this->getJumpLink( 'form_spam_providers' ),
						'protected'        => empty( $names ),
						'weight'           => 30,
					];
				},
				'comment_approved_minimum' => function () {
					$modComments = $this->getCon()->getModule_Comments();
					/** @var CommentsFilter\Options $optsComments */
					$optsComments = $modComments->getOptions();
					return [
						'title'            => __( 'Minimum Comment Auto-Approval', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Comments are auto-approved only if they have at least 1 other approved comment.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Comments are auto-approved only if they have at least 1 other approved comment.", 'wp-simple-firewall' ),
						'href'             => $modComments->isModOptEnabled() ? $this->getJumpLink( 'trusted_commenter_minimum' ) : $this->getJumpLink( 'enable_comments_filter' ),
						'protected'        => $modComments->isModOptEnabled() && $optsComments->getApprovedMinimum() > 1,
						'weight'           => 10,
					];
				},
				'admin_user'               => function () {
					$WPUsers = Services::WpUsers();
					$adminUser = $WPUsers->getUserByUsername( 'admin' );
					return [
						'title'            => __( 'Default Admin User', 'wp-simple-firewall' ),
						'desc_protected'   => __( "The default 'admin' user is no longer available.", 'wp-simple-firewall' ),
						'desc_unprotected' => __( "The default 'admin' user is still available.", 'wp-simple-firewall' ),
						'href'             => $adminUser instanceof \WP_User ? $WPUsers->getAdminUrl_ProfileEdit( $adminUser ) : '',
						'protected'        => !$adminUser instanceof \WP_User || !user_can( $adminUser, 'manage_options' ),
						'weight'           => 5,
					];
				},
				'cooldown'                 => function () {
					$modLG = $this->getCon()->getModule_LoginGuard();
					/** @var LoginGuard\Options $optsLG */
					$optsLG = $modLG->getOptions();
					return [
						'title'            => __( 'Login Cooldown', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Login Cooldown system is helping prevent brute force attacks by limiting login attempts.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Brute force login attacks are not blocked by the login cooldown system.", 'wp-simple-firewall' ),
						'href'             => $modLG->isModOptEnabled() ? $this->getJumpLink( 'login_limit_interval' ) : $this->getJumpLink( 'enable_login_protect' ),
						'protected'        => $modLG->isModOptEnabled() && $optsLG->isEnabledCooldown(),
						'weight'           => 20,
					];
				},
				'ade_loginguard'           => function () {
					$modLG = $this->getCon()->getModule_LoginGuard();
					/** @var LoginGuard\Options $optsLG */
					$optsLG = $modLG->getOptions();
					return [
						'title'            => __( 'AntiBot Detection Engine For Logins', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'The AntiBot Detection Engine option is enabled.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( 'The AntiBot Detection Engine option is disabled, removing brute force protection for login, register and lost password forms.', 'wp-simple-firewall' ),
						'href'             => $modLG->isModOptEnabled() ? $this->getJumpLink( 'enable_antibot_check' ) : $this->getJumpLink( 'enable_login_protect' ),
						'protected'        => $modLG->isModOptEnabled() && $optsLG->isEnabledAntiBot(),
						'weight'           => 30,
					];
				},
				'ade_login'                => function () {
					$modLG = $this->getCon()->getModule_LoginGuard();
					/** @var LoginGuard\Options $optsLG */
					$optsLG = $modLG->getOptions();
					return [
						'title'            => __( 'Login Bot Protection', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Brute force bot attacks against your WordPress login are blocked by the AntiBot Detection Engine.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Brute force login attacks by bots aren't being blocked.", 'wp-simple-firewall' ),
						'href'             => $modLG->isModOptEnabled() ? $this->getJumpLink( 'bot_protection_locations' ) : $this->getJumpLink( 'enable_login_protect' ),
						'protected'        => $modLG->isModOptEnabled() && $optsLG->isEnabledAntiBot() && $optsLG->isProtectLogin(),
						'weight'           => 30,
					];
				},
				'ade_register'             => function () {
					$modLG = $this->getCon()->getModule_LoginGuard();
					/** @var LoginGuard\Options $optsLG */
					$optsLG = $modLG->getOptions();
					return [
						'title'            => __( 'Register Bot Protection', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'SPAM and bulk user registration by bots are blocked by the AntiBot Detection Engine.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "SPAM and bulk user registration by bots aren't being blocked.", 'wp-simple-firewall' ),
						'href'             => $modLG->isModOptEnabled() ? $this->getJumpLink( 'bot_protection_locations' ) : $this->getJumpLink( 'enable_login_protect' ),
						'protected'        => $modLG->isModOptEnabled() && $optsLG->isEnabledAntiBot() && $optsLG->isProtectRegister(),
						'weight'           => 30,
					];
				},
				'ade_lostpassword'         => function () {
					$modLG = $this->getCon()->getModule_LoginGuard();
					/** @var LoginGuard\Options $optsLG */
					$optsLG = $modLG->getOptions();
					return [
						'title'            => __( 'Lost Password Bot Protection', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Lost Password SPAMing by bots are blocked by the AntiBot Detection Engine.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Lost Password SPAMing by bots aren't being blocked.", 'wp-simple-firewall' ),
						'href'             => $modLG->isModOptEnabled() ? $this->getJumpLink( 'bot_protection_locations' ) : $this->getJumpLink( 'enable_login_protect' ),
						'protected'        => $modLG->isModOptEnabled() && $optsLG->isEnabledAntiBot() && $optsLG->isProtectLostPassword(),
						'weight'           => 30,
					];
				},
				'2fa'                      => function () {
					$modLG = $this->getCon()->getModule_LoginGuard();
					/** @var LoginGuard\Options $optsLG */
					$optsLG = $modLG->getOptions();
					return [
						'title'            => __( '2-Factor Authentication', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'At least 1 2FA option is available to help users protect their accounts.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "There are no 2FA options made available to help users protect their accounts.", 'wp-simple-firewall' ),
						'href'             => $modLG->isModOptEnabled() ? $this->getJumpLink( 'enable_email_authentication' ) : $this->getJumpLink( 'enable_login_protect' ),
						'protected'        => $modLG->isModOptEnabled()
											  && ( $optsLG->isEmailAuthenticationActive()
												   || $optsLG->isEnabledGoogleAuthenticator()
												   || $optsLG->isEnabledYubikey()
												   || $optsLG->isEnabledU2F() ),
						'weight'           => 30,
					];
				},
				'pass_policies'            => function () {
					$modUM = $this->getCon()->getModule_UserManagement();
					/** @var UserManagement\Options $optsUM */
					$optsUM = $modUM->getOptions();
					return [
						'title'            => __( 'Password Policies', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Password policies are enabled to help promote good password hygiene.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Password polices aren't enabled which may lead to poor password hygiene.", 'wp-simple-firewall' ),
						'href'             => $modUM->isModOptEnabled() ? $this->getJumpLink( 'enable_password_policies' ) : $this->getJumpLink( 'enable_user_management' ),
						'protected'        => $modUM->isModOptEnabled() && $optsUM->isPasswordPoliciesEnabled(),
						'weight'           => 30,
					];
				},
				'user_email_validation'    => function () {
					$modUM = $this->getCon()->getModule_UserManagement();
					/** @var UserManagement\Options $optsUM */
					$optsUM = $modUM->getOptions();
					return [
						'title'            => __( 'User Registration Email Validation', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Newly registered users have their email address checked for valid and non-SPAM domain names.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Newly registered users don't have their email address checked for valid and non-SPAM domain names.", 'wp-simple-firewall' ),
						'href'             => $modUM->isModOptEnabled() ? $this->getJumpLink( 'reg_email_validate' ) : $this->getJumpLink( 'enable_user_management' ),
						'protected'        => $modUM->isModOptEnabled() && $optsUM->isValidateEmailOnRegistration(),
						'weight'           => 30,
					];
				},
				'pass_pwned'               => function () {
					$modUM = $this->getCon()->getModule_UserManagement();
					/** @var UserManagement\Options $optsUM */
					$optsUM = $modUM->getOptions();
					return [
						'title'            => __( 'Pwned Passwords', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Pwned passwords are blocked from being set by any user.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Pwned passwords are allowed to be used.", 'wp-simple-firewall' ),
						'href'             => $modUM->isModOptEnabled() ? $this->getJumpLink( 'pass_prevent_pwned' ) : $this->getJumpLink( 'enable_user_management' ),
						'protected'        => $modUM->isModOptEnabled() && $optsUM->isPasswordPoliciesEnabled() && $optsUM->isPassPreventPwned(),
						'weight'           => 30,
					];
				},
				'pass_str'                 => function () {
					$modUM = $this->getCon()->getModule_UserManagement();
					/** @var UserManagement\Options $optsUM */
					$optsUM = $modUM->getOptions();
					return [
						'title'            => __( 'Strong Passwords', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'All new passwords are required to be be of high strength.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "There is no requirement for strong user passwords.", 'wp-simple-firewall' ),
						'href'             => $modUM->isModOptEnabled() ? $this->getJumpLink( 'pass_min_strength' ) : $this->getJumpLink( 'enable_user_management' ),
						'protected'        => $modUM->isModOptEnabled() && $optsUM->isPasswordPoliciesEnabled() && $optsUM->getPassMinStrength() >= 3,
						'weight'           => 20,
					];
				},
				'plugin_badge'             => function () {
					$modPlugin = $this->getCon()->getModule_Plugin();
					/** @var Plugin\Options $optsPlugin */
					$optsPlugin = $modPlugin->getOptions();
					return [
						'title'            => __( 'Plugin Security Badge', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Your customers and visitors are reassured that you take their security seriously.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Your customers and visitors aren't given reassurance that you take their security seriously.", 'wp-simple-firewall' ),
						'href'             => $modPlugin->isModOptEnabled() ? $this->getJumpLink( 'display_plugin_badge' ) : $this->getJumpLink( 'global_enable_plugin_features' ),
						'protected'        => $modPlugin->isModOptEnabled() && $optsPlugin->isOpt( 'display_plugin_badge', 'Y' ),
						'weight'           => 5,
					];
				},
				'db_password'              => function () {
					return [
						'title'            => __( 'MySQL DB Password', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'WP Database password is very strong.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "WP Database password appears to be weak.", 'wp-simple-firewall' ),
						'href'             => '',
						'protected'        => ( ( new Zxcvbn() )->passwordStrength( DB_PASSWORD )[ 'score' ] ?? 0 ) >= 4,
						'weight'           => 25,
					];
				},
				'activity_log_enabled'     => function () {
					$modAudit = $this->getCon()->getModule_AuditTrail();
					/** @var AuditTrail\Options $optsAudit */
					$optsAudit = $modAudit->getOptions();
					return [
						'title'            => __( 'Activity Logging', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Tracking changes with the Activity Log is enabled making it easier to track issues.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Tracking changes with the Activity Log is disabled making it harder to track issues.", 'wp-simple-firewall' ),
						'href'             => $modAudit->isModOptEnabled() ? $this->getJumpLink( 'section_localdb' ) : $this->getJumpLink( 'enable_audit_trail' ),
						'protected'        => $modAudit->isModOptEnabled() && $optsAudit->isLogToDB(),
						'weight'           => 25,
					];
				},
				'traffic_log_enabled'      => function () {
					$modTraffic = $this->getCon()->getModule_Traffic();
					/** @var Traffic\Options $optsTraffic */
					$optsTraffic = $modTraffic->getOptions();
					return [
						'title'            => __( 'Traffic Logging', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Traffic requests are being logged, making it easier to track issues.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Traffic requests aren't being logged, making it harder to track issues.", 'wp-simple-firewall' ),
						'href'             => $modTraffic->isModOptEnabled() ? $this->getJumpLink( 'enable_logger' ) : $this->getJumpLink( 'enable_traffic' ),
						'protected'        => $modTraffic->isModOptEnabled() && $optsTraffic->isTrafficLoggerEnabled(),
						'weight'           => 25,
					];
				},
				'traffic_rate_limiting'    => function () {
					$modTraffic = $this->getCon()->getModule_Traffic();
					/** @var Traffic\Options $optsTraffic */
					$optsTraffic = $modTraffic->getOptions();
					return [
						'title'            => __( 'Traffic Rate Limiting', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Traffic rate limiting is enabled reducing the likelihood that bots can overwhelm your site.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Traffic is never rate limited meaning abusive bots and crawlers may consume resources without limits and potentially overload your system.", 'wp-simple-firewall' ),
						'href'             => $modTraffic->isModOptEnabled() ? $this->getJumpLink( 'enable_limiter' ) : $this->getJumpLink( 'enable_traffic' ),
						'protected'        => $modTraffic->isModOptEnabled() && $optsTraffic->isTrafficLimitEnabled(),
						'weight'           => 35,
					];
				},
				'scanresults_apc'          => function () {
					$results = $this->getCon()->getModule_HackGuard()->getScansCon()->getScanResultsCount();
					$hasResults = $results->countAbandoned() > 0;
					return [
						'title'            => __( 'Abandoned Plugins Found', 'wp-simple-firewall' ),
						'desc_protected'   => __( "There doesn't appear to be any abandoned plugins on your site.", 'wp-simple-firewall' ),
						'desc_unprotected' => __( "There appears to be at least 1 abandoned plugin installed on your site.", 'wp-simple-firewall' ),
						'href'             => $this->getUrlForScanResults(),
						'protected'        => !$hasResults,
						'weight'           => $hasResults ? 45 : 10,
						'is_critical'      => $hasResults
					];
				},
				'scanresults_mal'          => function () {
					$results = $this->getCon()->getModule_HackGuard()->getScansCon()->getScanResultsCount();
					$hasResults = $results->countMalware() > 0;
					return [
						'title'            => $hasResults ? __( 'Potential Malware Found', 'wp-simple-firewall' ) : __( 'No Potential Malware Found', 'wp-simple-firewall' ),
						'desc_protected'   => __( "There doesn't appear to be any PHP malware files on your site.", 'wp-simple-firewall' ),
						'desc_unprotected' => __( "There appears to be at least 1 PHP malware file on your site.", 'wp-simple-firewall' ),
						'href'             => $this->getUrlForScanResults(),
						'protected'        => !$hasResults,
						'weight'           => $hasResults ? 55 : 10,
						'is_critical'      => $hasResults
					];
				},
				'scanresults_wcf'          => function () {
					$results = $this->getCon()->getModule_HackGuard()->getScansCon()->getScanResultsCount();
					$hasResults = $results->countWPFiles() > 0;
					return [
						'title'            => $hasResults ? __( 'WordPress Files Modified', 'wp-simple-firewall' ) : __( 'No Modified WordPress Files Found', 'wp-simple-firewall' ),
						'desc_protected'   => __( "All WordPress Core files appear to be clean and unmodified.", 'wp-simple-firewall' ),
						'desc_unprotected' => __( "At least 1 WordPress Core file appears to be modified or unrecognised.", 'wp-simple-firewall' ),
						'href'             => $this->getUrlForScanResults(),
						'protected'        => !$hasResults,
						'weight'           => $hasResults ? 55 : 10,
						'is_critical'      => $hasResults
					];
				},
				'scanresults_ptg'          => function () {
					$results = $this->getCon()->getModule_HackGuard()->getScansCon()->getScanResultsCount();
					$hasResults = ( $results->countPluginFiles() + $results->countThemeFiles() ) > 0;
					return [
						'title'            => $hasResults ? __( 'Modified Plugin/Theme Files Found', 'wp-simple-firewall' ) : __( 'No Modified Plugin/Theme Files Found', 'wp-simple-firewall' ),
						'desc_protected'   => __( "All plugin & theme files appear to be valid.", 'wp-simple-firewall' ),
						'desc_unprotected' => __( "At least 1 of your plugins or themes appears to be modified.", 'wp-simple-firewall' ),
						'href'             => $this->getUrlForScanResults(),
						'protected'        => !$hasResults,
						'weight'           => $hasResults ? 55 : 10,
						'is_critical'      => $hasResults
					];
				},
				'scanresults_wpv'          => function () {
					$results = $this->getCon()->getModule_HackGuard()->getScansCon()->getScanResultsCount();
					$hasResults = $results->countVulnerableAssets() > 0;
					return [
						'title'            => $hasResults ? __( 'Vulnerable Assets Found', 'wp-simple-firewall' ) : __( 'No Vulnerable Assets Found', 'wp-simple-firewall' ),
						'desc_protected'   => __( "There doesn't appear to be any plugins or themes with known vulnerabilities.", 'wp-simple-firewall' ),
						'desc_unprotected' => __( "There appears to be at least 1 vulnerable plugin or theme installed on your site.", 'wp-simple-firewall' ),
						'href'             => $this->getUrlForScanResults(),
						'protected'        => !$hasResults,
						'weight'           => $hasResults ? 55 : 10,
						'is_critical'      => $hasResults
					];
				},
				'report_email'             => function () {
					$modPlugin = $this->getCon()->getModule_Plugin();
					/** @var Plugin\Options $optsPlugin */
					$optsPlugin = $modPlugin->getOptions();
					return [
						'title'            => __( 'Report Email', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Email address has been provided for reporting important security notices.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "An email address hasn't been provided for reporting important security notices.", 'wp-simple-firewall' )
											  .' '.__( 'A default will be used.', 'wp-simple-firewall' ),
						'href'             => $this->getJumpLink( 'block_send_email_address' ),
						'protected'        => Services::Data()
													  ->validEmail( $optsPlugin->getOpt( 'block_send_email_address' ) ),
						'weight'           => 10,
					];
				},
				'headers'                  => function () {
					$modHeaders = $this->getCon()->getModule_Headers();
					/** @var Headers\Options $optsHeaders */
					$optsHeaders = $modHeaders->getOptions();
					return [
						'title'            => __( 'HTTP Headers', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Important HTTP Headers are helping to protect visitors.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Important HTTP Headers aren't being used to help protect visitors.", 'wp-simple-firewall' ),
						'href'             => $modHeaders->isModOptEnabled() ? $this->getJumpLink( 'section_security_headers' ) : $this->getJumpLink( 'enable_headers' ),
						'protected'        => $modHeaders->isModOptEnabled() && $optsHeaders->isEnabledXFrame()
											  && $optsHeaders->isEnabledXssProtection() && $optsHeaders->isEnabledContentTypeHeader()
											  && $optsHeaders->isReferrerPolicyEnabled(),
						'weight'           => 10,
					];
				},
				'file_scanner'             => function () {
					$modHG = $this->getCon()->getModule_HackGuard();
					$scansCon = $modHG->getScansCon();
					/** @var HackGuard\Scan\Controller\Afs $afsCon */
					$afsCon = $scansCon->getScanCon( HackGuard\Scan\Controller\Afs::SCAN_SLUG );
					return [
						'title'            => __( 'WordPress File Scanner', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'WordPress file scanner is enabled.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "WordPress file scanner isn't enabled.", 'wp-simple-firewall' ),
						'href'             => $modHG->isModOptEnabled() ? $this->getJumpLink( 'enable_core_file_integrity_scan' ) : $this->getJumpLink( 'enable_hack_protect' ),
						'protected'        => $modHG->isModOptEnabled() && $afsCon->isEnabled(),
						'weight'           => 40,
					];
				},
				'malware_scanner'          => function () {
					$modHG = $this->getCon()->getModule_HackGuard();
					$scansCon = $modHG->getScansCon();
					/** @var HackGuard\Scan\Controller\Afs $afsCon */
					$afsCon = $scansCon->getScanCon( HackGuard\Scan\Controller\Afs::SCAN_SLUG );
					return [
						'title'            => __( 'PHP Malware Scanner', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'PHP malware scanner is enabled.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "PHP malware scanner isn't enabled.", 'wp-simple-firewall' ),
						'href'             => $modHG->isModOptEnabled() ? $this->getJumpLink( 'enable_core_file_integrity_scan' ) : $this->getJumpLink( 'enable_hack_protect' ),
						'protected'        => $modHG->isModOptEnabled() && $afsCon->isEnabledMalwareScan(),
						'weight'           => 30,
					];
				},
				'apc_scanner'              => function () {
					$modHG = $this->getCon()->getModule_HackGuard();
					$scansCon = $modHG->getScansCon();
					return [
						'title'            => __( 'Abandoned WordPress.org Plugins', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Detection of abandoned WordPress.org plugins is enabled.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Detection of abandoned WordPress.org plugins isn't enabled.", 'wp-simple-firewall' ),
						'href'             => $modHG->isModOptEnabled() ? $this->getJumpLink( 'enabled_scan_apc' ) : $this->getJumpLink( 'enable_hack_protect' ),
						'protected'        => $modHG->isModOptEnabled()
											  && $scansCon->getScanCon( HackGuard\Scan\Controller\Apc::SCAN_SLUG )
														  ->isEnabled(),
						'weight'           => 30,
					];
				},
				'wpv_scanner'              => function () {
					$modHG = $this->getCon()->getModule_HackGuard();
					$scansCon = $modHG->getScansCon();
					return [
						'title'            => __( 'Vulnerable Plugins & Themes', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Plugins and Themes are scanned for known vulnerabilities.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Plugins and Themes aren't scanned for known vulnerabilities.", 'wp-simple-firewall' ),
						'href'             => $modHG->isModOptEnabled() ? $this->getJumpLink( 'enable_wpvuln_scan' ) : $this->getJumpLink( 'enable_hack_protect' ),
						'protected'        => $modHG->isModOptEnabled()
											  && $scansCon->getScanCon( HackGuard\Scan\Controller\Wpv::SCAN_SLUG )
														  ->isEnabled(),
						'weight'           => 40,
					];
				},
				'vuln_autoupdate'          => function () {
					$modHG = $this->getCon()->getModule_HackGuard();
					$scansCon = $modHG->getScansCon();
					/** @var HackGuard\Options $optsHG */
					$optsHG = $modHG->getOptions();
					return [
						'title'            => __( 'Auto-Update Vulnerable Plugins', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Plugins with known vulnerabilities are automatically updated to protect your site.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Plugins with known vulnerabilities aren't automatically updated to protect your site.", 'wp-simple-firewall' ),
						'href'             => $modHG->isModOptEnabled() ? $this->getJumpLink( 'wpvuln_scan_autoupdate' ) : $this->getJumpLink( 'enable_hack_protect' ),
						'protected'        => $modHG->isModOptEnabled()
											  && $scansCon->getScanCon( HackGuard\Scan\Controller\Wpv::SCAN_SLUG )
														  ->isEnabled()
											  && $optsHG->isWpvulnAutoupdatesEnabled(),
						'weight'           => 30,
					];
				},
				'auto_repair_core'         => function () {
					$modHG = $this->getCon()->getModule_HackGuard();
					/** @var HackGuard\Options $optsHG */
					$optsHG = $modHG->getOptions();
					$scansCon = $modHG->getScansCon();
					/** @var HackGuard\Scan\Controller\Afs $afsCon */
					$afsCon = $scansCon->getScanCon( HackGuard\Scan\Controller\Afs::SCAN_SLUG );
					return [
						'title'            => __( 'WordPress Core Auto-Repair', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Auto-repair of modified WordPress core files is enabled.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Auto-repair of modified WordPress core files isn't enabled.", 'wp-simple-firewall' ),
						'href'             => $modHG->isModOptEnabled() ? $this->getJumpLink( 'file_repair_areas' ) : $this->getJumpLink( 'enable_hack_protect' ),
						'protected'        => $modHG->isModOptEnabled() && $afsCon->isEnabled() && $optsHG->isRepairFileWP(),
						'weight'           => 30,
					];
				},
				'auto_repair_plugin'       => function () {
					$modHG = $this->getCon()->getModule_HackGuard();
					/** @var HackGuard\Options $optsHG */
					$optsHG = $modHG->getOptions();
					$scansCon = $modHG->getScansCon();
					/** @var HackGuard\Scan\Controller\Afs $afsCon */
					$afsCon = $scansCon->getScanCon( HackGuard\Scan\Controller\Afs::SCAN_SLUG );
					return [
						'title'            => __( 'WordPress.org Plugin Auto-Repair', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Auto-repair of files from WordPress.org plugins is enabled.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Auto-repair of files from WordPress.org plugins isn't enabled.", 'wp-simple-firewall' ),
						'href'             => $modHG->isModOptEnabled() ? $this->getJumpLink( 'file_repair_areas' ) : $this->getJumpLink( 'enable_hack_protect' ),
						'protected'        => $modHG->isModOptEnabled() && $afsCon->isEnabledPluginThemeScan() && $optsHG->isRepairFilePlugin(),
						'weight'           => 30,
					];
				},
				'auto_repair_theme'        => function () {
					$modHG = $this->getCon()->getModule_HackGuard();
					/** @var HackGuard\Options $optsHG */
					$optsHG = $modHG->getOptions();
					$scansCon = $modHG->getScansCon();
					/** @var HackGuard\Scan\Controller\Afs $afsCon */
					$afsCon = $scansCon->getScanCon( HackGuard\Scan\Controller\Afs::SCAN_SLUG );
					return [
						'title'            => __( 'WordPress.org Theme Auto-Repair', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Auto-repair of files from WordPress.org themes is enabled.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Auto-repair of files from WordPress.org themes isn't enabled.", 'wp-simple-firewall' ),
						'href'             => $modHG->isModOptEnabled() ? $this->getJumpLink( 'file_repair_areas' ) : $this->getJumpLink( 'enable_hack_protect' ),
						'protected'        => $modHG->isModOptEnabled() && $afsCon->isEnabledPluginThemeScan() && $optsHG->isRepairFileTheme(),
						'weight'           => 20,
					];
				},
				'scan_freq'                => function () {
					$modHG = $this->getCon()->getModule_HackGuard();
					/** @var HackGuard\Options $optsHG */
					$optsHG = $modHG->getOptions();
					return [
						'title'            => __( 'Scanning Frequency', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Scans are run against your site at least twice per day.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Scans are run against your site once per day at most.", 'wp-simple-firewall' ),
						'href'             => $modHG->isModOptEnabled() ? $this->getJumpLink( 'scan_frequency' ) : $this->getJumpLink( 'enable_hack_protect' ),
						'protected'        => $modHG->isModOptEnabled() && $optsHG->getScanFrequency() > 1,
						'weight'           => 10,
					];
				},
				'filelocker_wpconfig'      => function () {
					$modHG = $this->getCon()->getModule_HackGuard();
					/** @var HackGuard\Options $optsHG */
					$optsHG = $modHG->getOptions();
					$fileLocker = $modHG->getFileLocker();
					return [
						'title'            => sprintf( '%s - %s', 'wp-config.php', __( 'Protection', 'wp-simple-firewall' ) ),
						'desc_protected'   => sprintf( __( '%s is protected against tampering.', 'wp-simple-firewall' ), 'wp-config.php' ),
						'desc_unprotected' => sprintf( __( "%s isn't protected against tampering.", 'wp-simple-firewall' ), 'wp-config.php' ),
						'href'             => $modHG->isModOptEnabled() ? $this->getJumpLink( 'file_locker' ) : $this->getJumpLink( 'enable_hack_protect' ),
						'protected'        => $modHG->isModOptEnabled() && $fileLocker->isEnabled() && in_array( 'wpconfig', $optsHG->getFilesToLock() ),
						'weight'           => 30,
					];
				},
				'filelocker_htaccess'      => function () {
					$modHG = $this->getCon()->getModule_HackGuard();
					/** @var HackGuard\Options $optsHG */
					$optsHG = $modHG->getOptions();
					$fileLocker = $modHG->getFileLocker();
					return [
						'title'            => sprintf( '%s - %s', '.htaccess', __( 'Protection', 'wp-simple-firewall' ) ),
						'desc_protected'   => sprintf( __( '%s is protected against tampering.', 'wp-simple-firewall' ), '.htaccess' ),
						'desc_unprotected' => sprintf( __( "%s isn't protected against tampering.", 'wp-simple-firewall' ), '.htaccess' ),
						'href'             => $modHG->isModOptEnabled() ? $this->getJumpLink( 'file_locker' ) : $this->getJumpLink( 'enable_hack_protect' ),
						'protected'        => $modHG->isModOptEnabled() && $fileLocker->isEnabled() && in_array( 'root_htaccess', $optsHG->getFilesToLock() ),
						'weight'           => 30,
					];
				},
				'secadmin'                 => function () {
					$modSA = $this->getCon()->getModule_SecAdmin();
					$secAdminCon = $modSA->getSecurityAdminController();
					return [
						'title'            => __( 'Security Admin Protection', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'The security plugin is protected against tampering through use of a Security Admin PIN.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "The security plugin isn't protected against tampering through use of a Security Admin PIN.", 'wp-simple-firewall' ),
						'href'             => $modSA->isModOptEnabled() ? $this->getJumpLink( 'admin_access_key' ) : $this->getJumpLink( 'enable_admin_access_restriction' ),
						'protected'        => $modSA->isModOptEnabled() && $secAdminCon->isEnabledSecAdmin(),
						'weight'           => 40,
					];
				},
				'secadmin_admins'          => function () {
					$modSA = $this->getCon()->getModule_SecAdmin();
					/** @var SecurityAdmin\Options $optsSecAdmin */
					$optsSecAdmin = $modSA->getOptions();
					$secAdminCon = $modSA->getSecurityAdminController();
					return [
						'title'            => __( 'WordPress Admins Protection', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'WordPress admin accounts are protected against tampering from other WordPress admins.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "WordPress admin accounts aren't protected against tampering from other WordPress admins.", 'wp-simple-firewall' ),
						'href'             => $modSA->isModOptEnabled() ? $this->getJumpLink( 'admin_access_restrict_admin_users' ) : $this->getJumpLink( 'enable_admin_access_restriction' ),
						'protected'        => $modSA->isModOptEnabled()
											  && $secAdminCon->isEnabledSecAdmin() && $optsSecAdmin->isSecAdminRestrictUsersEnabled(),
						'weight'           => 20,
					];
				},
				'secadmin_options'         => function () {
					$modSA = $this->getCon()->getModule_SecAdmin();
					/** @var SecurityAdmin\Options $optsSecAdmin */
					$optsSecAdmin = $modSA->getOptions();
					$secAdminCon = $modSA->getSecurityAdminController();
					return [
						'title'            => __( 'WordPress Settings Protection', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Critical WordPress settings are protected against tampering from other WordPress admins.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Critical WordPress settings aren't protected against tampering from other WordPress admins.", 'wp-simple-firewall' ),
						'href'             => $modSA->isModOptEnabled() ? $this->getJumpLink( 'admin_access_restrict_options' ) : $this->getJumpLink( 'enable_admin_access_restriction' ),
						'protected'        => $modSA->isModOptEnabled()
											  && $secAdminCon->isEnabledSecAdmin() && $optsSecAdmin->isRestrictWpOptions(),
						'weight'           => 20,
					];
				},
				'lockdown_xmlrpc'          => function () {
					$modLock = $this->getCon()->getModule_Lockdown();
					/** @var Lockdown\Options $optsLockdown */
					$optsLockdown = $modLock->getOptions();
					return [
						'title'            => __( 'XML-RPC Access', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Access to XML-RPC is disabled.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Access to XML-RPC is available.", 'wp-simple-firewall' ),
						'href'             => $modLock->isModOptEnabled() ? $this->getJumpLink( 'disable_xmlrpc' ) : $this->getJumpLink( 'enable_lockdown' ),
						'protected'        => $modLock->isModOptEnabled() && $optsLockdown->isXmlrpcDisabled(),
						'weight'           => 30,
					];
				},
				'lockdown_file_editing'    => function () {
					$modLock = $this->getCon()->getModule_Lockdown();
					/** @var Lockdown\Options $optsLockdown */
					$optsLockdown = $modLock->getOptions();
					return [
						'title'            => __( 'WordPress File Editing', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Editing files from within the WordPress admin area is disabled.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Editing files from within the WordPress admin area is allowed.", 'wp-simple-firewall' ),
						'href'             => $modLock->isModOptEnabled() ? $this->getJumpLink( 'disable_file_editing' ) : $this->getJumpLink( 'enable_lockdown' ),
						'protected'        => $modLock->isModOptEnabled() && $optsLockdown->isOptFileEditingDisabled(),
						'weight'           => 30,
					];
				},
				'author_discovery'         => function () {
					$modLock = $this->getCon()->getModule_Lockdown();
					/** @var Lockdown\Options $optsLockdown */
					$optsLockdown = $modLock->getOptions();
					return [
						'title'            => sprintf( '%s / %s', __( 'Username Fishing', 'wp-simple-firewall' ), __( 'Author Discovery', 'wp-simple-firewall' ) ),
						'desc_protected'   => __( 'The ability to fish for WordPress usernames is disabled.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "The ability to fish for WordPress usernames isn't blocked.", 'wp-simple-firewall' ),
						'href'             => $modLock->isModOptEnabled() ? $this->getJumpLink( 'block_author_discovery' ) : $this->getJumpLink( 'enable_lockdown' ),
						'protected'        => $modLock->isModOptEnabled() && $optsLockdown->isBlockAuthorDiscovery(),
						'weight'           => 30,
					];
				},
				'anonymous_rest'           => function () {
					$modLock = $this->getCon()->getModule_Lockdown();
					/** @var Lockdown\Options $optsLockdown */
					$optsLockdown = $modLock->getOptions();
					return [
						'title'            => __( 'Anonymous REST API Access', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Anonymous/Unauthenticated access to the WordPress REST API is disabled.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Anonymous/Unauthenticated access to the WordPress REST API isn't blocked.", 'wp-simple-firewall' ),
						'href'             => $modLock->isModOptEnabled() ? $this->getJumpLink( 'disable_anonymous_restapi' ) : $this->getJumpLink( 'enable_lockdown' ),
						'protected'        => $modLock->isModOptEnabled() && $optsLockdown->isRestApiAnonymousAccessDisabled(),
						'weight'           => 20,
					];
				},
				'ip_autoblock'             => function () {
					$modIPs = $this->getCon()->getModule_IPs();
					/** @var IPs\Options $optsIPs */
					$optsIPs = $modIPs->getOptions();
					return [
						'title'            => __( 'IP Auto-Block', 'wp-simple-firewall' ),
						'desc_protected'   => sprintf( __( 'Auto IP blocking is turned on with an offense limit of %s.', 'wp-simple-firewall' ),
							$optsIPs->getOffenseLimit() ),
						'desc_unprotected' => __( 'Auto IP blocking is turned of as there is no offense limit provided.', 'wp-simple-firewall' ),
						'href'             => $modIPs->isModOptEnabled() ? $this->getJumpLink( 'transgression_limit' ) : $this->getJumpLink( 'enable_ips' ),
						'protected'        => $modIPs->isModOptEnabled() && $optsIPs->isEnabledAutoBlackList(),
						'weight'           => 50,
					];
				},
				'ip_autoblock_limit'       => function () {
					$modIPs = $this->getCon()->getModule_IPs();
					/** @var IPs\Options $optsIPs */
					$optsIPs = $modIPs->getOptions();
					return [
						'title'            => __( 'IP Auto-Block Offense Limit', 'wp-simple-firewall' ),
						'desc_protected'   => sprintf( __( "The maximum allowable offenses allowed before blocking is reasonably low: %s", 'wp-simple-firewall' ),
							$optsIPs->getOffenseLimit() ),
						'desc_unprotected' => sprintf( __( "Your maximum offense limit before blocking an IP seems high: %s", 'wp-simple-firewall' ),
							$optsIPs->getOffenseLimit() ),
						'href'             => $modIPs->isModOptEnabled() ? $this->getJumpLink( 'transgression_limit' ) : $this->getJumpLink( 'enable_ips' ),
						'protected'        => $modIPs->isModOptEnabled()
											  && $optsIPs->isEnabledAutoBlackList() && $optsIPs->getOffenseLimit() <= 10,
						'weight'           => 30,
					];
				},
				'ip_autoblock_crowdsec'    => function () {
					$modIPs = $this->getCon()->getModule_IPs();
					/** @var IPs\Options $optsIPs */
					$optsIPs = $modIPs->getOptions();
					return [
						'title'            => __( 'CrowdSec Community IP Blocking', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'Crowd-Sourced IP Blocking with CrowdSec is switched ON.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( 'Crowd-Sourced IP Blocking with CrowdSec is switched OFF.', 'wp-simple-firewall' ),
						'href'             => $modIPs->isModOptEnabled() ? $this->getJumpLink( 'cs_block' ) : $this->getJumpLink( 'enable_ips' ),
						'protected'        => $modIPs->isModOptEnabled() && $optsIPs->isEnabledCrowdSecAutoBlock(),
						'weight'           => 50,
					];
				},
				'ade_threshold'            => function () {
					$modIPs = $this->getCon()->getModule_IPs();
					/** @var IPs\Options $optsIPs */
					$optsIPs = $modIPs->getOptions();
					return [
						'title'            => __( 'AntiBot Detection Engine', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'AntiBot Detection Engine is enabled with a minimum bot-score threshold.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( 'AntiBot Detection Engine is disabled as there is no minimum bot-score threshold provided.', 'wp-simple-firewall' ),
						'href'             => $modIPs->isModOptEnabled() ? $this->getJumpLink( 'antibot_minimum' ) : $this->getJumpLink( 'enable_ips' ),
						'protected'        => $optsIPs->isEnabledAntiBotEngine(),
						'weight'           => 30,
					];
				},
				'track_404'                => function () {
					$modIPs = $this->getCon()->getModule_IPs();
					/** @var IPs\Options $optsIPs */
					$optsIPs = $modIPs->getOptions();
					return [
						'title'            => sprintf( '%s - %s', __( 'Bot Tracking', 'wp-simple-firewall' ), '404s' ),
						'desc_protected'   => __( 'Bots that trigger 404 errors are penalised.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Bots that trigger 404 errors aren't penalised.", 'wp-simple-firewall' ),
						'href'             => $modIPs->isModOptEnabled() ? $this->getJumpLink( 'track_404' ) : $this->getJumpLink( 'enable_ips' ),
						'protected'        => $modIPs->isModOptEnabled() && $optsIPs->getOffenseCountFor( 'track_404' ) > 0,
						'weight'           => 20,
					];
				},
				'track_loginfail'          => function () {
					$modIPs = $this->getCon()->getModule_IPs();
					/** @var IPs\Options $optsIPs */
					$optsIPs = $modIPs->getOptions();
					return [
						'title'            => sprintf( '%s - %s', __( 'Bot Tracking', 'wp-simple-firewall' ), __( 'Failed Logins', 'wp-simple-firewall' ) ),
						'desc_protected'   => __( 'Bots that attempt to login and fail are penalised.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Bots that attempt to login and fail aren't penalised.", 'wp-simple-firewall' ),
						'href'             => $modIPs->isModOptEnabled() ? $this->getJumpLink( 'track_loginfailed' ) : $this->getJumpLink( 'enable_ips' ),
						'protected'        => $modIPs->isModOptEnabled() && $optsIPs->getOffenseCountFor( 'track_loginfailed' ) > 0,
						'weight'           => 30,
					];
				},
				'track_logininvalid'       => function () {
					$modIPs = $this->getCon()->getModule_IPs();
					/** @var IPs\Options $optsIPs */
					$optsIPs = $modIPs->getOptions();
					return [
						'title'            => sprintf( '%s - %s', __( 'Bot Tracking', 'wp-simple-firewall' ), __( 'Invalid Logins', 'wp-simple-firewall' ) ),
						'desc_protected'   => __( 'Bots that attempt to login with non-existent usernames are penalised.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Bots that attempt to login with non-existent usernames aren't penalised.", 'wp-simple-firewall' ),
						'href'             => $modIPs->isModOptEnabled() ? $this->getJumpLink( 'track_logininvalid' ) : $this->getJumpLink( 'enable_ips' ),
						'protected'        => $modIPs->isModOptEnabled() && $optsIPs->getOffenseCountFor( 'track_logininvalid' ) > 0,
						'weight'           => 40,
					];
				},
				'track_xml'                => function () {
					$modIPs = $this->getCon()->getModule_IPs();
					/** @var IPs\Options $optsIPs */
					$optsIPs = $modIPs->getOptions();
					return [
						'title'            => sprintf( '%s - %s', __( 'Bot Tracking', 'wp-simple-firewall' ), 'XML-RPC' ),
						'desc_protected'   => __( 'Bots that attempt to access XML-RPC are penalised.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Bots that attempt to access XML-RPC aren't penalised.", 'wp-simple-firewall' ),
						'href'             => $modIPs->isModOptEnabled() ? $this->getJumpLink( 'track_xmlrpc' ) : $this->getJumpLink( 'enable_ips' ),
						'protected'        => $modIPs->isModOptEnabled() && $optsIPs->getOffenseCountFor( 'track_xmlrpc' ) > 0,
						'weight'           => 40,
					];
				},
				'track_fake'               => function () {
					$modIPs = $this->getCon()->getModule_IPs();
					/** @var IPs\Options $optsIPs */
					$optsIPs = $modIPs->getOptions();
					return [
						'title'            => sprintf( '%s - %s', __( 'Bot Tracking', 'wp-simple-firewall' ), __( 'Fake Web Crawlers', 'wp-simple-firewall' ) ),
						'desc_protected'   => __( 'Bots that pretend to be official web crawlers such as Google are penalised.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Bots that pretend to be official web crawlers such as Google aren't penalised.", 'wp-simple-firewall' ),
						'href'             => $modIPs->isModOptEnabled() ? $this->getJumpLink( 'track_fakewebcrawler' ) : $this->getJumpLink( 'enable_ips' ),
						'protected'        => $modIPs->isModOptEnabled() && $optsIPs->getOffenseCountFor( 'track_fakewebcrawler' ) > 0,
						'weight'           => 30,
					];
				},
				'track_cheese'             => function () {
					$modIPs = $this->getCon()->getModule_IPs();
					/** @var IPs\Options $optsIPs */
					$optsIPs = $modIPs->getOptions();
					return [
						'title'            => sprintf( '%s - %s', __( 'Bot Tracking', 'wp-simple-firewall' ), __( 'Link-Cheese', 'wp-simple-firewall' ) ),
						'desc_protected'   => __( 'Bots that trigger the link-cheese bait are penalised.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Bots that trigger the link-cheese bait aren't penalised.", 'wp-simple-firewall' ),
						'href'             => $modIPs->isModOptEnabled() ? $this->getJumpLink( 'track_linkcheese' ) : $this->getJumpLink( 'enable_ips' ),
						'protected'        => $modIPs->isModOptEnabled() && $optsIPs->getOffenseCountFor( 'track_linkcheese' ) > 0,
						'weight'           => 20,
					];
				},
				'track_script'             => function () {
					$modIPs = $this->getCon()->getModule_IPs();
					/** @var IPs\Options $optsIPs */
					$optsIPs = $modIPs->getOptions();
					return [
						'title'            => sprintf( '%s - %s', __( 'Bot Tracking', 'wp-simple-firewall' ), __( 'Invalid Scripts', 'wp-simple-firewall' ) ),
						'desc_protected'   => __( 'Bots that attempt to access invalid scripts or WordPress files are penalised.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Bots that attempt to access invalid scripts or WordPress files aren't penalised.", 'wp-simple-firewall' ),
						'href'             => $modIPs->isModOptEnabled() ? $this->getJumpLink( 'track_invalidscript' ) : $this->getJumpLink( 'enable_ips' ),
						'protected'        => $modIPs->isModOptEnabled() && $optsIPs->getOffenseCountFor( 'track_invalidscript' ) > 0,
						'weight'           => 20,
					];
				},
				'plugins_inactive'         => function () {
					$WPP = Services::WpPlugins();
					$countPluginsInactive = count( $WPP->getPlugins() ) - count( $WPP->getActivePlugins() );
					return [
						'title'            => __( 'Inactive Plugins', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'All installed plugins appear to be active and in-use.', 'wp-simple-firewall' ),
						'desc_unprotected' => sprintf( __( 'There are %s inactive and unused plugins.', 'wp-simple-firewall' ), $countPluginsInactive ),
						'href'             => add_query_arg( [ 'plugin_status' => 'inactive' ],
							Services::WpGeneral()->getAdminUrl_Plugins( true ) ),
						'protected'        => $countPluginsInactive === 0,
						'weight'           => 25,
					];
				},
				'plugins_updates'          => function () {
					$WPP = Services::WpPlugins();
					$countPluginsUpdates = count( $WPP->getUpdates() );
					return [
						'title'            => __( 'Plugins With Updates', 'wp-simple-firewall' ),
						'desc_protected'   => __( "All available plugin updates have been applied.", 'wp-simple-firewall' ),
						'desc_unprotected' => sprintf( __( 'There are %s plugin updates waiting to be applied.', 'wp-simple-firewall' ), $countPluginsUpdates ),
						'href'             => add_query_arg( [ 'plugin_status' => 'upgrade' ],
							Services::WpGeneral()->getAdminUrl_Plugins( true ) ),
						'protected'        => $countPluginsUpdates === 0,
						'weight'           => 45,
					];
				},
				'themes_inactive'          => function () {
					$WPT = Services::WpThemes();
					$countThemesInactive = count( $WPT->getThemes() ) - ( $WPT->isActiveThemeAChild() ? 2 : 1 );
					return [
						'title'            => __( 'Inactive Themes', 'wp-simple-firewall' ),
						'desc_protected'   => __( "All installed themes appear to be active and in-use.", 'wp-simple-firewall' ),
						'desc_unprotected' => sprintf( __( 'There are %s inactive and unused themes.', 'wp-simple-firewall' ), $countThemesInactive ),
						'href'             => add_query_arg( [ 'plugin_status' => 'upgrade' ],
							Services::WpGeneral()->getAdminUrl_Themes( true ) ),
						'protected'        => $countThemesInactive === 0,
						'weight'           => 25,
					];
				},
				'themes_updates'           => function () {
					$WPT = Services::WpThemes();
					$countThemesUpdates = count( $WPT->getUpdates() );
					return [
						'title'            => __( 'Themes With Updates', 'wp-simple-firewall' ),
						'desc_protected'   => __( "All available theme updates have been applied.", 'wp-simple-firewall' ),
						'desc_unprotected' => sprintf( __( 'There are %s theme updates waiting to be applied.', 'wp-simple-firewall' ), $countThemesUpdates ),
						'href'             => Services::WpGeneral()->getAdminUrl_Themes( true ),
						'protected'        => $countThemesUpdates === 0,
						'weight'           => 45,
					];
				},
				'autoupdate_core'          => function () {
					return [
						'title'            => __( 'WordPress Core Automatic Updates', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'WordPress Core is automatically updated when minor upgrades are released.', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "WordPress Core isn't automatically updated when minor upgrades are released.", 'wp-simple-firewall' ),
						'href'             => $this->getJumpLink( $this->getCon()->getModule_Autoupdates()->getSlug() ),
						'protected'        => Services::WpGeneral()->canCoreUpdateAutomatically(),
						'weight'           => 50,
					];
				},
				'users_inactive'           => function () {
					$modUM = $this->getCon()->getModule_UserManagement();
					/** @var UserManagement\Options $optsUM */
					$optsUM = $modUM->getOptions();
					return [
						'title'            => __( 'Inactive User Accounts', 'wp-simple-firewall' ),
						'desc_protected'   => sprintf( __( 'Inactive user accounts are automatically suspended after %s.', 'wp-simple-firewall' ), $optsUM->getOpt( 'auto_idle_days' ) ),
						'desc_unprotected' => __( 'There is currently no control over how inactive user accounts are handled.', 'wp-simple-firewall' ),
						'href'             => $modUM->isModOptEnabled() ? $this->getJumpLink( 'auto_idle_days' ) : $this->getJumpLink( 'enable_user_management' ),
						'protected'        => $modUM->isModOptEnabled() && $optsUM->getOpt( 'auto_idle_days' ) > 0,
						'weight'           => 20,
					];
				},
				'sessions_idle'            => function () {
					$modUM = $this->getCon()->getModule_UserManagement();
					/** @var UserManagement\Options $optsUM */
					$optsUM = $modUM->getOptions();
					return [
						'title'            => __( 'Idle User Sessions', 'wp-simple-firewall' ),
						'desc_protected'   => sprintf( 'Idle user sessions are always automatically logged out after %s hours.', $optsUM->getOpt( 'session_idle_timeout_interval' ) ),
						'desc_unprotected' => __( 'There is currently no control over how idle user sessions are handled.', 'wp-simple-firewall' ),
						'href'             => $modUM->isModOptEnabled() ? $this->getJumpLink( 'session_idle_timeout_interval' ) : $this->getJumpLink( 'enable_user_management' ),
						'protected'        => $modUM->isModOptEnabled() && $optsUM->hasSessionIdleTimeout(),
						'weight'           => 20,
					];
				},
				'ssl_certificate'          => function () {
					$WP = Services::WpGeneral();
					$srvSSL = new Ssl();

					$ssl = [
						'title'            => __( 'SSL Certificate', 'wp-simple-firewall' ),
						'desc_protected'   => __( 'SSL Certificate remains valid for at least the next 2 weeks', 'wp-simple-firewall' ),
						'desc_unprotected' => __( "Visitors aren't protected with a valid SSL Certificate.", 'wp-simple-firewall' ),
						'href'             => add_query_arg(
							[
								'action' => Services::WpGeneral()->getHomeUrl(),
								'run'    => 'toolpage'
							],
							'https://mxtoolbox.com/SuperTool.aspx'
						),
						'protected'        => false,
						'weight'           => 45,
					];

					// SSL Expires
					$homeURL = $WP->getHomeUrl();
					$isHomeSsl = strpos( $homeURL, 'https://' ) === 0;

					if ( !$isHomeSsl ) {
						$ssl[ 'desc_unprotected' ] = __( "Visitors aren't protected with a valid SSL Certificate.", 'wp-simple-firewall' );
					}
					elseif ( strpos( $WP->getWpUrl(), 'https://' ) !== 0 ) {
						$ssl[ 'desc_unprotected' ] = __( "HTTPS setting for Home URL and WP Site URL aren't consistent.", 'wp-simple-firewall' );
						$ssl[ 'href' ] = $WP->getAdminUrl_Settings();
					}
					elseif ( $srvSSL->isEnvSupported() ) {
						$srvSSL = new Ssl();
						try {
							// first verify SSL cert:
							$srvSSL->getCertDetailsForDomain( $homeURL );

							// If we didn't throw an exception, we got it.
							$expiresAt = $srvSSL->getExpiresAt( $homeURL );
							if ( $expiresAt > 0 ) {
								$timeRemaining = ( $expiresAt - Services::Request()->ts() );
								$isExpired = $timeRemaining < 0;
								$daysLeft = $isExpired ? 0 : (int)round( $timeRemaining/DAY_IN_SECONDS, 0, PHP_ROUND_HALF_DOWN );

								if ( $daysLeft < 15 ) {
									if ( $isExpired ) {
										$ssl[ 'desc_unprotected' ] = __( 'SSL certificate for this site has expired.', 'wp-simple-firewall' );
									}
									else {
										$ssl[ 'desc_unprotected' ] = sprintf( __( 'SSL certificate will expire soon (%s days)', 'wp-simple-firewall' ), $daysLeft );
									}
								}
								else {
									$ssl[ 'protected' ] = true;
								}
							}
						}
						catch ( \Exception $e ) {
							$ssl[ 'desc_unprotected' ] = sprintf( '%s: %s', __( "Couldn't automatically test and verify your site SSL certificate", 'wp-simple-firewall' ), $e->getMessage() );
						}
					}
					else {
						$ssl[ 'protected' ] = true;
					}

					return $ssl;
				},
			]
		);
	}

	private function getUrlForScanResults() :string {
		return $this->getCon()->getModule_Insights()->getUrl_ScansResults();
	}

	/**
	 * @alias
	 */
	public function getJumpLink( string $for ) :string {
		return $this->getCon()->getModule_Plugin()->getUIHandler()->getOffCanvasJavascriptLinkFor( $for );
	}
}