<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class OptsSettingsLookup {

	use PluginControllerConsumer;

	public function enabledAntiBotCommentSpam() :bool {
		return self::con()->opts->optIs( 'enable_antibot_comments', 'Y' )
			   && $this->isModFromOptEnabled( 'enable_antibot_comments' );
	}

	public function enabledAntiBotEngine() :bool {
		return $this->getAntiBotMinScore() > 0;
	}

	public function enabledHumanCommentSpam() :bool {
		return self::con()->opts->optIs( 'enable_comments_human_spam_filter', 'Y' )
			   && $this->isModFromOptEnabled( 'enable_comments_human_spam_filter' );
	}

	public function enabledCrowdSecAutoBlock() :bool {
		return !self::con()->opts->optIs( 'cs_block', 'disabled' ) && $this->isModFromOptEnabled( 'cs_block' );
	}

	public function enabledCrowdSecAutoUnblock() :bool {
		return self::con()->opts->optIs( 'cs_block', 'block_with_unblock' ) && $this->isModFromOptEnabled( 'cs_block' );
	}

	public function enabledIpAutoBlock() :bool {
		return $this->getIpAutoBlockOffenseLimit() > 0;
	}

	public function enabledIntegrationMainwp() :bool {
		return self::con()->opts->optIs( 'enable_mainwp', 'Y' ) && $this->isModFromOptEnabled( 'enable_mainwp' );
	}

	public function getAntiBotMinScore() :int {
		return $this->isModFromOptEnabled( 'antibot_minimum' ) ?
			(int)apply_filters( 'shield/antibot_score_minimum', self::con()->opts->optGet( 'antibot_minimum' ) ) : 0;
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

	public function getIpAutoBlockTTL() :int {
		return (int)constant( \strtoupper( self::con()->opts->optGet( 'auto_expire' ).'_IN_SECONDS' ) );
	}

	public function getIpAutoBlockOffenseLimit() :int {
		return $this->isModFromOptEnabled( 'transgression_limit' ) ? (int)self::con()->opts->optGet( 'transgression_limit' ) : 0;
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

	public function getSessionIdleInterval() :int {
		return self::con()->opts->optGet( 'session_idle_timeout_interval' )*\HOUR_IN_SECONDS;
	}

	public function getSessionMax() :int {
		return self::con()->opts->optGet( 'session_timeout_interval' )*\DAY_IN_SECONDS;
	}

	public function isBotTrackImmediateBlock( string $key ) :bool {
		return self::con()->opts->optIs( $key, 'block' ) && $this->isModFromOptEnabled( $key );
	}

	public function isModFromOptEnabled( string $optKey ) :bool {
		return $this->isModEnabled( self::con()->cfg->configuration->modFromOpt( $optKey ) );
	}

	public function isModEnabled( string $slug ) :bool {
		return self::con()->opts->optIs( 'enable_'.$slug, 'Y' );
	}

	public function isPassPoliciesEnabled() :bool {
		return self::con()->opts->optIs( 'enable_password_policies', 'Y' ) && $this->isModFromOptEnabled( 'enable_password_policies' );
	}

	public function isPassPreventPwned() :bool {
		return $this->isPassPoliciesEnabled() && self::con()->opts->optIs( 'pass_prevent_pwned', 'Y' );
	}

	public function getEmailValidateChecks() :array {
		$con = self::con();
		return ( $this->isModFromOptEnabled( 'email_checks' ) && $con->opts->optGet( 'reg_email_validate' ) !== 'disabled'
				 && $con->isPremiumActive() ) ? $con->opts->optGet( 'email_checks' ) : [];
	}
}