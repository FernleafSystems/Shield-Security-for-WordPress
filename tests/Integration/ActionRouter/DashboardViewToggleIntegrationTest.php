<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	ActionResponse,
	Actions\DashboardViewToggle
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Dashboard\DashboardViewPreference;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class DashboardViewToggleIntegrationTest extends ShieldIntegrationTestCase {

	private int $adminUserId;

	public function set_up() {
		parent::set_up();

		$this->adminUserId = $this->loginAsAdministrator();
		delete_user_meta( $this->adminUserId, DashboardViewPreference::META_KEY );
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	private function processToggle( string $view ) :ActionResponse {
		return $this->processor()->processAction( DashboardViewToggle::SLUG, [
			'view' => $view,
		] );
	}

	public function test_valid_simple_updates_current_user_meta() :void {
		$this->processToggle( DashboardViewPreference::VIEW_SIMPLE );

		$this->assertSame(
			DashboardViewPreference::VIEW_SIMPLE,
			get_user_meta( $this->adminUserId, DashboardViewPreference::META_KEY, true )
		);
	}

	public function test_valid_advanced_updates_current_user_meta() :void {
		$this->processToggle( DashboardViewPreference::VIEW_ADVANCED );

		$this->assertSame(
			DashboardViewPreference::VIEW_ADVANCED,
			get_user_meta( $this->adminUserId, DashboardViewPreference::META_KEY, true )
		);
	}

	public function test_invalid_view_does_not_persist_invalid_value() :void {
		$this->processToggle( 'bad-value' );

		$stored = (string)get_user_meta( $this->adminUserId, DashboardViewPreference::META_KEY, true );
		$this->assertNotSame( 'bad-value', $stored );
		$this->assertSame( DashboardViewPreference::VIEW_SIMPLE, $stored );
	}

	public function test_response_includes_redirect_next_step() :void {
		$response = $this->processToggle( DashboardViewPreference::VIEW_ADVANCED );

		$this->assertSame( 'redirect', (string)( $response->next_step[ 'type' ] ?? '' ) );
		$this->assertNotEmpty( (string)( $response->next_step[ 'url' ] ?? '' ) );
	}

	public function test_ajax_response_includes_no_reload_and_sanitized_view() :void {
		$originalIsAjax = self::con()->this_req->wp_is_ajax;
		self::con()->this_req->wp_is_ajax = true;
		try {
			$response = $this->processToggle( 'bad-value' );
		}
		finally {
			self::con()->this_req->wp_is_ajax = $originalIsAjax;
		}

		$payload = $response->payload();
		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertFalse( (bool)( $payload[ 'page_reload' ] ?? true ) );
		$this->assertSame( DashboardViewPreference::VIEW_SIMPLE, (string)( $payload[ 'view' ] ?? '' ) );
		$this->assertSame(
			DashboardViewPreference::VIEW_SIMPLE,
			(string)get_user_meta( $this->adminUserId, DashboardViewPreference::META_KEY, true )
		);
	}
}
