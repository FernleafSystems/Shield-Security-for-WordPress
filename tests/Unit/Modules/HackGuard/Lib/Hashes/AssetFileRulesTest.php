<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\Hashes;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\AssetFileRules;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class AssetFileRulesTest extends BaseUnitTest {

	private const BUILD = '{"buildDate":1792016945,"buildMeta":{"buildAuthor":"@cklosowski","fileCount":5623,"fileChecksum":"22e7a10707420c21b3802eb4b8a2ecee","manifestChecksum":"%s"}}';

	public function test_edd_build_manifest_downloads_of_one_build_empty_to_the_same_content() :void {
		// Two downloads of EDD Pro 3.7.2 with the same licence: one by the WP Hashes API, one by a site's updater.
		$api = \sprintf( self::BUILD, '14771e1a-6c34-43nM72YBhyPIs3jtZugiQY4siKSziB2Mf0ZKzTLe3VSJcR7SxbcfA6OdN+jhbpmG8rYZ8TgwIxiLCY0LVGZ3iSITLp7A6l4DrYfJUXD33LBRr+pl4rmLdt6Yj8JFGrwI2PLczANqosWfljEmkkv15ik1OchMARpDGCkB55rC7yMPjMU+kU78aqobvicp5PslJI6WDOcCG2FjqgeeUNHS43jwM2YGesuI56z6+vymanF0o=' );
		$site = \sprintf( self::BUILD, '1900a92e-567d-40O0TYMeyeHsToMThKjy3wdXBsL4oaiek73sSa\/d4zW1yR+1DEI63IG\/1nVmOWzm1STHscjx4CFDzlBbq95aKeHhUvMbKIBFMdoKZen0zo6gflQC4bxE4XSnyU9mkT5K0JGPm31wCHlv+E7tMsXOl4pNCmN34hCsz2ERSATtdD98y0VEfUVOdUpVxHQkhpG9tZisHxBXVXNg2yI8G\/gCFtH52zg9ohvrE1jRBw5gpf+P0v45hZbZfGbh2hdfapULeUHor+8p7CeHU4N\/2Yd+5ZDw==' );
		$rules = new AssetFileRules();

		$this->assertSame( \sprintf( self::BUILD, '' ), $rules->apply( 'vendor/build.json', $api ) );
		$this->assertSame( \sprintf( self::BUILD, '' ), $rules->apply( 'vendor/build.json', $site ) );
		$this->assertNull( $rules->apply( 'build.json', $site ) );
		$this->assertNull( $rules->apply( 'lib/vendor/build.json', $site ) );
	}

	/**
	 * @dataProvider provideUnrecognisedContent
	 */
	public function test_content_without_exactly_one_recognised_value_is_left_as_is( string $content ) :void {
		$this->assertNull( ( new AssetFileRules() )->apply( 'vendor/build.json', $content ) );
	}

	public function provideUnrecognisedContent() :array {
		return [
			'already empty'   => [ \sprintf( self::BUILD, '' ) ],
			'no UUID prefix'  => [ \sprintf( self::BUILD, 'O0TYMeyeHsToMThKjy3wdXBsL4oaiek73sSa' ) ],
			'unescaped slash' => [ \sprintf( self::BUILD, '1900a92e-567d-40O0TY/Mey' ) ],
			'twice'           => [ \sprintf( self::BUILD, '1900a92e-567d-40O0TYMey' ).\sprintf( self::BUILD, '1900a92e-567d-40O0TYMey' ) ],
			'spaced'          => [ '{"manifestChecksum": "1900a92e-567d-40O0TYMey"}' ],
		];
	}
}
