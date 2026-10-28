<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Request;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Request\Sensitivity\{
	BuildRequestFacts,
	RequestFacts,
	RequestSensitivity,
	ResolveRequestSensitivity
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

/**
 * The facts gathered from a live request, and the sensitivity resolved from them, including the filter.
 */
class RequestSensitivityTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $requestSnapshot = [];

	private array $optionSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->optionSnapshot = $this->snapshotSelectedOptions( [ 'rename_wplogin_path' ] );
	}

	public function tear_down() {
		remove_all_filters( 'shield/request_sensitivity' );
		$this->restoreSelectedOptions( $this->optionSnapshot );
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		parent::tear_down();
	}

	public function test_facts_carry_query_parameters_and_whether_credentials_are_present() :void {
		$req = $this->applyCurrentRequestState(
			[ 'REQUEST_URI' => '/?s=widgets', 'HTTP_AUTHORIZATION' => 'Basic dXNlcjpwYXNz' ],
			[ 's' => 'widgets' ]
		);

		$facts = ( new BuildRequestFacts() )->build( $req );

		$this->assertSame( 'GET', $facts->method );
		$this->assertSame( [ 's' => 'widgets' ], $facts->query );
		$this->assertTrue( $facts->hasCredentials );
		$this->assertFalse( $facts->isShieldAction );
		$this->assertSame( [], $facts->wooPageIds, 'WooCommerce is not active' );
	}

	public function test_viewing_a_page_is_open_and_searching_is_guarded() :void {
		$open = ( new ResolveRequestSensitivity() )->resolve( $this->applyCurrentRequestState( [ 'REQUEST_URI' => '/about/' ] ) );
		$search = ( new ResolveRequestSensitivity() )->resolve(
			$this->applyCurrentRequestState( [ 'REQUEST_URI' => '/?s=widgets' ], [ 's' => 'widgets' ] )
		);

		$this->assertSame( RequestSensitivity::OPEN, $open->level );
		$this->assertSame( RequestSensitivity::GUARDED, $search->level );
	}

	public function test_once_routed_a_page_view_is_open_and_no_longer_provisional() :void {
		$pageId = self::factory()->post->create( [ 'post_type' => 'page', 'post_status' => 'publish' ] );
		$url = (string)\get_permalink( $pageId );
		$this->go_to( $url );

		\parse_str( (string)\wp_parse_url( $url, \PHP_URL_QUERY ), $query );
		$req = $this->applyCurrentRequestState( [ 'REQUEST_URI' => \str_replace( \home_url(), '', $url ) ], $query );
		$facts = ( new BuildRequestFacts() )->build( $req );
		$sensitivity = ( new ResolveRequestSensitivity() )->resolve( $req );

		$this->assertNotNull( $facts->routeVars );
		$this->assertSame( $pageId, $facts->queriedObjectId );
		$this->assertSame( [ RequestSensitivity::OPEN, 'content' ], [ $sensitivity->level, $sensitivity->reason ] );
		$this->assertFalse( $sensitivity->isProvisional );
	}

	public function test_shields_renamed_login_page_is_treated_as_the_login_page() :void {
		$this->requireController()->opts->optSet( 'rename_wplogin_path', 'my-login' );

		$sensitivity = ( new ResolveRequestSensitivity() )->resolve( $this->applyCurrentRequestState( [ 'REQUEST_URI' => '/my-login' ] ) );

		$this->assertSame( [ RequestSensitivity::GUARDED, 'login_page' ], [ $sensitivity->level, $sensitivity->reason ] );
	}

	public function test_shield_action_is_critical() :void {
		$req = $this->applyCurrentRequestState(
			[ 'REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/' ],
			[],
			[ ActionData::FIELD_ACTION => ActionData::FIELD_SHIELD, ActionData::FIELD_EXECUTE => 'capture_not_bot' ]
		);

		$sensitivity = ( new ResolveRequestSensitivity() )->resolve( $req );

		$this->assertSame( [ RequestSensitivity::CRITICAL, 'shield_action' ], [ $sensitivity->level, $sensitivity->reason ] );
	}

	public function test_a_filter_can_reclassify_a_route_but_not_to_an_unknown_level() :void {
		add_filter( 'shield/request_sensitivity', static fn( string $level, RequestFacts $facts ) => $facts->path === '/members/' ? RequestSensitivity::CRITICAL : 'unknown', 10, 2 );

		$members = ( new ResolveRequestSensitivity() )->resolve( $this->applyCurrentRequestState( [ 'REQUEST_URI' => '/members/' ] ) );
		$other = ( new ResolveRequestSensitivity() )->resolve( $this->applyCurrentRequestState( [ 'REQUEST_URI' => '/about/' ] ) );

		$this->assertSame( [ RequestSensitivity::CRITICAL, 'filtered' ], [ $members->level, $members->reason ] );
		$this->assertSame( [ RequestSensitivity::OPEN, 'content' ], [ $other->level, $other->reason ] );
	}
}
