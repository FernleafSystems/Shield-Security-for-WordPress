<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\InvestigateRenderContracts;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class InvestigateRenderContractsTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
	}

	public function test_normalize_table_contract_applies_defaults_for_missing_keys() :void {
		$normalized = ( new InvestigateRenderContractsTestDouble() )->normalizeTableContract( [
			'title'  => 'Recent Sessions',
			'status' => 'good',
		] );

		$this->assertSame( 'Recent Sessions', $normalized[ 'title' ] ?? '' );
		$this->assertSame( 'good', $normalized[ 'status' ] ?? '' );
		$this->assertSame( 'Full Log', $normalized[ 'full_log_text' ] ?? '' );
		$this->assertSame( 'btn btn-outline-secondary btn-sm', $normalized[ 'full_log_button_class' ] ?? '' );
		$this->assertFalse( $normalized[ 'is_flat' ] ?? true );
		$this->assertFalse( $normalized[ 'is_empty' ] ?? true );
		$this->assertSame( 'info', $normalized[ 'empty_status' ] ?? '' );
		$this->assertSame( '', $normalized[ 'empty_text' ] ?? 'missing' );
	}

	public function test_normalize_table_contract_does_not_coerce_wrong_types() :void {
		$normalized = ( new InvestigateRenderContractsTestDouble() )->normalizeTableContract( [
			'title'                  => 99,
			'status'                 => [ 'warning' ],
			'full_log_text'          => true,
			'full_log_button_class'  => null,
			'is_flat'                => 1,
			'is_empty'               => 'yes',
			'empty_status'           => false,
			'empty_text'             => 123,
		] );

		$this->assertSame( '', $normalized[ 'title' ] ?? 'missing' );
		$this->assertSame( 'info', $normalized[ 'status' ] ?? '' );
		$this->assertSame( 'Full Log', $normalized[ 'full_log_text' ] ?? '' );
		$this->assertSame( 'btn btn-outline-secondary btn-sm', $normalized[ 'full_log_button_class' ] ?? '' );
		$this->assertFalse( $normalized[ 'is_flat' ] ?? true );
		$this->assertFalse( $normalized[ 'is_empty' ] ?? true );
		$this->assertSame( 'info', $normalized[ 'empty_status' ] ?? '' );
		$this->assertSame( '', $normalized[ 'empty_text' ] ?? 'missing' );
	}
}

class InvestigateRenderContractsTestDouble {

	use InvestigateRenderContracts;

	public function normalizeTableContract( array $table ) :array {
		return $this->normalizeInvestigationTableContract( $table );
	}
}
