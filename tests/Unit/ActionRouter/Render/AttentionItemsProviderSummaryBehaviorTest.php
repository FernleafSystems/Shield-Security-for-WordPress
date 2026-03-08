<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\AttentionItemsProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class AttentionItemsProviderSummaryBehaviorTest extends BaseUnitTest {

	public function test_build_queue_items_preserves_description_and_target_contract() :void {
		$items = ( new AttentionItemsProviderSummaryTestDouble( [
			[
				'key'      => 'system_ssl_certificate',
				'zone'     => 'maintenance',
				'label'    => 'SSL Certificate',
				'count'    => 1,
				'severity' => 'warning',
				'text'     => 'SSL certificate requires review.',
				'href'     => 'https://example.com/ssl',
				'action'   => 'Review',
				'target'   => '_blank',
			],
		] ) )->buildQueueItems();

		$this->assertSame( 'SSL Certificate', (string)( $items[ 0 ][ 'label' ] ?? '' ) );
		$this->assertSame( 'SSL certificate requires review.', (string)( $items[ 0 ][ 'description' ] ?? '' ) );
		$this->assertSame( 'https://example.com/ssl', (string)( $items[ 0 ][ 'href' ] ?? '' ) );
		$this->assertSame( 'Review', (string)( $items[ 0 ][ 'action' ] ?? '' ) );
		$this->assertSame( '_blank', (string)( $items[ 0 ][ 'target' ] ?? '' ) );
	}

	public function test_empty_items_are_all_clear_and_good() :void {
		$summary = ( new AttentionItemsProviderSummaryTestDouble( [] ) )->buildActionSummary();

		$this->assertSame( 0, (int)( $summary[ 'total' ] ?? -1 ) );
		$this->assertSame( 'good', (string)( $summary[ 'severity' ] ?? '' ) );
		$this->assertTrue( (bool)( $summary[ 'is_all_clear' ] ?? false ) );
	}

	public function test_first_critical_item_sets_critical_severity() :void {
		$summary = ( new AttentionItemsProviderSummaryTestDouble( [
			[
				'key'      => 'critical-item',
				'severity' => 'critical',
				'count'    => 1,
			],
			[
				'key'      => 'warning-item',
				'severity' => 'warning',
				'count'    => 1,
			],
		] ) )->buildActionSummary();

		$this->assertSame( 2, (int)( $summary[ 'total' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $summary[ 'severity' ] ?? '' ) );
		$this->assertFalse( (bool)( $summary[ 'is_all_clear' ] ?? true ) );
	}

	public function test_unexpected_severity_falls_back_to_warning() :void {
		$summary = ( new AttentionItemsProviderSummaryTestDouble( [
			[
				'key'      => 'unexpected-item',
				'severity' => 'unexpected',
				'count'    => 1,
			],
		] ) )->buildActionSummary();

		$this->assertSame( 1, (int)( $summary[ 'total' ] ?? 0 ) );
		$this->assertSame( 'warning', (string)( $summary[ 'severity' ] ?? '' ) );
		$this->assertFalse( (bool)( $summary[ 'is_all_clear' ] ?? true ) );
	}
}

class AttentionItemsProviderSummaryTestDouble extends AttentionItemsProvider {

	/** @var array<int, array<string, mixed>> */
	private array $items;

	/** @param array<int, array<string, mixed>> $items */
	public function __construct( array $items ) {
		$this->items = $items;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function buildActionItems() :array {
		return $this->items;
	}
}
