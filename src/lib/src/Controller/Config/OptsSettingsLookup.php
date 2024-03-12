<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config;

use FernleafSystems\Wordpress\Plugin\Shield\Enum\EnumModules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class OptsSettingsLookup {

	use PluginControllerConsumer;

	public function enabledAntiBotCommentSpam() :bool {
		return $this->optIsAndModForOptEnabled( 'enable_antibot_comments', 'Y' );
	}

	public function enabledAntiBotEngine() :bool {
		return $this->getAntiBotMinScore() > 0;
	}

	public function enabledHumanCommentSpam() :bool {
		return $this->optIsAndModForOptEnabled( 'enable_comments_human_spam_filter', 'Y' );
	}

	public function enabledCrowdSecAutoBlock() :bool {
		return !self::con()->opts->optIs( 'cs_block', 'disabled' ) && $this->isModFromOptEnabled( 'cs_block' );
	}

	public function enabledCrowdSecAutoUnblock() :bool {
		return $this->optIsAndModForOptEnabled( 'cs_block', 'block_with_unblock' );
	}

	public function enabledIpAutoBlock() :bool {
		return $this->getIpAutoBlockOffenseLimit() > 0;
	}

	public function enabledLoginGuardAntiBotCheck() :bool {
		return $this->optIsAndModForOptEnabled( 'enable_antibot_check', 'Y' );
	}

	public function enabledLoginGuardGaspCheck() :bool {
		return !$this->enabledLoginGuardAntiBotCheck() && $this->optIsAndModForOptEnabled( 'enable_login_gasp_check', 'Y' );
	}

	public function enabledIntegrationMainwp() :bool {
		return $this->optIsAndModForOptEnabled( 'enable_mainwp', 'Y' );
	}

	/**
	 * @param string $area - login, register, password, woocommerce
	 */
	public function enabledLoginProtectionArea( string $area ) :bool {
		return $this->enabledLoginGuardAntiBotCheck() && \in_array( $area, self::con()->opts->optGet( 'bot_protection_locations' ) );
	}

	public function enabledTelemetry() :bool {
		return self::con()->isPremiumActive() || $this->optIsAndModForOptEnabled( 'enable_tracking', 'Y' );
	}

	public function enabledTrafficLimiter() :bool {
		$opts = self::con()->opts;
		return $this->enabledTrafficLogger()
			   && $opts->optIs( 'enable_limiter', 'Y' )
			   && $opts->optGet( 'limit_time_span' ) > 0
			   && $opts->optGet( 'limit_requests' ) > 0;
	}

	public function enabledTrafficLogger() :bool {
		return $this->optIsAndModForOptEnabled( 'enable_logger', 'Y' );
	}

	public function getActivatedPeriod() :int {
		return Services::Request()->ts() - self::con()->opts->optGet( 'activated_at' );
	}

	public function getAntiBotMinScore() :int {
		return $this->isModFromOptEnabled( 'antibot_minimum' ) ?
			(int)apply_filters( 'shield/antibot_score_minimum', self::con()->opts->optGet( 'antibot_minimum' ) ) : 0;
	}

	public function getBlockdownCfg() :array {
		return \array_merge( [
			'activated_at' => 0,
			'activated_by' => '',
			'disabled_at'  => 0,
			'exclusions'   => [],
			'whitelist_me' => '',
		], self::con()->opts->optGet( 'blockdown_cfg' ) );
	}

	public function getBotTrackOffenseCountFor( string $key ) :int {
		$count = 0;
		if ( $this->isModFromOptEnabled( $key ) ) {
			$optValue = self::con()->opts->optGet( $key );
			if ( $optValue === 'transgression-double' ) {
				$count = 2;
			}
			elseif ( \in_array( $optValue, [ 'transgression-single', 'block' ] ) ) {
				$count = 1;
			}
		}
		return $count;
	}

	/**
	 * @return string[]
	 */
	public function getCommentTrustedRoles() :array {
		return self::con()->isPremiumActive() ? self::con()->opts->optGet( 'trusted_user_roles' ) : [];
	}

	public function getCommenterTrustedMinimum() :int {
		return $this->isModFromOptEnabled( 'trusted_commenter_minimum' ) ? self::con()->opts->optGet( 'trusted_commenter_minimum' ) : 1;
	}

	public function getEmailValidateChecks() :array {
		$con = self::con();
		return ( $this->isModFromOptEnabled( 'email_checks' ) && $con->opts->optGet( 'reg_email_validate' ) !== 'disabled'
				 && $con->isPremiumActive() ) ? $con->opts->optGet( 'email_checks' ) : [];
	}

	public function getIpAutoBlockTTL() :int {
		return (int)constant( \strtoupper( self::con()->opts->optGet( 'auto_expire' ).'_IN_SECONDS' ) );
	}

	public function getIpAutoBlockOffenseLimit() :int {
		return $this->isModFromOptEnabled( 'transgression_limit' ) ? (int)self::con()->opts->optGet( 'transgression_limit' ) : 0;
	}

	public function getLoginGuardGaspKey() :string {
		$key = self::con()->opts->optGet( 'gasp_key' );
		if ( empty( $key ) ) {
			$key = \uniqid();
			self::con()->opts->optSet( 'gasp_key', $key );
		}
		return self::con()->prefix( $key );
	}

	public function getLoginGuardEmailAuth2FaRoles() :array {
		$roles = apply_filters( 'shield/2fa_email_enforced_user_roles', self::con()->opts->optGet( 'two_factor_auth_user_roles' ) );
		return \array_unique( \array_filter( \array_map( 'sanitize_key',
			\is_array( $roles ) ? $roles : self::con()->opts->optDefault( 'two_factor_auth_user_roles' )
		) ) );
	}

	public function getPassExpireTimeout() :int {
		return $this->isModFromOptEnabled( 'pass_expire' ) ? self::con()->opts->optGet( 'pass_expire' )*\DAY_IN_SECONDS : 0;
	}

	public function getReportEmail() :string {
		$e = self::con()->opts->optGet( 'block_send_email_address' );
		if ( self::con()->isPremiumActive() ) {
			$e = apply_filters( 'shield/report_email', $e );
		}
		$e = \trim( $e );
		return Services::Data()->validEmail( $e ) ? $e : Services::WpGeneral()->getSiteAdminEmail();
	}

	public function getSecAdminPIN() :string {
		return self::con()->opts->optGet( 'admin_access_key' );
	}

	public function getSecAdminWpOptionsToRestrict() :array {
		$def = self::con()->cfg->configuration->def( 'options_to_restrict' );
		return $def[ ( Services::WpGeneral()->isMultisite() ? 'wpms' : 'wp' ).'_options' ] ?? [];
	}

	public function getSessionIdleInterval() :int {
		return self::con()->opts->optGet( 'session_idle_timeout_interval' )*\HOUR_IN_SECONDS;
	}

	public function getSessionMax() :int {
		return self::con()->opts->optGet( 'session_timeout_interval' )*\DAY_IN_SECONDS;
	}

	public function getTrafficAutoClean() :int {
		$con = self::con();
		$days = $con->opts->optGet( 'auto_clean' );
		if ( $days !== $con->caps->getMaxLogRetentionDays() ) {
			$days = (int)\min( $days, $con->caps->getMaxLogRetentionDays() );
			$con->opts->optSet( 'auto_clean', $days );
		}
		return $days;
	}

	public function getTrafficLiveLogTimeRemaining() :int {
		$opts = self::con()->opts;
		$now = Services::Request()->ts();

		if ( $opts->optIs( 'enable_live_log', 'Y' ) ) {
			if ( $opts->optGet( 'live_log_started_at' ) > 0 ) {
				if ( $this->getTrafficLiveLogDuration() <= $now - $opts->optGet( 'live_log_started_at' ) ) {
					$opts->optSet( 'live_log_started_at', 0 )
						 ->optSet( 'enable_live_log', 'N' );
				}
			}
			elseif ( $opts->optGet( 'live_log_started_at' ) === 0 ) {
				$opts->optSet( 'live_log_started_at', $now );
			}
		}
		else {
			$opts->optSet( 'live_log_started_at', 0 );
		}

		$startedAt = $opts->optGet( 'live_log_started_at' );
		return $startedAt > 0 ? \max( 0, $this->getTrafficLiveLogDuration() - ( $now - $startedAt ) ) : 0;
	}

	public function getTrafficLiveLogDuration() :int {
		return (int)\min(
			\DAY_IN_SECONDS,
			\max( \MINUTE_IN_SECONDS, apply_filters( 'shield/live_traffic_log_duration', \HOUR_IN_SECONDS/2 ) )
		);
	}

	public function ipSource() :string {
		return self::con()->opts->optGet( 'visitor_address_source' );
	}

	public function isBotTrackImmediateBlock( string $key ) :bool {
		return $this->optIsAndModForOptEnabled( $key, 'block' );
	}

	public function isScanAutoFilterResults() :bool {
		return (bool)apply_filters( 'shield/scan_auto_filter_results', true );
	}

	public function isModFromOptEnabled( string $optKey ) :bool {
		return $this->isModEnabled( self::con()->cfg->configuration->modFromOpt( $optKey ) );
	}

	public function isModEnabled( string $slug ) :bool {
		return self::con()->opts->optIs( $slug === EnumModules::PLUGIN ? 'global_enable_plugin_features' : 'enable_'.$slug, 'Y' );
	}

	public function isPassPoliciesEnabled() :bool {
		return $this->optIsAndModForOptEnabled( 'enable_password_policies', 'Y' );
	}

	public function isPassPreventPwned() :bool {
		return $this->isPassPoliciesEnabled() && self::con()->opts->optIs( 'pass_prevent_pwned', 'Y' );
	}

	public function isPluginGloballyDisabled() :bool {
		return !self::con()->opts->optIs( 'global_enable_plugin_features', 'Y' );
	}

	/**
	 * shortcut for doing optIs() check alongside module enabled check for said option.
	 */
	public function optIsAndModForOptEnabled( string $optKey, $optIs ) :bool {
		return self::con()->opts->optIs( $optKey, $optIs ) && $this->isModFromOptEnabled( $optKey );
	}
}