<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\DBs\ReqLogs;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class HandlerTest extends BaseUnitTest {

	public function test_get_type_name_returns_mcp_for_mcp_type() :void {
		$this->assertSame( 'MCP', Handler::GetTypeName( Handler::TYPE_MCP ) );
	}
}
