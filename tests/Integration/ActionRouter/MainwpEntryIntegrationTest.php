<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionProcessor,
	Actions\MainWP\ClientActions\MainwpEnable,
	Actions\MainWP\MainwpSitePageDisplay,
	Actions\ModuleOptionsSave,
	Actions\PluginSetOpt,
	CapturePluginAction,
	Exceptions\ActionException,
	Exceptions\UserAuthRequiredException
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client\Actions\ClientActionRunner;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

/**
 * The MainWP dashboard's site page iframe and the actions a dashboard runs on a client site.
 */
class MainwpEntryIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private const SNAPSHOT_OPTS = [
		'enable_mainwp',
		'email_can_send_verified_at',
	];

	private array $requestSnapshot = [];

	private array $optsSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$opts = $this->requireController()->opts;
		foreach ( self::SNAPSHOT_OPTS as $opt ) {
			$this->optsSnapshot[ $opt ] = $opts->optGet( $opt );
		}
		$this->enablePremiumCapabilities( [ 'mainwp_level_1' ] );
		$opts->optSet( 'enable_mainwp', 'N' )->optSet( 'email_can_send_verified_at', 0 );
		$this->loginAsSecurityAdmin();
	}

	public function tear_down() {
		$opts = $this->requireController()->opts;
		foreach ( $this->optsSnapshot as $opt => $value ) {
			$opts->optSet( $opt, $value );
		}
		$this->disablePremiumCapabilities();
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		parent::tear_down();
	}

	public function test_site_page_without_its_nonce_is_refused_before_it_renders() :void {
		$request = ActionData::Build( MainwpSitePageDisplay::class, false, [
			'site_id' => 123,
		] );
		unset( $request[ ActionData::FIELD_NONCE ] );
		$this->applyCurrentRequestState( [
			'REQUEST_URI' => '/wp-admin/?'.\http_build_query( $request ),
		], $request );

		$capture = new MainwpEntryCaptureTestDouble();
		$this->assertTrue( $capture->canRunForTest() );
		$this->expectException( \WPDieException::class );
		$capture->runForTest();
	}

	public function test_site_page_rejects_an_invalid_site() :void {
		$this->expectException( ActionException::class );
		( new ActionProcessor() )->processAction( MainwpSitePageDisplay::class, [
			'site_id' => 0,
		] );
	}

	public function test_site_page_requires_a_logged_in_admin() :void {
		\wp_set_current_user( 0 );
		$this->expectException( UserAuthRequiredException::class );
		( new ActionProcessor() )->processAction( MainwpSitePageDisplay::class, [
			'site_id' => 123,
		] );
	}

	public function test_legacy_enable_request_from_an_older_dashboard_switches_mainwp_on() :void {
		$payload = ( new ClientActionRunner() )->run( PluginSetOpt::SLUG, [
			'action_overrides' => [
				'is_nonce_verify_required' => false,
			],
			'opt_key'          => 'enable_mainwp',
			'opt_value'        => 'Y',
		] );

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertSame( 'Y', $this->requireController()->opts->optGet( 'enable_mainwp' ) );
	}

	public function test_enable_action_switches_mainwp_on() :void {
		$payload = ( new ClientActionRunner() )->run( MainwpEnable::SLUG, [] );

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertSame( 'Y', $this->requireController()->opts->optGet( 'enable_mainwp' ) );
	}

	public function test_any_other_legacy_option_write_is_refused() :void {
		try {
			( new ClientActionRunner() )->run( PluginSetOpt::SLUG, [
				'opt_key'   => 'email_can_send_verified_at',
				'opt_value' => 12345,
			] );
			$this->fail( 'Expected the option write to be refused.' );
		}
		catch ( ActionException $e ) {
		}
		$this->assertSame( 0, (int)$this->requireController()->opts->optGet( 'email_can_send_verified_at' ) );
	}

	public function test_legacy_enable_request_with_another_value_is_refused() :void {
		$this->expectException( ActionException::class );
		( new ClientActionRunner() )->run( PluginSetOpt::SLUG, [
			'opt_key'   => 'enable_mainwp',
			'opt_value' => 'N',
		] );
	}

	public function test_action_not_declared_for_mainwp_is_refused_even_with_override_metadata() :void {
		$this->expectException( ActionException::class );
		( new ClientActionRunner() )->run( ModuleOptionsSave::SLUG, [
			'action_overrides' => [
				'is_nonce_verify_required' => false,
			],
		] );
	}
}

class MainwpEntryCaptureTestDouble extends CapturePluginAction {

	public function canRunForTest() :bool {
		return $this->canRun();
	}

	public function runForTest() :void {
		$this->theRun();
	}
}
