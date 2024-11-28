<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class OptsLookup {

	use PluginControllerConsumer;

	public function enabledAntiBotCommentSpam() :bool {
		return self::con()->opts->optIs( 'enable_antibot_comments', 'Y' );
	}

	public function enabledAntiBotEngine() :bool {
		return $this->getAntiBotMinScore() > 0;
	}

	public function enabledHumanCommentSpam() :bool {
		return self::con()->opts->optIs( 'enable_comments_human_spam_filter', 'Y' );
	}

	public function enabledCrowdSecAutoBlock() :bool {
		return !self::con()->opts->optIs( 'cs_block', 'disabled' );
	}

	public function enabledCrowdSecAutoUnblock() :bool {
		return self::con()->opts->optIs( 'cs_block', 'block_with_unblock' );
	}

	public function enabledIpAutoBlock() :bool {
		return $this->getIpAutoBlockOffenseLimit() > 0;
	}

	public function enabledIntegrationMainwp() :bool {
		return self::con()->opts->optIs( 'enable_mainwp', 'Y' );
	}

	/**
	 * @param string $area - login, register, password, woocommerce
	 */
	public function enabledLoginProtectionArea( string $area ) :bool {
		return \in_array( $area, self::con()->opts->optGet( 'bot_protection_locations' ) );
	}

	public function enabledTelemetry() :bool {
		return self::con()->isPremiumActive() || self::con()->opts->optIs( 'enable_tracking', 'Y' );
	}

	public function enabledTrafficLimiter() :bool {
		$opts = self::con()->opts;
		return $this->enabledTrafficLogger()
			   && $opts->optIs( 'enable_limiter', 'Y' )
			   && $opts->optGet( 'limit_time_span' ) > 0
			   && $opts->optGet( 'limit_requests' ) > 0;
	}

	public function enabledTrafficLogger() :bool {
		return self::con()->opts->optIs( 'enable_logger', 'Y' );
	}

	public function getActivatedPeriod() :int {
		return Services::Request()->ts() - self::con()->opts->optGet( 'activated_at' );
	}

	public function getAntiBotMinScore() :int {
		return (int)apply_filters( 'shield/antibot_score_minimum', self::con()->opts->optGet( 'antibot_minimum' ) );
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
		if ( $this->isPluginEnabled() ) {
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
		return self::con()->opts->optGet( 'trusted_commenter_minimum' );
	}

	public function getEmailValidateChecks() :array {
		$con = self::con();
		return ( $con->opts->optGet( 'reg_email_validate' ) !== 'disabled' && $con->isPremiumActive() ) ? $con->opts->optGet( 'email_checks' ) : [];
	}

	/**
	 * Structure of stored data changed with 19.1, so this method handles old & new. It'll resave it as the newer
	 * format.
	 */
	public function getFirewallParametersWhitelist() :array {
		$list = [];
		$raw = self::con()->opts->optGet( 'page_params_whitelist' );
		if ( !empty( $raw ) ) {
			$reconstructed = [];
			foreach ( \array_filter( $raw ) as $idxOrPage => $paramsLineOrArray ) {

				$page = null;
				$params = null;

				if ( \is_string( $paramsLineOrArray ) ) {
					$parts = \array_map( '\trim', \explode( ',', $paramsLineOrArray, 2 ) );
					if ( \count( $parts ) === 2 ) {
						[ $page, $params ] = $parts;
						$params = \array_map( '\trim', \explode( ',', $params ) );
					}
				}
				elseif ( \is_array( $paramsLineOrArray ) && !\is_numeric( $idxOrPage ) ) {
					$page = $idxOrPage;
					$params = $paramsLineOrArray;
				}

				if ( !empty( $page ) && !empty( $params ) ) {
					$list[ $page ] = $params;
					$reconstructed[] = \implode( ',', \array_merge( [ $page ], $params ) );
				}
			}

			self::con()->opts->optSet( 'page_params_whitelist', $reconstructed );
		}
		return $list;
	}

	public function getInstalledAt() :int {
		return (int)self::con()->opts->optGet( 'installation_time' );
	}

	public function getIpAutoBlockTTL() :int {
		return (int)\constant( \strtoupper( self::con()->opts->optGet( 'auto_expire' ).'_IN_SECONDS' ) );
	}

	public function getIpAutoBlockOffenseLimit() :int {
		return self::con()->opts->optGet( 'transgression_limit' );
	}

	public function getLoginGuardEmailAuth2FaRoles() :array {
		$roles = apply_filters( 'shield/2fa_email_enforced_user_roles', self::con()->opts->optGet( 'two_factor_auth_user_roles' ) );
		return \array_unique( \array_filter( \array_map( 'sanitize_key',
			\is_array( $roles ) ? $roles : self::con()->opts->optDefault( 'two_factor_auth_user_roles' )
		) ) );
	}

	public function getPassExpireTimeout() :int {
		return self::con()->opts->optGet( 'pass_expire' )*\DAY_IN_SECONDS;
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

	public function getXferExcluded() :array {
		return self::con()->opts->optGet( 'xfer_excluded' );
	}

	public function ipSource() :string {
		return self::con()->opts->optGet( 'visitor_address_source' );
	}

	public function isBotTrackImmediateBlock( string $key ) :bool {
		return self::con()->opts->optIs( $key, 'block' );
	}

	public function isScanAutoFilterResults() :bool {
		return (bool)apply_filters( 'shield/scan_auto_filter_results', true );
	}

	public function isPluginEnabled() :bool {
		return self::con()->opts->optIs( 'global_enable_plugin_features', 'Y' );
	}

	public function isPassPoliciesEnabled() :bool {
		return self::con()->opts->optIs( 'enable_password_policies', 'Y' );
	}

	public function isPassPreventPwned() :bool {
		return $this->isPassPoliciesEnabled() && self::con()->opts->optIs( 'pass_prevent_pwned', 'Y' );
	}
}