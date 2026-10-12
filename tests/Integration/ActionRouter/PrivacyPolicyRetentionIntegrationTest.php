<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\PrivacyPolicy;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\ActivityLogRetentionPolicy;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class PrivacyPolicyRetentionIntegrationTest extends ShieldIntegrationTestCase {

	public function test_privacy_policy_render_uses_active_retention_policy_values() :void {
		$activityFilter = static function ( array $policy ) :array {
			$policy[ 'retention_seconds_by_level' ] = [
				'info'    => 13*\HOUR_IN_SECONDS,
				'notice'  => 7*\DAY_IN_SECONDS,
				'warning' => 45*\DAY_IN_SECONDS,
			];
			$policy[ 'high_value_retention_seconds' ] = 365*\DAY_IN_SECONDS;
			return $policy;
		};
		add_filter( ActivityLogRetentionPolicy::FILTER_ACTIVITY_POLICY, $activityFilter );

		try {
			$payload = ( new ActionProcessor() )->processAction( PrivacyPolicy::SLUG )->payload();
			$auditRetention = (array)( $payload[ 'render_data' ][ 'audit_retention' ] ?? [] );

			$this->assertSame( [
				'info_hours'      => 13,
				'notice_days'     => 7,
				'warning_days'    => 45,
				'high_value_days' => 365,
			], $auditRetention );
			$this->assertArrayHasKey( 'render_output', $payload );
		}
		finally {
			remove_filter( ActivityLogRetentionPolicy::FILTER_ACTIVITY_POLICY, $activityFilter );
		}
	}

	public function test_privacy_policy_describes_the_active_visitor_check() :void {
		$con = $this->requireController();
		$snapshot = $this->snapshotSelectedOptions( [ 'silentcaptcha_cookie_free' ] );
		$threshold = 50;
		$thresholdFilter = static function () use ( &$threshold ) :int {
			return $threshold;
		};
		add_filter( 'shield/silent_captcha_bot_threshold', $thresholdFilter, \PHP_INT_MAX );
		$sentence = static function () :string {
			$payload = ( new ActionProcessor() )->processAction( PrivacyPolicy::SLUG )->payload();
			return (string)( $payload[ 'render_data' ][ 'strings' ][ 'cookies_item_4_sentence_1' ] ?? '' );
		};

		try {
			$con->opts->optSet( 'silentcaptcha_cookie_free', 'N' );
			$this->assertStringContainsString( 'short-lived cookie', $sentence() );
			$con->opts->optSet( 'silentcaptcha_cookie_free', 'Y' );
			$this->assertStringContainsString( 'without using a cookie', $sentence() );
			$threshold = 0;
			$this->assertStringContainsString( 'does not normally use cookies', $sentence() );
		}
		finally {
			remove_filter( 'shield/silent_captcha_bot_threshold', $thresholdFilter, \PHP_INT_MAX );
			$this->restoreSelectedOptions( $snapshot );
		}
	}
}
