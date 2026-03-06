<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\AjaxBatchRequests,
	Actions\AjaxRender,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	ModeLandingAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class InvestigateLandingPageIntegrationTest extends ShieldIntegrationTestCase {

	use ModeLandingAssertions;
	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function renderInvestigateLandingPage( array $extra = [] ) :array {
		return $this->renderPluginAdminRoutePayload(
			PluginNavs::NAV_ACTIVITY,
			PluginNavs::SUBNAV_ACTIVITY_OVERVIEW,
			$extra
		);
	}

	public function test_landing_exposes_subject_payload_and_disabled_premium_integrations_contract() :void {
		$payload = $this->renderInvestigateLandingPage();
		$this->assertRouteRenderOutputHealthy( $payload, 'investigate landing' );
		$renderData = $payload[ 'render_data' ] ?? [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$subjects = \is_array( $vars[ 'subjects' ] ?? null ) ? $vars[ 'subjects' ] : [];
		$subjectDefinitions = PluginNavs::investigateLandingSubjectDefinitions();
		$enabledSubjectDefinitions = \array_filter(
			$subjectDefinitions,
			static fn( array $subject ) :bool => (bool)( $subject[ 'is_enabled' ] ?? false )
		);
		$expectedTileCount = \count( $subjectDefinitions );
		$expectedPanelCount = \count( $enabledSubjectDefinitions );
		$expectedLivePanelCount = \count( \array_filter(
			\array_keys( $enabledSubjectDefinitions ),
			static fn( string $subjectKey ) :bool => $subjectKey === 'live_traffic'
		) );
		$this->assertArrayHasKey( 'premium_integrations', $subjectDefinitions );
		$this->assertFalse(
			(bool)( $subjectDefinitions[ 'premium_integrations' ][ 'is_enabled' ] ?? true ),
			'Premium integrations subject must remain disabled.'
		);
		$this->assertModeShellPayload( $vars, 'investigate', 'info', true );
		$this->assertModePanelPayload( $vars, '', false );
		$batchActionData = \is_array( $vars[ 'batch_render_action' ] ?? null ) ? $vars[ 'batch_render_action' ] : [];
		$this->assertSame( ActionData::FIELD_SHIELD, $batchActionData[ ActionData::FIELD_ACTION ] ?? '' );
		$this->assertSame( AjaxBatchRequests::SLUG, $batchActionData[ ActionData::FIELD_EXECUTE ] ?? '' );
		$this->assertNotSame( '', (string)( $renderData[ 'strings' ][ 'landing_hint' ] ?? '' ) );

		foreach ( $subjectDefinitions as $subjectKey => $subjectDefinition ) {
			$matches = \array_values( \array_filter(
				$subjects,
				static fn( array $subject ) :bool => (string)( $subject[ 'key' ] ?? '' ) === $subjectKey
			) );
			$this->assertCount( 1, $matches, 'Landing subject payload for '.$subjectKey );
			$subject = $matches[ 0 ] ?? [];

			if ( (bool)( $subjectDefinition[ 'is_enabled' ] ?? false ) ) {
				$this->assertTrue( (bool)( $subject[ 'is_enabled' ] ?? false ) );
				$this->assertFalse( (bool)( $subject[ 'is_loaded' ] ?? true ) );
				$this->assertSame( $subjectKey, (string)( $subject[ 'panel_target' ] ?? '' ) );
			}
			else {
				$this->assertFalse( (bool)( $subject[ 'is_enabled' ] ?? true ) );
				$this->assertTrue( (bool)( $subject[ 'is_disabled' ] ?? false ) );
			}
			$this->assertSame( (string)( $subjectDefinition[ 'lookup_key' ] ?? '' ), (string)( $subject[ 'lookup_key' ] ?? '' ) );
			$this->assertSame( $subjectKey === 'live_traffic', (bool)( $subject[ 'is_live' ] ?? false ) );
			$renderActionData = \is_array( $subject[ 'render_action' ] ?? null ) ? $subject[ 'render_action' ] : [];
			if ( (bool)( $subjectDefinition[ 'is_enabled' ] ?? false ) ) {
				$this->assertSame( ActionData::FIELD_SHIELD, $renderActionData[ ActionData::FIELD_ACTION ] ?? '' );
				$this->assertSame( AjaxRender::SLUG, $renderActionData[ ActionData::FIELD_EXECUTE ] ?? '' );
				$this->assertSame( ( $subjectDefinition[ 'render_action' ] )::SLUG, $renderActionData[ 'render_slug' ] ?? '' );
				$this->assertSame( $subjectDefinition[ 'render_nav' ], $renderActionData[ Constants::NAV_ID ] ?? '' );
				$this->assertSame( $subjectDefinition[ 'render_subnav' ], $renderActionData[ Constants::NAV_SUB_ID ] ?? '' );
			}
			else {
				$this->assertSame( [], $renderActionData );
			}
		}

		$this->assertCount( $expectedTileCount, $vars[ 'mode_tiles' ] ?? [] );
		$this->assertCount( \count( $subjectDefinitions ), $subjects );
		$this->assertSame( $expectedPanelCount, \count( \array_values( \array_filter(
			$subjects,
			static fn( array $subject ) :bool => (bool)( $subject[ 'is_enabled' ] ?? false )
		) ) ) );
		$this->assertSame( $expectedPanelCount, \count( \array_values( \array_filter(
			$subjects,
			static fn( array $subject ) :bool => !(bool)( $subject[ 'is_loaded' ] ?? true ) && (bool)( $subject[ 'is_enabled' ] ?? false )
		) ) ) );
		$this->assertSame( $expectedLivePanelCount, \count( \array_values( \array_filter(
			$subjects,
			static fn( array $subject ) :bool => (bool)( $subject[ 'is_live' ] ?? false ) && (bool)( $subject[ 'is_enabled' ] ?? false )
		) ) ) );
	}

	public function test_lookup_preload_sets_active_subject_and_loaded_panel_payload() :void {
		$enabledSubjectCount = \count( \array_filter(
			PluginNavs::investigateLandingSubjectDefinitions(),
			static fn( array $subject ) :bool => (bool)( $subject[ 'is_enabled' ] ?? false )
		) );

		$payload = $this->renderInvestigateLandingPage( [
			'subject'    => 'ip',
			'analyse_ip' => '203.0.113.88',
		] );
		$this->assertRouteRenderOutputHealthy( $payload, 'investigate landing preload' );
		$vars = \is_array( $payload[ 'render_data' ][ 'vars' ] ?? null ) ? $payload[ 'render_data' ][ 'vars' ] : [];
		$subjects = \is_array( $vars[ 'subjects' ] ?? null ) ? $vars[ 'subjects' ] : [];
		$subjectsByKey = [];
		foreach ( $subjects as $subject ) {
			$subjectsByKey[ (string)( $subject[ 'key' ] ?? '' ) ] = $subject;
		}

		$this->assertModePanelPayload( $vars, 'ip', true );
		$this->assertCount( $enabledSubjectCount - 1, \array_values( \array_filter(
			$subjects,
			static fn( array $subject ) :bool => !(bool)( $subject[ 'is_loaded' ] ?? true ) && (bool)( $subject[ 'is_enabled' ] ?? false )
		) ) );
		$this->assertTrue( (bool)( $subjectsByKey[ 'ip' ][ 'is_loaded' ] ?? false ) );
		$this->assertSame( '203.0.113.88', (string)( $subjectsByKey[ 'ip' ][ 'subject_title' ] ?? '' ) );
		$this->assertSame( 'analyse_ip', (string)( $subjectsByKey[ 'ip' ][ 'lookup_key' ] ?? '' ) );
		$this->assertNotSame( '', \trim( (string)( $subjectsByKey[ 'ip' ][ 'panel_body' ] ?? '' ) ) );
	}

}
