<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ActivityLog\BuildActivityLogTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Traffic\BuildTrafficTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class RequestIdWhereIntegrationTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'esc_sql' )->returnArg();
	}

	public function test_activity_log_builder_includes_request_id_where() :void {
		$builder = new class extends BuildActivityLogTableData {

			protected function parseSearchText() :array {
				return [
					'remaining'  => '',
					'ip'         => '',
					'request_id' => 'abc123',
					'user_id'    => '',
					'user_name'  => '',
					'user_email' => '',
				];
			}

			public function __construct() {
				$this->table_data = [
					'searchPanes' => [],
				];
			}

			public function exposeBuildWheresFromSearchParams() :array {
				return $this->buildWheresFromSearchParams();
			}
		};

		$wheres = $builder->exposeBuildWheresFromSearchParams();

		$this->assertContains( "`req`.`req_id`='abc123'", $wheres );
	}

	public function test_traffic_builder_includes_request_id_where() :void {
		$builder = new class extends BuildTrafficTableData {

			protected function parseSearchText() :array {
				return [
					'remaining'  => '',
					'ip'         => '',
					'request_id' => 'abc123',
					'user_id'    => '',
					'user_name'  => '',
					'user_email' => '',
				];
			}

			public function __construct() {
				$this->table_data = [
					'searchPanes' => [],
				];
			}

			public function exposeBuildWheresFromSearchParams() :array {
				return $this->buildWheresFromSearchParams();
			}
		};

		$wheres = $builder->exposeBuildWheresFromSearchParams();

		$this->assertContains( "`req`.`req_id`='abc123'", $wheres );
	}
}
