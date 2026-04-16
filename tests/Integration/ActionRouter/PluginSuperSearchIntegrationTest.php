<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\PluginSuperSearch
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class PluginSuperSearchIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'ips' );
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	public function test_super_search_returns_matching_ip_for_numeric_fragment() :void {
		TestDataFactory::createIpRecord( '212.159.74.132' );

		$payload = ( new ActionProcessor() )->processAction( PluginSuperSearch::SLUG, [
			'search' => '212',
		] )->payload();

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertTrue(
			$this->resultsContainIp( (array)( $payload[ 'results' ] ?? [] ), '212.159.74.132' ),
			'Expected super search to include IP lookup results for numeric fragments.'
		);
	}

	public function test_super_search_matches_compact_dash_config_queries() :void {
		$payload = ( new ActionProcessor() )->processAction( PluginSuperSearch::SLUG, [
			'search' => 'xmlrpc',
		] )->payload();

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertTrue(
			$this->resultsContainChildId( (array)( $payload[ 'results' ] ?? [] ), 'config_disable_xmlrpc' ),
			'Expected super search to include the XML-RPC config option for compact dash queries.'
		);
	}

	private function resultsContainIp( array $groups, string $expectedIp ) :bool {
		foreach ( $groups as $group ) {
			if ( !\is_array( $group ) || !\is_array( $group[ 'children' ] ?? null ) ) {
				continue;
			}
			foreach ( $group[ 'children' ] as $child ) {
				if ( !\is_array( $child ) ) {
					continue;
				}
				if ( (string)( $child[ 'ip' ] ?? '' ) === $expectedIp ) {
					return true;
				}
			}
		}
		return false;
	}

	private function resultsContainChildId( array $groups, string $expectedId ) :bool {
		foreach ( $groups as $group ) {
			if ( !\is_array( $group ) || !\is_array( $group[ 'children' ] ?? null ) ) {
				continue;
			}
			foreach ( $group[ 'children' ] as $child ) {
				if ( !\is_array( $child ) ) {
					continue;
				}
				if ( (string)( $child[ 'id' ] ?? '' ) === $expectedId ) {
					return true;
				}
			}
		}
		return false;
	}
}
