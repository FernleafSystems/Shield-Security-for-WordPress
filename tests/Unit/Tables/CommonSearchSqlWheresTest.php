<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\{
	BaseBuildSearchPanesData,
	BaseBuildTableData
};
use FernleafSystems\Wordpress\Services\Core\Users;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class CommonSearchSqlWheresTest extends BaseUnitTest {

	private $origServiceItems;

	private $origServices;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'esc_sql' )->returnArg();
		$this->origServiceItems = $this->getServicesProperty( 'items' )->getValue();
		$this->origServices = $this->getServicesProperty( 'services' )->getValue();
	}

	protected function tearDown() :void {
		$this->getServicesProperty( 'items' )->setValue( null, $this->origServiceItems );
		$this->getServicesProperty( 'services' )->setValue( null, $this->origServices );
		parent::tearDown();
	}

	public function test_request_id_builds_exact_where_clause() :void {
		$builder = $this->createBuilder( [
			'request_id' => 'abc123',
		] );

		$wheres = $builder->exposeBuildWheresFromCommonSearchParams();

		$this->assertCount( 1, $wheres );
		$this->assertSame( "`req`.`req_id`='abc123'", $wheres[ 0 ] );
	}

	public function test_empty_request_id_does_not_add_where_clause() :void {
		$builder = $this->createBuilder( [] );

		$wheres = $builder->exposeBuildWheresFromCommonSearchParams();

		$this->assertSame( [], $wheres );
	}

	public function test_user_email_builds_uid_where_clause() :void {
		$this->injectWpUsersService( new class extends Users {
			public function getUserByEmail( string $email ) {
				return (object)[
					'ID' => 37,
				];
			}
		} );

		$builder = $this->createBuilder( [
			'user_email' => 'person@example.com',
		] );

		$wheres = $builder->exposeBuildWheresFromCommonSearchParams();

		$this->assertCount( 1, $wheres );
		$this->assertSame( "`req`.`uid`=37", $wheres[ 0 ] );
	}

	private function createBuilder( array $parsedSearch ) :object {
		return new class( $parsedSearch ) extends BaseBuildTableData {

			private array $parsedSearch;

			public function __construct( array $parsedSearch ) {
				$this->parsedSearch = \array_merge( [
					'remaining'  => '',
					'ip'         => '',
					'request_id' => '',
					'user_id'    => '',
					'user_name'  => '',
					'user_email' => '',
				], $parsedSearch );
				$this->table_data = [
					'search'      => [ 'value' => '' ],
					'searchPanes' => [],
					'start'       => 0,
					'length'      => 10,
					'order'       => [],
					'columns'     => [],
				];
			}

			protected function parseSearchText() :array {
				return $this->parsedSearch;
			}

			public function exposeBuildWheresFromCommonSearchParams() :array {
				return $this->buildWheresFromCommonSearchParams();
			}

			protected function countTotalRecords() :int {
				return 0;
			}

			protected function countTotalRecordsFiltered() :int {
				return 0;
			}

			protected function buildTableRowsFromRawRecords( array $records ) :array {
				return [];
			}

			protected function getSearchPanesDataBuilder() :BaseBuildSearchPanesData {
				throw new \RuntimeException( 'Not implemented' );
			}
		};
	}

	private function injectWpUsersService( Users $users ) :void {
		$this->getServicesProperty( 'items' )->setValue( null, [
			'service_wpusers' => $users,
		] );
		$this->getServicesProperty( 'services' )->setValue( null, null );
	}

	private function getServicesProperty( string $propertyName ) :\ReflectionProperty {
		$reflection = new \ReflectionClass( Services::class );
		$property = $reflection->getProperty( $propertyName );
		$property->setAccessible( true );
		return $property;
	}
}
