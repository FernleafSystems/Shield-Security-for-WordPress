<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SelectSearchData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class SelectSearchDataConfigSearchTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias(
			fn( string $text ) :string => $text
		);
		Functions\when( 'sanitize_text_field' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \trim( $text ) : ''
		);
		$this->installControllerStubs();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_build_matches_compact_dash_config_search_terms() :void {
		$results = ( new SelectSearchData() )->build( 'xmlrpc' );

		$this->assertTrue( $this->resultsContainChildId( $results, 'config_disable_xmlrpc' ) );
	}

	private function installControllerStubs() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->labels = new class {
			public string $Name = 'Shield Security';
			public string $url_helpdesk = 'https://help.example.com';

			public function getBrandName( string $brand ) :string {
				return $brand;
			}
		};
		$controller->svgs = new class {
			public function iconClass( string $slug ) :string {
				return 'icon-'.$slug;
			}
		};
		$controller->plugin_urls = new class {
			public function adminTopNav( string $nav, string $subnav = '' ) :string {
				return '/admin/'.$nav.'/'.$subnav;
			}

			public function cfgForZoneComponent( string $componentSlug ) :string {
				return '/zone-component/'.$componentSlug;
			}

			public function cfgForOpt( string $optKey ) :string {
				return '/config/'.$optKey;
			}
		};
		$controller->cfg = (object)[
			'configuration' => (object)[
				'options' => [
					'disable_xmlrpc' => [
						'section'         => 'section_apixml',
						'zone_comp_slugs' => [ 'xml_rpc_component' ],
					],
				],
			],
		];
		$controller->caps = (object)[];
		$controller->comps = (object)[
			'opts_lookup' => (object)[],
		];
		$controller->opts = new class {
			public function optDef( string $key ) :array {
				switch ( $key ) {
					case 'disable_xmlrpc':
						return [
							'section'         => 'section_apixml',
							'name'            => 'Disable XML-RPC',
							'summary'         => 'Disable The XML-RPC System',
							'description'     => 'Checking this option will completely turn off the whole XML-RPC system.',
							'zone_comp_slugs' => [ 'xml_rpc_component' ],
						];
					case 'user_form_providers':
					case 'form_spam_providers':
						return [
							'value_options' => [],
						];
					default:
						return [];
				}
			}
		};

		PluginControllerInstaller::install( $controller );
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
