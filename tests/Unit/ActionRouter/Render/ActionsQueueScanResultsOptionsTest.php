<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueScanResultsOptions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ActionsQueueScanResultsOptionsTest extends BaseUnitTest {

	public function test_normalize_returns_full_four_flag_shape() :void {
		$options = ( new ActionsQueueScanResultsOptions() )->normalize( [
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

	public function test_forced_ignored_options_preserve_stored_repaired_and_deleted_flags() :void {
		$helper = new class extends ActionsQueueScanResultsOptions {
			public function storedOptions() :array {
				return $this->normalize( [
					'include_repaired' => true,
					'include_deleted'  => true,
				] );
			}
		};

		$this->assertSame(
			[
				'include_ignored'  => true,
				'include_repaired' => true,
				'include_deleted'  => true,
				'ignored_only'     => true,
			],
			$helper->forcedIgnoredOptions()
		);
	}

	public function test_current_options_from_action_data_falls_back_to_stored_options() :void {
		$helper = new class extends ActionsQueueScanResultsOptions {
			public function storedOptions() :array {
				return $this->normalize( [
					'include_ignored'  => true,
					'include_repaired' => true,
				] );
			}
		};

		$this->assertSame(
			[
				'include_ignored'  => true,
				'include_repaired' => true,
				'include_deleted'  => false,
				'ignored_only'     => false,
			],
			$helper->currentOptionsFromActionData( [
				'display_context' => ActionsQueueScanResultsOptions::DISPLAY_CONTEXT,
			] )
		);
	}

	public function test_build_subject_action_data_owns_subject_scope_and_optional_display_flags() :void {
		$helper = new ActionsQueueScanResultsOptions();

		$this->assertSame(
			[
				'display_context' => ActionsQueueScanResultsOptions::DISPLAY_CONTEXT,
				'subject_type'    => 'plugin',
				'subject_id'      => 'example-plugin/example-plugin.php',
			],
			$helper->buildSubjectActionData( 'plugin', 'example-plugin/example-plugin.php' )
		);

		$this->assertSame(
			[
				'display_context'         => ActionsQueueScanResultsOptions::DISPLAY_CONTEXT,
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
