<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use PHPUnit\Framework\TestCase;

class PopularPluginsCompatibilityManifestTest extends TestCase {

	use PluginPathsTrait;

	public function testPinnedManifestContainsExactlyTwentyUniqueRepresentativePlugins() :void {
		$manifest = $this->decodePluginJsonFile(
			'tests/fixtures/popular-plugin-compat/plugin-slugs.json',
			'Popular plugin compatibility manifest'
		);
		$slugs = $manifest[ 'slugs' ] ?? null;
		$this->assertIsArray( $slugs );

		$this->assertCount( 20, $slugs );
		$this->assertCount( 20, \array_unique( $slugs ) );

		foreach ( [
			'wordfence',
			'woocommerce',
			'easy-digital-downloads',
			'wordpress-seo',
			'updraftplus',
			'duplicator',
			'autoptimize',
			'wp-super-cache',
			'really-simple-ssl',
			'mainwp',
			'mainwp-child',
			'worker',
			'iwp-client',
		] as $requiredSlug ) {
			$this->assertContains( $requiredSlug, $slugs );
		}
	}

	public function testArchivedPopularManifestKeepsOriginalOneHundredPluginSet() :void {
		$manifest = $this->decodePluginJsonFile(
			'tests/fixtures/popular-plugin-compat/plugin-slugs-popular-100.json',
			'Archived popular plugin compatibility manifest'
		);
		$slugs = $manifest[ 'slugs' ] ?? null;
		$this->assertIsArray( $slugs );

		$this->assertCount( 100, $slugs );
		$this->assertCount( 100, \array_unique( $slugs ) );
	}
}
