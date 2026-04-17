<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureZoneDiagnosisBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory,
	UnitTestPluginUrls
};

class ConfigureZoneDiagnosisBuilderTest extends BaseUnitTest {

	private object $secAdminController;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
		$this->secAdminController = new class {
			public bool $enabled = true;

			public function isEnabledSecAdmin() :bool {
				return $this->enabled;
			}
		};
		UnitTestControllerFactory::install(
			new UnitTestPluginUrls(),
			null,
			new class( $this->secAdminController ) {
				public object $comps;

				public function __construct( object $secAdminController ) {
					$this->comps = (object)[
						'sec_admin' => $secAdminController,
					];
				}
			}
		);
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_mixed_issue_zone_uses_issue_rows_for_findings_and_guidance() :void {
		$diagnosis = ( new ConfigureZoneDiagnosisBuilder() )->build(
			$this->buildZoneTile( 'users', 'Users', 'warning', 'Needs Work', '1 group needs work', [
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
		$this->assertArrayNotHasKey( 'review_fallback_card', $diagnosis );
		$this->assertArrayNotHasKey( 'review_rows_heading', $diagnosis );
		$this->assertSame( 'Users', $diagnosis[ 'header' ][ 'title' ] );
		$this->assertSame( 'Users', $diagnosis[ 'zone_selection' ][ 'label' ] );
		$this->assertSame( '2FA is not enforced.', $diagnosis[ 'preview_text' ] );
		$this->assertNotSame( '', $diagnosis[ 'header' ][ 'badge' ] ?? '' );
		$this->assertNotSame( '', $diagnosis[ 'header' ][ 'next_step' ] ?? '' );
		$this->assertArrayNotHasKey( 'healthy_rows_heading', $diagnosis );
		$this->assertArrayNotHasKey( 'next_move_heading', $diagnosis );
		$this->assertArrayNotHasKey( 'next_move', $diagnosis );
		$this->assertArrayNotHasKey( 'settings_href', $diagnosis );
		$this->assertArrayNotHasKey( 'settings_label', $diagnosis );
		$this->assertArrayNotHasKey( 'inline_control', $diagnosis[ 'problem_rows' ][ 0 ] );
		$this->assertSame( '2fa', $diagnosis[ 'problem_rows' ][ 0 ][ 'key' ] ?? '' );
		$this->assertTrue( $diagnosis[ 'problem_rows' ][ 0 ][ 'expand_action' ][ 'is_expandable' ] );
		$this->assertSame(
			'configure-diagnosis-users-2fa',
			$diagnosis[ 'problem_rows' ][ 0 ][ 'expand_action' ][ 'id' ] ?? ''
		);
		$this->assertSame( '2fa', $diagnosis[ 'problem_rows' ][ 0 ][ 'expand_action' ][ 'data_attributes' ][ 'zone_component_slug' ] ?? '' );
		$this->assertSame(
			'configure-diagnosis-users-captcha',
			$diagnosis[ 'problem_rows' ][ 1 ][ 'expand_action' ][ 'id' ] ?? ''
		);
		$this->assertSame(
			'configure-diagnosis-users-password_reset',
			$diagnosis[ 'healthy_rows' ][ 0 ][ 'expand_action' ][ 'id' ] ?? ''
		);
		$this->assertSame( 'password_reset', $diagnosis[ 'healthy_rows' ][ 0 ][ 'expand_action' ][ 'data_attributes' ][ 'zone_component_slug' ] ?? '' );
		$this->assertSame( 'Password reset is enabled.', $diagnosis[ 'healthy_rows' ][ 0 ][ 'summary' ] );
	}

	public function test_all_good_zone_enters_review_state_without_findings() :void {
		$diagnosis = ( new ConfigureZoneDiagnosisBuilder() )->build(
			$this->buildZoneTile( 'firewall', 'Firewall', 'good', 'Good', 'All groups healthy', [
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
		$this->assertArrayNotHasKey( 'review_fallback_card', $diagnosis );
		$this->assertNotSame( '', $diagnosis[ 'header' ][ 'badge' ] ?? '' );
		$this->assertNotSame( '', $diagnosis[ 'header' ][ 'next_step' ] ?? '' );
		$this->assertNotSame( '', $diagnosis[ 'preview_text' ] ?? '' );
		$this->assertArrayNotHasKey( 'healthy_rows_heading', $diagnosis );
		$this->assertArrayNotHasKey( 'next_move', $diagnosis );
	}

	public function test_login_preview_does_not_use_hide_login_warning_text() :void {
		$diagnosis = ( new ConfigureZoneDiagnosisBuilder() )->build(
			$this->buildZoneTile( 'login', 'Login', 'warning', 'Needs Work', '1 group needs work', [
				[
					'status' => 'warning',
					'rows'   => [
						$this->buildDetailRow(
							'Hide WP Login',
							'warning',
							'Needs Work',
							'Hide The WP Login Page.'
						),
					],
				],
			] )
		);

		$this->assertSame(
			'Protect the WordPress login and verify user logins with two-factor authentication.',
			$diagnosis[ 'preview_text' ]
		);
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
		$this->assertArrayNotHasKey( 'review_fallback_card', $diagnosis );
		$this->assertNotSame( '', $diagnosis[ 'header' ][ 'badge' ] ?? '' );
		$this->assertArrayNotHasKey( 'review_rows_heading', $diagnosis );
		$this->assertNotSame( '', $diagnosis[ 'risk_context' ] ?? '' );
		$this->assertNotSame( '', $diagnosis[ 'preview_text' ] ?? '' );
		$this->assertNotSame( '', $diagnosis[ 'header' ][ 'next_step' ] ?? '' );
		$this->assertArrayNotHasKey( 'next_move', $diagnosis );
		$this->assertArrayNotHasKey( 'inline_control', $diagnosis[ 'review_rows' ][ 0 ] );
		$this->assertSame( 'traffic_logging', $diagnosis[ 'review_rows' ][ 0 ][ 'key' ] ?? '' );
		$this->assertTrue( $diagnosis[ 'review_rows' ][ 0 ][ 'expand_action' ][ 'is_expandable' ] );
		$this->assertSame(
			'configure-diagnosis-general-traffic_logging',
			$diagnosis[ 'review_rows' ][ 0 ][ 'expand_action' ][ 'id' ] ?? ''
		);
		$this->assertSame( 'traffic_logging', $diagnosis[ 'review_rows' ][ 0 ][ 'expand_action' ][ 'data_attributes' ][ 'zone_component_slug' ] ?? '' );
	}

	public function test_empty_review_state_exposes_producer_owned_fallback_review_row() :void {
		$diagnosis = ( new ConfigureZoneDiagnosisBuilder() )->build(
			$this->buildZoneTile( 'firewall', 'Firewall', 'good', 'Good', 'All groups healthy', [] )
		);

		$this->assertSame( [], $diagnosis[ 'problem_rows' ] );
		$this->assertCount( 1, $diagnosis[ 'review_rows' ] );
		$this->assertSame( [], $diagnosis[ 'healthy_rows' ] );
		$this->assertArrayNotHasKey( 'review_fallback_card', $diagnosis );
		$this->assertSame(
			[
				'key'               => 'review_fallback',
				'title'             => 'Good',
				'summary'           => 'All groups healthy',
				'status'            => 'neutral',
				'status_label'      => 'Review',
				'status_icon_class' => 'bi bi-info-circle-fill',
				'explanations'      => [],
				'expand_action'     => [
					'id'              => '',
					'is_expandable'   => false,
					'label'           => '',
					'title'           => '',
					'data_attributes' => [],
				],
			],
			$diagnosis[ 'review_rows' ][ 0 ]
		);
	}

	public function test_build_rejects_rows_without_producer_owned_keys() :void {
		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( 'producer-owned row key' );

		( new ConfigureZoneDiagnosisBuilder() )->build(
			$this->buildZoneTile( 'login', 'Login', 'warning', 'Needs Work', '1 group needs work', [
				[
					'status' => 'warning',
					'rows'   => [
						\array_merge(
							$this->buildDetailRow( 'Missing Key', 'warning', 'Needs Work', 'Broken row.' ),
							[ 'key' => '' ]
						),
					],
				],
			] )
		);
	}

	public function test_fallback_summary_prefers_explanation_then_title() :void {
		$diagnosis = ( new ConfigureZoneDiagnosisBuilder() )->build(
			$this->buildZoneTile( 'secadmin', 'Security Admin', 'critical', 'Critical', '1 critical group', [
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

	public function test_secadmin_header_includes_disable_action_when_enabled() :void {
		$diagnosis = ( new ConfigureZoneDiagnosisBuilder() )->build(
			$this->buildZoneTile( 'secadmin', 'Security Admin', 'critical', 'Critical', '1 critical group', [] )
		);

		$this->assertCount( 1, $diagnosis[ 'header' ][ 'actions' ] );
		$this->assertSame( 'Disable Security Admin', $diagnosis[ 'header' ][ 'actions' ][ 0 ][ 'label' ] ?? '' );
		$this->assertSame( 'deactivate', $diagnosis[ 'header' ][ 'actions' ][ 0 ][ 'type' ] ?? '' );
	}

	public function test_secadmin_header_omits_disable_action_when_disabled() :void {
		$this->secAdminController->enabled = false;

		$diagnosis = ( new ConfigureZoneDiagnosisBuilder() )->build(
			$this->buildZoneTile( 'secadmin', 'Security Admin', 'critical', 'Critical', '1 critical group', [] )
		);

		$this->assertSame( [], $diagnosis[ 'header' ][ 'actions' ] );
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
				'rows'          => [],
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
