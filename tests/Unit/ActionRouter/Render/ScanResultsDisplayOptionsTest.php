<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ScanResultsDisplayOptions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ScanResultsDisplayOptionsTest extends BaseUnitTest {

	public function test_normalize_returns_full_four_flag_shape() :void {
		$options = ( new ScanResultsDisplayOptions() )->normalize( [
			'include_ignored'  => 'yes',
			'include_repaired' => 1,
			'ignored_only'     => true,
		] );

		$this->assertSame(
			[
				'include_ignored'  => true,
				'include_repaired' => true,
				'include_deleted'  => false,
				'ignored_only'     => true,
			],
			$options
		);
	}

	public function test_normalize_treats_false_like_strings_as_false() :void {
		$options = ( new ScanResultsDisplayOptions() )->normalize( [
			'include_ignored'  => 'false',
			'include_repaired' => '0',
			'include_deleted'  => 'off',
			'ignored_only'     => 'false',
		] );

		$this->assertSame(
			[
				'include_ignored'  => false,
				'include_repaired' => false,
				'include_deleted'  => false,
				'ignored_only'     => false,
			],
			$options
		);
	}

	public function test_current_options_from_action_data_defaults_to_active_only() :void {
		$helper = new ScanResultsDisplayOptions();
		$this->assertSame(
			[
				'include_ignored'  => false,
				'include_repaired' => false,
				'include_deleted'  => false,
				'ignored_only'     => false,
			],
			$helper->currentOptionsFromActionData( [
				'display_context' => ScanResultsDisplayOptions::DISPLAY_CONTEXT,
			] )
		);
	}

	public function test_active_and_ignored_keeps_terminal_filters_out_of_liveness_scope() :void {
		$helper = new ScanResultsDisplayOptions();

		$this->assertSame(
			[
				'include_ignored'  => true,
				'include_repaired' => false,
				'include_deleted'  => false,
				'ignored_only'     => false,
			],
			$helper->activeAndIgnored()
		);
	}
}
