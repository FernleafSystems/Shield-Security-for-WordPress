<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueSecurityCheckProvider,
	ActionsQueueSecurityCheckSource
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ActionsQueueSecurityCheckSourceTest extends BaseUnitTest {

	public function test_source_merges_attention_and_assessment_rows_from_providers() :void {
		$source = new ActionsQueueSecurityCheckSourceTestDouble( [
			new ActionsQueueSecurityCheckProviderTestDouble( 'hidden_plugins', 'critical' ),
			new ActionsQueueSecurityCheckProviderTestDouble( 'second_check', 'good' ),
		] );

		$this->assertSame(
			[ 'hidden_plugins', 'second_check' ],
			\array_column( $source->attentionItems(), 'key' )
		);
		$this->assertSame(
			[ 'critical', 'good' ],
			\array_column( $source->assessmentRows(), 'status' )
		);
	}
}

class ActionsQueueSecurityCheckSourceTestDouble extends ActionsQueueSecurityCheckSource {

	private array $providers;

	public function __construct( array $providers ) {
		$this->providers = $providers;
	}

	protected function providers() :array {
		return $this->providers;
	}
}

class ActionsQueueSecurityCheckProviderTestDouble implements ActionsQueueSecurityCheckProvider {

	private string $key;
	private string $status;

	public function __construct( string $key, string $status ) {
		$this->key = $key;
		$this->status = $status;
	}

	public function attentionItems() :array {
		return [
			[
				'key'                => $this->key,
				'zone'               => 'scans',
				'source'             => 'security_check',
				'label'              => $this->key,
				'description'        => '',
				'count'              => 1,
				'ignored_count'      => 0,
				'severity'           => $this->status,
				'href'               => '',
				'action'             => '',
				'target'             => '',
				'supports_sub_items' => false,
			],
		];
	}

	public function assessmentRows() :array {
		return [
			[
				'key'               => $this->key,
				'label'             => $this->key,
				'description'       => '',
				'drill_bucket'      => 'critical',
				'item_icon_class'   => '',
				'status'            => $this->status,
				'status_label'      => $this->status,
				'status_icon_class' => '',
			],
		];
	}
}
