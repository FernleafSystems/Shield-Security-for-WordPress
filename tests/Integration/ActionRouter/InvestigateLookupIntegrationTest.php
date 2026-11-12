<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\InvestigateLookup
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class InvestigateLookupIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'ips' );
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	/**
	 * @return list<array{kind:string,label:string,icon_class:string,results:list<array{value:string,title:string,meta:string}>}>
	 */
	private function lookup( string $kind, string $search ) :array {
		$payload = ( new ActionProcessor() )->processAction( InvestigateLookup::SLUG, [
			'kind'   => $kind,
			'search' => $search,
		] )->payload();
		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		return $payload[ 'groups' ] ?? [];
	}

	private function group( array $groups, string $kind ) :?array {
		foreach ( $groups as $group ) {
			if ( $group[ 'kind' ] === $kind ) {
				return $group;
			}
		}
		return null;
	}

	public function test_nothing_typed_with_any_kind_returns_nothing() :void {
		$this->assertSame( [], $this->lookup( 'any', '' ) );
	}

	public function test_any_kind_groups_matching_users_and_plugins() :void {
		$userLogin = 'lookup_any_'.\wp_rand( 1000, 9999 );
		$userId = \wp_create_user( $userLogin, \wp_generate_password( 24, true, true ), $userLogin.'@example.com' );
		$this->assertIsInt( $userId );

		$users = $this->group( $this->lookup( 'any', $userLogin ), 'user' );
		$this->assertNotNull( $users );
		$this->assertSame( (string)$userId, $users[ 'results' ][ 0 ][ 'value' ] );
		$this->assertSame( $userLogin, $users[ 'results' ][ 0 ][ 'title' ] );
		$this->assertNotSame( '', $users[ 'label' ] );

		$plugin = \array_values( Services::WpPlugins()->getPluginsAsVo() )[ 0 ] ?? null;
		$this->assertNotNull( $plugin );
		$plugins = $this->group( $this->lookup( 'any', (string)$plugin->Name ), 'plugin' );
		$this->assertNotNull( $plugins );
		$this->assertContains( $plugin->file, \array_column( $plugins[ 'results' ], 'value' ) );
	}

	public function test_a_kind_with_nothing_typed_lists_that_kind() :void {
		$groups = $this->lookup( 'plugin', '' );
		$this->assertCount( 1, $groups );
		$this->assertSame( 'plugin', $groups[ 0 ][ 'kind' ] );
		$this->assertCount( \count( Services::WpPlugins()->getPluginsAsVo() ), $groups[ 0 ][ 'results' ] );
	}

	public function test_core_is_found_by_name() :void {
		$core = $this->group( $this->lookup( 'any', 'wordpress' ), 'core' );
		$this->assertNotNull( $core );
		$this->assertStringContainsString( Services::WpGeneral()->getVersion(), $core[ 'results' ][ 0 ][ 'title' ] );
	}

	public function test_an_unknown_kind_is_treated_as_any() :void {
		$this->assertSame( [], $this->lookup( 'nonsense', '' ) );
	}
}
