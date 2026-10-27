<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables\Traffic;

use Brain\Monkey\Functions;
use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\{
	LogRecord,
	Ops\Handler
};
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Traffic\BuildTrafficTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\InvokesNonPublicMethods;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{PluginControllerInstaller, ServicesState, UnitTestControllerFactory};
use FernleafSystems\Wordpress\Services\Core\{General, Request, Users};
use FernleafSystems\Wordpress\Services\Utilities\IpUtils;

class BuildTrafficTableDataPathRedactionTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'apply_filters' )->alias( static fn( string $tag, $value ) => $value );
		Functions\when( 'esc_html' )->alias(
			static fn( string $text ) :string => \htmlspecialchars( $text, \ENT_QUOTES, 'UTF-8' )
		);
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_ip' => new IpUtils(),
			'service_request' => new class extends Request {
				public function carbon( $setTimezone = false, bool $userLocale = true ) :Carbon {
					return Carbon::createFromTimestampUTC( 1713278100 );
				}
			},
			'service_wpgeneral' => new class extends General {
				public function getTimeStringForDisplay( $ts = null, $bShowTime = true, $bShowDate = true ) {
					return '2024-04-16';
				}
			},
		] );
		UnitTestControllerFactory::install( null, null, (object)[
			'this_req' => (object)[ 'ip' => '', 'ip_meta_record' => (object)[ 'country_iso2' => '' ] ],
		] );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_hidden_path_cell_escapes_logged_request_path() :void {
		$path = '/catalog<img/src=x/onerror=alert(1)>';
		$record = $this->record( Handler::TYPE_HTTP, $path, 'x=1' );
		$record->ip = '';
		$record->uid = 0;
		$record->code = 200;
		$record->created_at = 1713278100;
		$record->meta = \array_merge( $record->meta, [ 'ua' => '' ] );
		$rows = $this->invokeNonPublicMethod( new BuildTrafficTableData(), 'buildTableRowsFromRawRecords', [ [ $record ] ] );

		$this->assertSame( \htmlspecialchars( $path, \ENT_QUOTES, 'UTF-8' ), $rows[ 0 ][ 'path' ] );
		$dom = new \DOMDocument();
		$dom->loadHTML( '<td>'.$rows[ 0 ][ 'path' ].'</td>' );
		$this->assertSame( 0, $dom->getElementsByTagName( 'img' )->length );
		$this->assertSame( $path, $dom->getElementsByTagName( 'td' )->item( 0 )->textContent );
	}

	public function test_page_column_escapes_logged_request_verb() :void {
		$builder = new BuildTrafficTableData();
		$record = $this->record( Handler::TYPE_HTTP, '/wp-login.php', '' );
		$record->verb = '<b>x';
		$this->setLogRecord( $builder, $record );

		$html = (string)$this->invokeNonPublicMethod( $builder, 'getColumnContent_Page' );

		$this->assertStringNotContainsString( '<B>', $html );
		$this->assertStringContainsString( 'POST', $this->pageColumnTextForVerb( 'post' ) );
		$this->assertStringContainsString( '<B>X: /wp-login.php', $this->pageColumnText( $builder ) );
	}

	public function test_user_display_escapes_user_login_and_keeps_ordinary_logins_readable() :void {
		Functions\when( 'esc_url' )->returnArg();
		$users = new class extends Users {
			public array $logins = [];

			public function getUserById( $id ) {
				$user = new \WP_User();
				$user->ID = (int)$id;
				$user->user_login = $this->logins[ (int)$id ] ?? '';
				return $user;
			}

			public function getAdminUrl_ProfileEdit( $user = null ) :string {
				return 'https://example.com/wp-admin/user-edit.php?user_id='.$user->ID;
			}
		};
		$users->logins = [
			7 => 'o\'brien.smith@example',
			8 => '<img src=x>',
		];
		ServicesState::installItems( [ 'service_wpusers' => $users ] );
		$builder = new BuildTrafficTableData();

		$ordinary = (string)$this->invokeNonPublicMethod( $builder, 'getTrafficUserDisplay', [ 7 ] );
		$markup = (string)$this->invokeNonPublicMethod( $builder, 'getTrafficUserDisplay', [ 8 ] );

		$this->assertSame( 'o\'brien.smith@example', \html_entity_decode( \strip_tags( $ordinary ), \ENT_QUOTES, 'UTF-8' ) );
		$this->assertStringNotContainsString( '<img', $markup );
		$this->assertSame( '<img src=x>', \html_entity_decode( \strip_tags( $markup ), \ENT_QUOTES, 'UTF-8' ) );
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

	private function pageColumnTextForVerb( string $verb ) :string {
		$builder = new BuildTrafficTableData();
		$record = $this->record( Handler::TYPE_HTTP, '/', '' );
		$record->verb = $verb;
		$this->setLogRecord( $builder, $record );
		return $this->pageColumnText( $builder );
	}

	private function pageColumnText( BuildTrafficTableData $builder ) :string {
		return \html_entity_decode(
			\strip_tags( (string)$this->invokeNonPublicMethod( $builder, 'getColumnContent_Page' ) ),
			\ENT_QUOTES,
			'UTF-8'
		);
	}
}
