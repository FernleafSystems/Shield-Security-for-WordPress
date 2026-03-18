<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureZoneDiagnosisBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ConfigureZoneDiagnosisBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
	}

	public function test_mixed_issue_zone_uses_issue_rows_for_findings_and_guidance() :void {
		$diagnosis = ( new ConfigureZoneDiagnosisBuilder() )->build(
			$this->buildZoneTile( 'login', 'Login', 'warning', 'Needs Work', '1 component needs work', [
				[
					'status' => 'critical',
					'rows'   => [
						$this->buildDetailRow( '2FA', 'critical', 'Issue', '2FA is not enforced.' ),
					],
				],
				[
					'status' => 'warning',
					'rows'   => [
						$this->buildDetailRow( 'CAPTCHA', 'warning', 'Needs Work', 'CAPTCHA is disabled.' ),
					],
				],
				[
					'status' => 'good',
					'rows'   => [
						$this->buildDetailRow( 'Password reset', 'good', 'Good', 'Password reset is enabled.' ),
					],
				],
			] )
		);

		$this->assertFalse( $diagnosis[ 'is_review_state' ] );
		$this->assertSame( 2, $diagnosis[ 'findings_count' ] );
		$this->assertSame( 2, \count( $diagnosis[ 'findings' ] ) );
		$this->assertSame( 2, \count( $diagnosis[ 'problem_rows' ] ) );
		$this->assertSame( 1, \count( $diagnosis[ 'healthy_rows' ] ) );
		$this->assertSame( '2FA', $diagnosis[ 'strip_text' ] );
		$this->assertSame( '2 findings', $diagnosis[ 'strip_badge' ] );
		$this->assertSame( 'Next move', $diagnosis[ 'next_move_heading' ] );
		$this->assertSame( '2FA is not enforced.', $diagnosis[ 'preview_text' ] );
		$this->assertStringContainsString( 'Review 2FA', $diagnosis[ 'next_move' ] );
	}

	public function test_all_good_zone_enters_review_state_without_findings() :void {
		$diagnosis = ( new ConfigureZoneDiagnosisBuilder() )->build(
			$this->buildZoneTile( 'firewall', 'Firewall', 'good', 'Good', 'All components healthy', [
				[
					'status' => 'good',
					'rows'   => [
						$this->buildDetailRow( 'WAF Rules', 'good', 'Active', 'Firewall rules are active.' ),
					],
				],
			] )
		);

		$this->assertTrue( $diagnosis[ 'is_review_state' ] );
		$this->assertSame( [], $diagnosis[ 'findings' ] );
		$this->assertSame( [], $diagnosis[ 'problem_rows' ] );
		$this->assertSame( 1, \count( $diagnosis[ 'healthy_rows' ] ) );
		$this->assertSame( 'Good', $diagnosis[ 'strip_badge' ] );
		$this->assertStringContainsString( 'no active findings', $diagnosis[ 'preview_text' ] );
	}

	public function test_general_zone_uses_neutral_review_state() :void {
		$diagnosis = ( new ConfigureZoneDiagnosisBuilder() )->build(
			$this->buildZoneTile( 'general', 'General', 'neutral', 'General', 'General settings', [
				[
					'status' => 'neutral',
					'rows'   => [
						$this->buildDetailRow( 'Traffic Logging', 'neutral', 'General', 'General settings' ),
					],
				],
			] )
		);

		$this->assertTrue( $diagnosis[ 'is_review_state' ] );
		$this->assertSame( [], $diagnosis[ 'problem_rows' ] );
		$this->assertSame( 1, \count( $diagnosis[ 'healthy_rows' ] ) );
		$this->assertSame( 'Review', $diagnosis[ 'strip_badge' ] );
		$this->assertStringContainsString( 'General controls', $diagnosis[ 'risk_context' ] );
		$this->assertStringContainsString( 'site-wide controls', $diagnosis[ 'preview_text' ] );
	}

	public function test_fallback_summary_prefers_explanation_then_title() :void {
		$diagnosis = ( new ConfigureZoneDiagnosisBuilder() )->build(
			$this->buildZoneTile( 'secadmin', 'Security Admin', 'critical', 'Critical', '1 critical component', [
				[
					'status' => 'critical',
					'rows'   => [
						$this->buildDetailRow(
							'PIN Protection',
							'critical',
							'Issue',
							'',
							[ 'Set a PIN before more admins are added.' ]
						),
					],
				],
			] )
		);

		$this->assertSame( 'Set a PIN before more admins are added.', $diagnosis[ 'preview_text' ] );
		$this->assertSame( 'Set a PIN before more admins are added.', $diagnosis[ 'findings' ][ 0 ][ 'summary' ] );
	}

	private function buildZoneTile(
		string $key,
		string $label,
		string $status,
		string $statusLabel,
		string $statLine,
		array $detailGroups
	) :array {
		return [
			'key'               => $key,
			'include_in_posture' => $key !== 'general',
			'label'             => $label,
			'icon_class'        => 'bi bi-gear',
			'status'            => $status,
			'status_label'      => $statusLabel,
			'status_icon_class' => 'bi bi-shield-check',
			'stat_line'         => $statLine,
			'settings_href'     => '/admin/'.$key,
			'settings_label'    => 'Configure '.$label.' Settings',
			'settings_action'   => [],
			'panel'             => [
				'title'         => $label,
				'status'        => $status,
				'status_label'  => $statusLabel,
				'components'    => [],
				'detail_groups' => $detailGroups,
			],
		];
	}

	private function buildDetailRow(
		string $title,
		string $status,
		string $statusLabel,
		string $summary,
		array $explanations = []
	) :array {
		return [
			'key'               => \strtolower( \str_replace( ' ', '_', $title ) ),
			'title'             => $title,
			'summary'           => $summary,
			'status'            => $status,
			'status_label'      => $statusLabel,
			'status_icon_class' => 'bi bi-exclamation-triangle-fill',
			'count_badge'       => null,
			'badge_status'      => $status,
			'explanations'      => $explanations,
			'action'            => [],
		];
	}
}
