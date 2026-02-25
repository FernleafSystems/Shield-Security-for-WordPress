<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\I18n;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n\LoadTextDomain;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class LoadTextDomainTest extends BaseUnitTest {

	public function testClassExists() :void {
		$this->assertTrue( \class_exists( LoadTextDomain::class ) );
	}

	public function testRunRegistersLoadTextdomainFilter() :void {
		Functions\expect( 'add_filter' )
				 ->once()
				 ->withArgs(
					 function ( $tag, $callback, $priority, $acceptedArgs ) {
						 return $tag === 'load_textdomain_mofile'
								&& \is_array( $callback )
								&& isset( $callback[ 1 ] )
								&& $callback[ 1 ] === 'onLoadTextdomainMofile'
								&& $priority === 100
								&& $acceptedArgs === 2;
					 }
				 )
				 ->andReturn( true );

		( new LoadTextDomain() )->run();
		$this->assertTrue( true );
	}
}
