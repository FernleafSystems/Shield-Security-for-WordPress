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

	public function test_build_subject_action_data_owns_subject_scope_and_optional_display_flags() :void {
		$helper = new ScanResultsDisplayOptions();

		$this->assertSame(
			[
				'display_context'         => ScanResultsDisplayOptions::DISPLAY_CONTEXT,
				'results_display_options' => $helper->activeOnly(),
				'subject_type'    => 'plugin',
				'subject_id'      => 'example-plugin/example-plugin.php',
			],
			$helper->buildSubjectActionData( 'plugin', 'example-plugin/example-plugin.php' )
		);

		$this->assertSame(
			[
				'display_context'         => ScanResultsDisplayOptions::DISPLAY_CONTEXT,
				'results_display_options' => [
					'include_ignored'  => true,
					'include_repaired' => false,
					'include_deleted'  => false,
					'ignored_only'     => true,
				],
				'subject_type'            => 'plugin',
				'subject_id'              => 'example-plugin/example-plugin.php',
			],
			$helper->buildSubjectActionData(
				'plugin',
				'example-plugin/example-plugin.php',
				[
					'include_ignored' => '1',
					'ignored_only'    => true,
				]
			)
		);
	}
}
