<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables\Traffic;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\{
	LogRecord,
	Ops\Handler
};
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Traffic\BuildTrafficTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\InvokesNonPublicMethods;

class BuildTrafficTableDataPathRedactionTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'apply_filters' )->alias( static fn( string $tag, $value ) => $value );
		Functions\when( 'esc_html' )->alias(
			static fn( string $text ) :string => \htmlspecialchars( $text, \ENT_QUOTES, 'UTF-8' )
		);
	}

	public function test_page_column_uses_redacted_display_path_for_http_rows() :void {
		$builder = new BuildTrafficTableData();
		$this->setLogRecord( $builder, $this->record( Handler::TYPE_HTTP, '/wp-login.php', 'key=reset-secret&reauth=1' ) );

		$text = $this->pageColumnText( $builder );

		$this->assertStringContainsString( 'key=redacted', $text );
		$this->assertStringContainsString( 'reauth=1', $text );
		$this->assertStringNotContainsString( 'reset-secret', $text );
	}

	public function test_page_column_redacts_wp_cli_args_with_same_query_redactor() :void {
		$builder = new BuildTrafficTableData();
		$this->setLogRecord( $builder, $this->record( Handler::TYPE_WPCLI, 'shield scan', 'key=cli-secret&force=1' ) );

		$text = $this->pageColumnText( $builder );

		$this->assertStringContainsString( 'key=redacted', $text );
		$this->assertStringContainsString( 'force=1', $text );
		$this->assertStringNotContainsString( 'cli-secret', $text );
	}

	private function record( string $type, string $path, string $query ) :LogRecord {
		$record = new LogRecord();
		$record->type = $type;
		$record->verb = 'GET';
		$record->path = $path;
		$record->meta = [
			'query' => $query,
		];
		return $record;
	}

	private function setLogRecord( BuildTrafficTableData $builder, LogRecord $record ) :void {
		$property = new \ReflectionProperty( $builder, 'log' );
		$property->setAccessible( true );
		$property->setValue( $builder, $record );
	}

	private function pageColumnText( BuildTrafficTableData $builder ) :string {
		return \html_entity_decode(
			\strip_tags( (string)$this->invokeNonPublicMethod( $builder, 'getColumnContent_Page' ) ),
			\ENT_QUOTES,
			'UTF-8'
		);
	}
}
