<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Controller\Config\Opts;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\OptionAuditValueFormatter;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class OptionAuditValueFormatterTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
		Functions\when( 'wp_json_encode' )->alias( static fn( $value ) :string => (string)\json_encode( $value ) );
	}

	public function test_sensitive_scalar_is_redacted() :void {
		$this->assertSame(
			'redacted',
			$this->formatter()->format( [
				'type'      => 'text',
				'sensitive' => true,
			], 'secret-value' )
		);
	}

	public function test_sensitive_array_is_redacted() :void {
		$this->assertSame(
			'redacted',
			$this->formatter()->format( [
				'type'      => 'array',
				'sensitive' => true,
			], [ 'first', 'second' ] )
		);
	}

	public function test_checkbox_value_is_formatted_for_audit_log() :void {
		$formatter = $this->formatter();

		$this->assertSame( 'on', $formatter->format( [ 'type' => 'checkbox' ], 'Y' ) );
		$this->assertSame( 'off', $formatter->format( [ 'type' => 'checkbox' ], 'N' ) );
	}

	public function test_array_values_are_joined_for_audit_log() :void {
		$this->assertSame(
			'first, second',
			$this->formatter()->format( [ 'type' => 'array' ], [ 'first', 'second' ] )
		);
	}

	public function test_multiple_select_values_are_joined_for_audit_log() :void {
		$this->assertSame(
			'first, second',
			$this->formatter()->format( [ 'type' => 'multiple_select' ], [ 'first', 'second' ] )
		);
	}

	public function test_scalar_value_is_returned_for_audit_log() :void {
		$this->assertSame( '42', $this->formatter()->format( [ 'type' => 'integer' ], 42 ) );
	}

	public function test_unhandled_non_scalar_value_uses_json_fallback() :void {
		$this->assertSame(
			'{"nested":true} (JSON Encoded)',
			$this->formatter()->format( [ 'type' => 'custom' ], [ 'nested' => true ] )
		);
	}

	private function formatter() :OptionAuditValueFormatter {
		return new OptionAuditValueFormatter();
	}
}
