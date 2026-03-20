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
						$this->buildDetailRow(
							'2FA',
							'critical',
							'Issue',
							'2FA is not enforced.'
						),
					],
				],
				[
					'status' => 'warning',
					'rows'   => [
						$this->buildDetailRow(
							'CAPTCHA',
							'warning',
							'Needs Work',
							'CAPTCHA is disabled.'
						),
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

		$this->assertSame( 2, \count( $diagnosis[ 'problem_rows' ] ) );
		$this->assertSame( [], $diagnosis[ 'review_rows' ] );
		$this->assertSame( 1, \count( $diagnosis[ 'healthy_rows' ] ) );
		$this->assertSame( [], $diagnosis[ 'review_fallback_card' ] );
		$this->assertSame( 'Login', $diagnosis[ 'header' ][ 'title' ] );
		$this->assertSame( 'Login', $diagnosis[ 'zone_selection' ][ 'label' ] );
		$this->assertSame( '2 findings', $diagnosis[ 'header' ][ 'badge' ] );
		$this->assertSame( '1 setting configured correctly', $diagnosis[ 'healthy_rows_heading' ] );
		$this->assertSame( 'Next move', $diagnosis[ 'next_move_heading' ] );
		$this->assertSame( '2FA is not enforced.', $diagnosis[ 'preview_text' ] );
		$this->assertSame( 'Review 2FA below next.', $diagnosis[ 'next_move' ] );
		$this->assertArrayNotHasKey( 'inline_control', $diagnosis[ 'problem_rows' ][ 0 ] );
		$this->assertTrue( $diagnosis[ 'problem_rows' ][ 0 ][ 'expand_action' ][ 'is_expandable' ] );
		$this->assertSame( '2fa', $diagnosis[ 'problem_rows' ][ 0 ][ 'expand_action' ][ 'data_attributes' ][ 'zone_component_slug' ] ?? '' );
		$this->assertSame( 'password_reset', $diagnosis[ 'healthy_rows' ][ 0 ][ 'expand_action' ][ 'data_attributes' ][ 'zone_component_slug' ] ?? '' );
		$this->assertSame( 'Password reset is enabled.', $diagnosis[ 'healthy_rows' ][ 0 ][ 'summary' ] );
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

		$this->assertSame( [], $diagnosis[ 'problem_rows' ] );
		$this->assertSame( [], $diagnosis[ 'review_rows' ] );
		$this->assertSame( 1, \count( $diagnosis[ 'healthy_rows' ] ) );
		$this->assertSame( [], $diagnosis[ 'review_fallback_card' ] );
		$this->assertSame( 'Good', $diagnosis[ 'header' ][ 'badge' ] );
		$this->assertStringContainsString( 'no active findings', $diagnosis[ 'preview_text' ] );
		$this->assertSame( '1 setting configured correctly', $diagnosis[ 'healthy_rows_heading' ] );
	}

	public function test_general_zone_uses_neutral_review_state() :void {
		$diagnosis = ( new ConfigureZoneDiagnosisBuilder() )->build(
			$this->buildZoneTile( 'general', 'General', 'neutral', 'General', 'General settings', [
				[
					'status' => 'neutral',
					'rows'   => [
						$this->buildDetailRow(
							'Traffic Logging',
							'neutral',
							'General',
							'General settings'
						),
					],
				],
			] )
		);

		$this->assertSame( [], $diagnosis[ 'problem_rows' ] );
		$this->assertSame( 1, \count( $diagnosis[ 'review_rows' ] ) );
		$this->assertSame( [], $diagnosis[ 'healthy_rows' ] );
		$this->assertSame( [], $diagnosis[ 'review_fallback_card' ] );
		$this->assertSame( 'Review', $diagnosis[ 'header' ][ 'badge' ] );
		$this->assertSame( 'Review these settings', $diagnosis[ 'review_rows_heading' ] );
		$this->assertStringContainsString( 'General controls', $diagnosis[ 'risk_context' ] );
		$this->assertStringContainsString( 'site-wide controls', $diagnosis[ 'preview_text' ] );
		$this->assertArrayNotHasKey( 'inline_control', $diagnosis[ 'review_rows' ][ 0 ] );
		$this->assertTrue( $diagnosis[ 'review_rows' ][ 0 ][ 'expand_action' ][ 'is_expandable' ] );
		$this->assertSame( 'traffic_logging', $diagnosis[ 'review_rows' ][ 0 ][ 'expand_action' ][ 'data_attributes' ][ 'zone_component_slug' ] ?? '' );
	}

	public function test_empty_review_state_exposes_producer_owned_fallback_card() :void {
		$diagnosis = ( new ConfigureZoneDiagnosisBuilder() )->build(
			$this->buildZoneTile( 'firewall', 'Firewall', 'good', 'Good', 'All components healthy', [] )
		);

		$this->assertSame( [], $diagnosis[ 'problem_rows' ] );
		$this->assertSame( [], $diagnosis[ 'review_rows' ] );
		$this->assertSame( [], $diagnosis[ 'healthy_rows' ] );
		$this->assertSame(
			[
				'title'             => 'Good',
				'summary'           => 'All components healthy',
				'status'            => 'neutral',
				'status_label'      => 'Review',
				'status_icon_class' => 'bi bi-info-circle-fill',
			],
			$diagnosis[ 'review_fallback_card' ]
		);
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
		$this->assertSame( 'Set a PIN before more admins are added.', $diagnosis[ 'problem_rows' ][ 0 ][ 'summary' ] );
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
		array $explanations = [],
		?array $action = null
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
			'action'            => $action ?? [
				'label'   => 'Configure',
				'href'    => 'javascript:{}',
				'title'   => 'Configure '.$title,
				'target'  => '',
				'icon'    => 'bi bi-gear-fill',
				'classes' => [ 'zone_component_action' ],
				'data'    => [
					'zone_component_action' => 'offcanvas_zone_component_config',
					'zone_component_slug'   => \strtolower( \str_replace( ' ', '_', $title ) ),
					'form_context'          => 'offcanvas',
				],
			],
		];
	}
}
