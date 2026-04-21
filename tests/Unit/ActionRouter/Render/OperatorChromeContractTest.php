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
			'title'           => 'Example Plugin',
			'actions'         => [
				[
					'label'            => 'Ignore All Results',
					'ajax_action_json' => '{"sub_action":"ignore_all"}',
					'type'             => 'deactivate',
					'confirm_text'     => 'Ignore all active results for Example Plugin?',
					'unexpected'       => 'discard',
				],
			],
		] );

		$this->assertSame( 'Example Plugin', $header[ 'title' ] );
		$this->assertSame(
			[
				[
					'kind'             => 'ajax',
					'label'            => 'Ignore All Results',
					'type'             => 'deactivate',
					'icon_class'       => '',
					'href'             => '',
					'ajax_action_json' => '{"sub_action":"ignore_all"}',
					'confirm_text'     => 'Ignore all active results for Example Plugin?',
				],
			],
			$header[ 'actions' ]
		);
	}

	public function test_normalize_header_discards_context_actions_without_renderable_payload() :void {
		$header = OperatorChromeContract::normalizeHeader( [
			'title'   => 'Example Plugin',
			'actions' => [
				[
					'kind'  => 'ajax',
					'label' => 'Broken Ajax Action',
				],
				[
					'kind'  => 'href',
					'label' => 'Broken Href Action',
				],
				[
					'kind'             => 'ajax',
					'label'            => 'Ignore All Results',
					'type'             => 'deactivate',
					'ajax_action_json' => '{"sub_action":"ignore_all"}',
				],
			],
		] );

		$this->assertSame(
			[
				[
					'kind'             => 'ajax',
					'label'            => 'Ignore All Results',
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
