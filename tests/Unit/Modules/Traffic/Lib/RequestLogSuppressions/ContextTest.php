<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Traffic\Lib\RequestLogSuppressions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogSuppressions\Context;
use FernleafSystems\Wordpress\Plugin\Shield\Request\ThisRequest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ContextTest extends BaseUnitTest {

	public function testRequestStateReadsReturnTheirTypesWhenTheStateIsUnset() :void {
		$context = new Context( $this->unsetRequest() );

		$this->assertFalse( $context->isAjax() );
		$this->assertFalse( $context->isSecurityAdmin() );
		$this->assertFalse( $context->isAdmin() );
		$this->assertSame( '', $context->method() );
		$this->assertSame( '', $context->path() );
		$this->assertSame( '', $context->scriptName() );
	}

	public function testSetRequestStateIsReadAsSet() :void {
		$req = $this->unsetRequest();
		$req->wp_is_ajax = true;
		$req->is_security_admin = true;
		$req->wp_is_admin = true;
		$req->path = '/wp-admin/admin-ajax.php';
		$req->script_name = 'admin-ajax.php';
		$context = new Context( $req );

		$this->assertTrue( $context->isAjax() );
		$this->assertTrue( $context->isSecurityAdmin() );
		$this->assertTrue( $context->isAdmin() );
		$this->assertSame( '/wp-admin/admin-ajax.php', $context->path() );
		$this->assertSame( 'admin-ajax.php', $context->scriptName() );
	}

	/**
	 * Request state as a request that exits early leaves it: nothing beyond what's assigned is set.
	 */
	private function unsetRequest() :ThisRequest {
		/** @var ThisRequest $req */
		$req = ( new \ReflectionClass( ThisRequest::class ) )->newInstanceWithoutConstructor();
		return $req;
	}
}
