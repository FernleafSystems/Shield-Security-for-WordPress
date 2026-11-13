<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\ConfigurePanelBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\InvokesNonPublicMethods;

/**
 * A setting offers its fuller description only when that says more than the summary already shown with it.
 */
class ConfigurePanelBuilderDetailsTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'wp_strip_all_tags' )->alias( static fn( string $text ) :string => \strip_tags( $text ) );
	}

	public function test_a_description_that_adds_something_is_offered_line_by_line() :void {
		$this->assertSame(
			[ 'Shield stores data in a temporary folder.', 'It chose <code>/tmp</code>.' ],
			$this->details( [
				'summary'     => 'Preferred Temporary Directory',
				'description' => [ ' Shield stores data in a temporary folder. ', '', 'It chose <code>/tmp</code>.' ],
			] )
		);
	}

	public function test_a_description_that_only_repeats_the_summary_is_not_offered() :void {
		$this->assertSame( [], $this->details( [
			'summary'     => 'Global Plugin On/Off Switch',
			'description' => [ '<strong>Global plugin on/off switch.</strong>' ],
		] ) );
	}

	public function test_a_single_line_description_from_the_spec_is_offered() :void {
		$this->assertSame(
			[ 'Uncheck this option to disable all Shield features' ],
			$this->details( [
				'summary'     => 'Global Plugin On/Off Switch',
				'description' => 'Uncheck this option to disable all Shield features',
			] )
		);
	}

	public function test_a_setting_without_a_description_offers_nothing() :void {
		$this->assertSame( [], $this->details( [ 'summary' => 'Anything' ] ) );
		$this->assertSame( [], $this->details( [ 'summary' => 'Anything', 'description' => [ '', '  ' ] ] ) );
	}

	private function details( array $option ) :array {
		return $this->invokeNonPublicMethod( new ConfigurePanelBuilder(), 'details', [ $option ] );
	}
}
