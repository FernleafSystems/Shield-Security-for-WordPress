<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Traffic\Lib\Utility;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\LogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\Utility\ConvertLogsToFlatText;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\General;

class ConvertLogsToFlatTextTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'apply_filters' )->alias( static fn( string $tag, $value ) => $value );
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_wpgeneral' => new class extends General {
				public function getTimeStampForDisplay( $ts = null ) :string {
					return '2024-04-16 12:35:00';
				}
			},
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_flat_text_redacts_sensitive_query_values_and_preserves_ordinary_values() :void {
		$record = new LogRecord();
		$record->ip = '198.51.100.12';
		$record->uid = 0;
		$record->created_at = 1713278100;
		$record->verb = 'GET';
		$record->path = '/wp-login.php';
		$record->code = 200;
		$record->meta = [
			'query' => 'key=reset-secret&reauth=1',
		];

		$text = ( new ConvertLogsToFlatText() )->convertSingle( $record );

		$this->assertStringContainsString( 'key=redacted', $text );
		$this->assertStringContainsString( 'reauth=1', $text );
		$this->assertStringNotContainsString( 'reset-secret', $text );
	}
}
