<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\OperatorChromeContract;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class OperatorChromeContractTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'sanitize_key' )->alias(
			static fn( string $value ) :string => \strtolower( \preg_replace( '/[^a-z0-9_\\-]/', '', $value ) ?? '' )
		);
	}

	public function test_normalize_header_keeps_only_supported_context_action_fields() :void {
		$header = OperatorChromeContract::normalizeHeader( [
			'title'           => 'example_plugin_title',
			'actions'         => [
				[
					'label'            => 'ignore_all_results_label',
					'ajax_action_json' => '{"sub_action":"ignore_all"}',
					'type'             => 'deactivate',
					'confirm_text'     => 'confirm_ignore_all_results',
					'unexpected'       => 'discard',
				],
			],
		] );

		$this->assertSame( 'example_plugin_title', $header[ 'title' ] );
		$this->assertSame(
			[
				[
					'kind'             => 'ajax',
					'label'            => 'ignore_all_results_label',
					'type'             => 'deactivate',
					'icon_class'       => '',
					'href'             => '',
					'ajax_action_json' => '{"sub_action":"ignore_all"}',
					'confirm_text'     => 'confirm_ignore_all_results',
				],
			],
			$header[ 'actions' ]
		);
	}

	public function test_normalize_header_discards_context_actions_without_renderable_payload() :void {
		$header = OperatorChromeContract::normalizeHeader( [
			'title'   => 'example_plugin_title',
			'actions' => [
				[
					'kind'  => 'ajax',
					'label' => 'broken_ajax_action',
				],
				[
					'kind'  => 'href',
					'label' => 'broken_href_action',
				],
				[
					'kind'             => 'ajax',
					'label'            => 'ignore_all_results_label',
					'type'             => 'deactivate',
					'ajax_action_json' => '{"sub_action":"ignore_all"}',
				],
			],
		] );

		$this->assertSame(
			[
				[
					'kind'             => 'ajax',
					'label'            => 'ignore_all_results_label',
					'type'             => 'deactivate',
					'icon_class'       => '',
					'href'             => '',
					'ajax_action_json' => '{"sub_action":"ignore_all"}',
					'confirm_text'     => '',
				],
			],
			$header[ 'actions' ]
		);
	}
}
