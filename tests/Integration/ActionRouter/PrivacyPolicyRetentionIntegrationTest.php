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
			$html = (string)( $payload[ 'render_output' ] ?? '' );

			$this->assertSame( [
				'info_hours'      => 13,
				'notice_days'     => 7,
				'warning_days'    => 45,
				'high_value_days' => 365,
			], $auditRetention );

			$this->assertHtmlNotContainsMarker( 'Exception during render', $html, 'privacy-policy-render' );
			foreach ( [ '13', '7', '45', '365' ] as $marker ) {
				$this->assertStringContainsString( $marker, $html );
			}
		}
		finally {
			remove_filter( ActivityLogRetentionPolicy::FILTER_ACTIVITY_POLICY, $activityFilter );
		}
	}
}
