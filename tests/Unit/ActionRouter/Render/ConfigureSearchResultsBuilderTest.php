<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ConfigureLandingViewBuilder,
	ConfigureSearchResultsBuilder
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestPluginUrls
};

class ConfigureSearchResultsBuilderTest extends BaseUnitTest {

	private array $optionDefs;
	private array $landingViewData;

	protected function setUp() :void {
		parent::setUp();

		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text )
				? ( \preg_replace( '/[^a-z0-9_-]/', '', \strtolower( \trim( $text ) ) ) ?? '' )
				: ''
		);
		Functions\when( 'sanitize_text_field' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \trim( $text ) : ''
		);
		Functions\when( 'rawurlencode_deep' )->alias(
			static function ( $value ) {
				if ( \is_array( $value ) ) {
					return \array_map(
						static fn( $item ) :string => \rawurlencode( (string)$item ),
						$value
					);
				}
				return \rawurlencode( (string)$value );
			}
		);
		Functions\when( 'add_query_arg' )->alias(
			static function ( array $params, string $url ) :string {
				if ( empty( $params ) ) {
					return $url;
				}

				$pairs = [];
				foreach ( $params as $key => $value ) {
					$pairs[] = $key.'='.$value;
				}

				return $url.( \str_contains( $url, '?' ) ? '&' : '?' ).\implode( '&', $pairs );
			}
		);

		$this->optionDefs = [
			'custom_silentcaptcha_toggle' => [
				'section'         => 'section_silentcaptcha',
				'name'            => 'Bot Challenge Toggle',
				'summary'         => 'silentCAPTCHA settings switch',
				'description'     => [ 'Enable silentCAPTCHA checks for comment flows.' ],
				'zone_comp_slugs' => [ 'silentcaptcha_component' ],
			],
			'comments_cooldown' => [
				'section'         => 'section_bot_comment_spam_common',
				'zone_comp_slugs' => [ 'module_spam' ],
			],
			'comments_cooldown_shadow' => [
				'section'         => 'section_bot_comment_spam_common',
				'name'            => 'Cooldown Shadow',
				'summary'         => 'Should not be claimed by an explicit option_keys row',
				'description'     => [ 'Explicit option ownership must not expand through the module slug.' ],
				'zone_comp_slugs' => [ 'module_spam' ],
			],
			'orphan_search_target' => [
				'section'         => 'section_defaults',
				'name'            => 'Orphan Search Target',
				'summary'         => 'Should not be shown',
				'description'     => [ 'No configure diagnosis row owns this option.' ],
				'zone_comp_slugs' => [ 'orphan_component' ],
			],
			'block_aggressive' => [
				'section'         => 'section_firewall_blocking_options',
				'name'            => 'Aggressive Scan',
				'summary'         => 'Aggressively Block Data',
				'description'     => [ 'Employs a set of aggressive rules to detect and block malicious data submitted to your site.' ],
				'zone_comp_slugs' => [ 'web_application_firewall', 'module_firewall' ],
			],
			'block_send_email' => [
				'section'         => 'section_firewall_blocking_options',
				'name'            => 'Send Email Report',
				'summary'         => 'Send Firewall Trigger Report Email',
				'description'     => [ 'Send firewall trigger report email.' ],
				'zone_comp_slugs' => [ 'module_firewall' ],
			],
			'hidden_shared_firewall_option' => [
				'section'         => 'section_firewall_blocking_options',
				'name'            => 'Hidden Shared Firewall Option',
				'summary'         => 'Shared owner must not fall back to general module rows',
				'description'     => [ 'This option has a specific owner that is not visible in Configure diagnosis rows.' ],
				'zone_comp_slugs' => [ 'missing_firewall_component', 'module_firewall' ],
			],
			'disable_xmlrpc' => [
				'section'         => 'section_apixml',
				'zone_comp_slugs' => [ 'xml_rpc_component' ],
			],
		];
		$this->landingViewData = $this->landingViewDataFixture();

		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_build_returns_flat_option_and_zone_results_for_silentcaptcha_search() :void {
		$results = $this->newBuilder()->build( 'silentcaptcha' );

		$this->assertNotSame( [], $results );
		$this->assertSame( [ 'zone', 'option' ], \array_column( $results, 'type' ) );
		$this->assertSame(
			[],
			\array_diff( \array_column( $results, 'type' ), [ 'option', 'zone' ] )
		);
		$this->assertSame( 'zone', $results[ 0 ][ 'type' ] ?? '' );
		$this->assertSame( 'Spam', $results[ 0 ][ 'label' ] ?? '' );
		$this->assertSame(
			$this->landingViewData[ 'tile_lookup' ][ 'spam' ][ 'summary' ],
			$results[ 0 ][ 'summary' ] ?? ''
		);
		$this->assertSame( 'bi bi-shield-fill', $results[ 0 ][ 'icon_class' ] ?? '' );
		$this->assertSame( [
			'key'        => 'spam',
			'label'      => 'Spam',
			'status'     => 'warning',
			'icon_class' => 'bi bi-shield-fill',
			'header'     => [
				'title' => 'Spam',
			],
		], \json_decode( (string)( $results[ 0 ][ 'selection_json' ] ?? '' ), true ) );
		$this->assertSame( '', $results[ 0 ][ 'focus_request_json' ] ?? 'missing' );
		$this->assertSame( 'option', $results[ 1 ][ 'type' ] ?? '' );
		$this->assertSame( 'Bot Challenge Toggle', $results[ 1 ][ 'label' ] ?? '' );
		$this->assertSame( 'silentCAPTCHA settings switch', $results[ 1 ][ 'summary' ] ?? '' );
		$this->assertSame( 'bi bi-sliders', $results[ 1 ][ 'icon_class' ] ?? '' );
		$this->assertSame( [
			'row_key'     => 'silentcaptcha_component',
			'config_item' => 'custom_silentcaptcha_toggle',
		], \json_decode( (string)( $results[ 1 ][ 'focus_request_json' ] ?? '' ), true ) );
		$this->assertSame(
			'/admin/zones/overview?zone=spam&row_key=silentcaptcha_component&config_item=custom_silentcaptcha_toggle',
			$results[ 1 ][ 'href' ] ?? ''
		);
	}

	public function test_build_uses_exact_row_keys_and_excludes_unresolvable_options() :void {
		$results = $this->newBuilder()->build( 'comments cooldown orphan shadow' );
		$optionResults = \array_values( \array_filter(
			$results,
			static fn( array $result ) :bool => ( $result[ 'type' ] ?? '' ) === 'option'
		) );

		$this->assertSame(
			[],
			\array_diff( \array_column( $results, 'type' ), [ 'option', 'zone' ] )
		);
		$this->assertNotContains( 'Orphan Search Target', \array_column( $optionResults, 'label' ) );
		$this->assertNotContains( 'Cooldown Shadow', \array_column( $optionResults, 'label' ) );
		$this->assertSame( 'Comments Cooldown', $optionResults[ 0 ][ 'label' ] ?? '' );
		$this->assertSame( 'Minimum Time Interval Between Comments (seconds)', $optionResults[ 0 ][ 'summary' ] ?? '' );
		$this->assertSame(
			'/admin/zones/overview?zone=spam&row_key=general_settings&config_item=comments_cooldown',
			$optionResults[ 0 ][ 'href' ] ?? ''
		);
		$this->assertStringNotContainsString( 'expand_id=', $optionResults[ 0 ][ 'href' ] ?? '' );
		$this->assertStringNotContainsString( 'zone_component_slug=', $optionResults[ 0 ][ 'href' ] ?? '' );
		$this->assertStringNotContainsString( 'option_keys=', $optionResults[ 0 ][ 'href' ] ?? '' );
	}

	public function test_shared_options_prefer_specific_component_rows_over_module_rows() :void {
		$results = $this->newBuilder()->build( 'aggressive email report' );
		$optionResults = [];
		foreach ( $results as $result ) {
			if ( ( $result[ 'type' ] ?? '' ) === 'option' ) {
				$optionResults[ $result[ 'label' ] ?? '' ] = $result;
			}
		}

		$this->assertSame(
			'/admin/zones/overview?zone=firewall&row_key=web_application_firewall&config_item=block_aggressive',
			$optionResults[ 'Aggressive Scan' ][ 'href' ] ?? ''
		);
		$this->assertSame(
			[
				'row_key'     => 'web_application_firewall',
				'config_item' => 'block_aggressive',
			],
			\json_decode( (string)( $optionResults[ 'Aggressive Scan' ][ 'focus_request_json' ] ?? '' ), true )
		);
		$this->assertSame(
			'/admin/zones/overview?zone=firewall&row_key=general_settings&config_item=block_send_email',
			$optionResults[ 'Send Email Report' ][ 'href' ] ?? ''
		);
		$this->assertArrayNotHasKey(
			'Hidden Shared Firewall Option',
			$optionResults,
			'Shared-owner options must not fall back to general module rows when their specific owner is not visible.'
		);
	}

	public function test_zone_search_matches_authored_tile_summary_text() :void {
		$results = $this->newBuilder()->build( 'stable firewall' );

		$this->assertNotSame( [], $results );
		$this->assertSame( 'zone', $results[ 0 ][ 'type' ] ?? '' );
		$this->assertSame( 'Firewall', $results[ 0 ][ 'label' ] ?? '' );
		$this->assertSame(
			$this->landingViewData[ 'tile_lookup' ][ 'firewall' ][ 'summary' ],
			$results[ 0 ][ 'summary' ] ?? ''
		);
	}

	public function test_hyphenated_option_queries_match_compact_and_split_dash_terms() :void {
		$xmlRpcResults = $this->newBuilder()->build( 'xml-rpc' );
		$xmlRpcCompactResults = $this->newBuilder()->build( 'xmlrpc' );

		$xmlRpcResult = $this->findOptionResultByConfigItem( $xmlRpcResults, 'disable_xmlrpc' );
		$xmlRpcCompactResult = $this->findOptionResultByConfigItem( $xmlRpcCompactResults, 'disable_xmlrpc' );

		$this->assertNotNull( $xmlRpcResult );
		$this->assertSame( 'option', $xmlRpcResult[ 'type' ] ?? '' );
		$this->assertSame(
			[
				'row_key'     => 'xml_rpc_component',
				'config_item' => 'disable_xmlrpc',
			],
			\json_decode( (string)( $xmlRpcResult[ 'focus_request_json' ] ?? '' ), true )
		);
		$this->assertSame(
			'/admin/zones/overview?zone=security&row_key=xml_rpc_component&config_item=disable_xmlrpc',
			$xmlRpcResult[ 'href' ] ?? ''
		);

		$this->assertNotNull( $xmlRpcCompactResult );
		$this->assertSame(
			[
				'row_key'     => 'xml_rpc_component',
				'config_item' => 'disable_xmlrpc',
			],
			\json_decode( (string)( $xmlRpcCompactResult[ 'focus_request_json' ] ?? '' ), true )
		);
		$this->assertSame(
			'/admin/zones/overview?zone=security&row_key=xml_rpc_component&config_item=disable_xmlrpc',
			$xmlRpcCompactResult[ 'href' ] ?? ''
		);
	}

	private function newBuilder() :ConfigureSearchResultsBuilder {
		return new ConfigureSearchResultsBuilder(
			new class( $this->landingViewData ) extends ConfigureLandingViewBuilder {
				private array $landingViewData;

				public function __construct( array $landingViewData ) {
					$this->landingViewData = $landingViewData;
				}

				public function build() :array {
					return $this->landingViewData;
				}
			}
		);
	}

	private function landingViewDataFixture() :array {
		return [
			'tile_lookup' => [
				'spam' => [
					'summary' => 'Stable spam summary.',
				],
				'firewall' => [
					'summary' => 'Stable firewall summary.',
				],
				'security' => [
					'summary' => 'Stable security summary.',
				],
			],
			'diagnoses' => [
				'spam' => [
					'zone_key'      => 'spam',
					'zone_label'    => 'Spam',
					'zone_icon_class' => 'bi bi-shield-fill',
					'zone_selection_json' => \json_encode( [
						'key'        => 'spam',
						'label'      => 'Spam',
						'status'     => 'warning',
						'icon_class' => 'bi bi-shield-fill',
						'header'     => [
							'title' => 'Spam',
						],
					], JSON_THROW_ON_ERROR ),
					'preview_text'  => 'Review silentCAPTCHA settings and comment protection.',
					'risk_context'  => 'Spam settings protect comment workflows.',
					'problem_rows'  => [
						[
							'key'           => 'silentcaptcha_component',
							'title'         => 'silentCAPTCHA Protection',
							'summary'       => 'Configure silentCAPTCHA coverage.',
							'explanations'  => [ 'silentCAPTCHA settings help block comment bots.' ],
							'expand_action' => [
								'id'              => 'configure-diagnosis-spam-silentcaptcha_component',
								'is_expandable'   => true,
								'data_attributes' => [
									'zone_component_slug' => 'silentcaptcha_component',
									'config_item'         => 'custom_silentcaptcha_toggle',
								],
							],
						],
					],
					'review_rows'   => [
						[
							'key'           => 'general_settings',
							'title'         => 'Comment Cooldown',
							'summary'       => 'Adjust comment throttling.',
							'explanations'  => [ 'Cooldown settings reduce repeated comment submissions.' ],
							'expand_action' => [
								'id'              => 'configure-diagnosis-spam-general_settings',
								'is_expandable'   => true,
								'data_attributes' => [
									'zone_component_slug' => 'module_spam',
									'option_keys'         => 'comments_cooldown',
								],
							],
						],
					],
					'healthy_rows'  => [],
				],
				'firewall' => [
					'zone_key'      => 'firewall',
					'zone_label'    => 'Firewall',
					'zone_icon_class' => 'bi bi-fire',
					'zone_selection_json' => \json_encode( [
						'key'        => 'firewall',
						'label'      => 'Firewall',
						'status'     => 'good',
						'icon_class' => 'bi bi-fire',
						'header'     => [
							'title' => 'Firewall',
						],
					], JSON_THROW_ON_ERROR ),
					'preview_text'  => 'Review firewall controls.',
					'risk_context'  => 'Firewall settings protect request flows.',
					'problem_rows'  => [],
					'review_rows'   => [
						[
							'key'           => 'general_settings',
							'title'         => 'Firewall General Settings',
							'summary'       => 'Adjust module-only firewall settings.',
							'explanations'  => [ 'General firewall settings.' ],
							'expand_action' => [
								'id'              => 'configure-diagnosis-firewall-general_settings',
								'is_expandable'   => true,
								'data_attributes' => [
									'zone_component_slug' => 'module_firewall',
								],
							],
						],
					],
					'healthy_rows'  => [
						[
							'key'           => 'web_application_firewall',
							'title'         => 'Web Application Firewall',
							'summary'       => 'Configure WAF rules.',
							'explanations'  => [ 'Aggressive WAF rules block bad requests.' ],
							'expand_action' => [
								'id'              => 'configure-diagnosis-firewall-web_application_firewall',
								'is_expandable'   => true,
								'data_attributes' => [
									'zone_component_slug' => 'web_application_firewall',
								],
							],
						],
					],
				],
				'security' => [
					'zone_key'      => 'security',
					'zone_label'    => 'Security',
					'zone_icon_class' => 'bi bi-lock-fill',
					'zone_selection_json' => \json_encode( [
						'key'        => 'security',
						'label'      => 'Security',
						'status'     => 'warning',
						'icon_class' => 'bi bi-lock-fill',
						'header'     => [
							'title' => 'Security',
						],
					], JSON_THROW_ON_ERROR ),
					'preview_text'  => 'Review API and XML-RPC controls.',
					'risk_context'  => 'Security settings protect exposed WordPress system interfaces.',
					'problem_rows'  => [],
					'review_rows'   => [
						[
							'key'           => 'xml_rpc_component',
							'title'         => 'XML-RPC Controls',
							'summary'       => 'Review XML-RPC hardening.',
							'explanations'  => [ 'Disable XML-RPC when it is not required.' ],
							'expand_action' => [
								'id'              => 'configure-diagnosis-security-xml_rpc_component',
								'is_expandable'   => true,
								'data_attributes' => [
									'zone_component_slug' => 'xml_rpc_component',
									'config_item'         => 'disable_xmlrpc',
								],
							],
						],
					],
					'healthy_rows'  => [],
				],
			],
		];
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new UnitTestPluginUrls();
		$controller->labels = new class {
			public string $Name = 'Shield';

			public function getBrandName( string $brand ) :string {
				return $brand === 'silentcaptcha' ? 'silentCAPTCHA' : $brand;
			}
		};
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};
		$controller->cfg = (object)[
			'configuration' => (object)[
				'options'  => $this->optionDefs,
				'sections' => [],
			],
		];
		$controller->opts = new class( $this->optionDefs ) {
			private array $optionDefs;

			public function __construct( array $optionDefs ) {
				$this->optionDefs = $optionDefs;
			}

			public function optDef( string $key ) :array {
				return $this->optionDefs[ $key ] ?? [];
			}
		};

		PluginControllerInstaller::install( $controller );
	}

	private function findOptionResultByConfigItem( array $results, string $configItem ) :?array {
		foreach ( $results as $result ) {
			if ( !\is_array( $result ) || ( $result[ 'type' ] ?? '' ) !== 'option' ) {
				continue;
			}

			$focusRequest = \json_decode( (string)( $result[ 'focus_request_json' ] ?? '' ), true );
			if ( \is_array( $focusRequest ) && ( $focusRequest[ 'config_item' ] ?? '' ) === $configItem ) {
				return $result;
			}
		}

		return null;
	}
}
