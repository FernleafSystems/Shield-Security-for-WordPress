<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\ConfigurePanelBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\BrainMonkeyWordPressWrapperFunctions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\InvokesNonPublicMethods;

/**
 * A setting offers its fuller description only when that says more than the summary already shown with it, and a
 * switch the site's plan doesn't include is shown as off.
 */
class ConfigurePanelBuilderDetailsTest extends BaseUnitTest {

	use BrainMonkeyWordPressWrapperFunctions;
	use InvokesNonPublicMethods;

	protected function setUp() :void {
		parent::setUp();
		$this->registerWordPressWrapperFunctions();
		Functions\when( 'wp_kses' )->returnArg();
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

	public function test_a_setting_without_a_description_offers_nothing() :void {
		$this->assertSame( [], $this->details( [ 'summary' => 'Anything', 'description' => [] ] ) );
		$this->assertSame( [], $this->details( [ 'summary' => 'Anything', 'description' => [ '', '  ' ] ] ) );
	}

	public function test_a_switch_outside_the_plan_is_drawn_off_whatever_its_default() :void {
		$switchedOn = [ 'type' => 'checkbox', 'value' => 'Y', 'summary' => '', 'description' => [] ];

		$items = $this->invokeNonPublicMethod( new ConfigurePanelBuilder(), 'buildItems', [
			[
				[ 'key' => 'in_plan', 'control' => 'toggle' ],
				[ 'key' => 'outside_plan', 'control' => 'toggle' ],
			],
			[
				'in_plan'      => $switchedOn + [ 'key' => 'in_plan', 'unavailable' => false ],
				'outside_plan' => $switchedOn + [ 'key' => 'outside_plan', 'unavailable' => true ],
			],
		] );

		$this->assertTrue( $items[ 0 ][ 'is_on' ] );
		$this->assertFalse( $items[ 1 ][ 'is_on' ] );
	}

	private function details( array $option ) :array {
		return $this->invokeNonPublicMethod( new ConfigurePanelBuilder(), 'details', [ $option ] );
	}
}
