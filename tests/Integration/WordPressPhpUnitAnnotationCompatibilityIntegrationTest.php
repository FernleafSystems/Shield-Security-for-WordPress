<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration;

/**
 * @group smoke
 * @expectedDeprecated shield_phpunit_compatibility_class_deprecated
 * @expectedIncorrectUsage shield_phpunit_compatibility_class_incorrect_usage
 */
class WordPressPhpUnitAnnotationCompatibilityIntegrationTest extends ShieldWordPressTestCase {

	/**
	 * @expectedDeprecated shield_phpunit_compatibility_method_deprecated
	 * @expectedIncorrectUsage shield_phpunit_compatibility_method_incorrect_usage
	 */
	public function test_wordpress_custom_annotations_are_read_at_class_and_method_scope() :void {
		\_deprecated_function( 'shield_phpunit_compatibility_class_deprecated', '1.0.0' );
		\_deprecated_function( 'shield_phpunit_compatibility_method_deprecated', '1.0.0' );
		\_doing_it_wrong( 'shield_phpunit_compatibility_class_incorrect_usage', 'Compatibility probe.', '1.0.0' );
		\_doing_it_wrong( 'shield_phpunit_compatibility_method_incorrect_usage', 'Compatibility probe.', '1.0.0' );

		$this->assertTrue( true );
	}
}
