<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Profiles;

use FernleafSystems\Wordpress\Plugin\Shield\Profiles\CompareLevelWithCurrent;
use FernleafSystems\Wordpress\Plugin\Shield\Profiles\Levels;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * The "what would change" list shown beside each protection level must agree with what applying that level does.
 */
class CompareLevelWithCurrentIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();
		$optionKeys = [];
		foreach ( $this->requireController()->comps->security_profiles->getStructure() as $section ) {
			$optionKeys = \array_merge( $optionKeys, \array_column( $section[ 'opts' ], 'opt_key' ) );
		}
		$this->optionsSnapshot = $this->snapshotSelectedOptions( \array_unique( $optionKeys ) );
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		parent::tear_down();
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'providerLevels' )]
	public function test_once_a_level_is_applied_it_has_nothing_left_to_change( string $level ) :void {
		$this->requireController()->comps->security_profiles->applyLevel( $level );

		$comparison = ( new CompareLevelWithCurrent() )->build( $level );

		$this->assertSame( 0, $comparison[ 'stricter' ] + $comparison[ 'looser' ], $level );
		foreach ( $comparison[ 'groups' ] as $group ) {
			$this->assertFalse( $group[ 'has_changes' ], $group[ 'title' ] );
		}
	}

	public function test_a_level_above_the_site_is_stricter_and_a_level_below_it_is_looser() :void {
		$profiles = $this->requireController()->comps->security_profiles;

		$profiles->applyLevel( Levels::LIGHT );
		$strong = ( new CompareLevelWithCurrent() )->build( Levels::STRONG );
		$this->assertGreaterThan( 0, $strong[ 'stricter' ] );
		$this->assertSame( 0, $strong[ 'looser' ] );

		$profiles->applyLevel( Levels::STRONG );
		$light = ( new CompareLevelWithCurrent() )->build( Levels::LIGHT );
		$this->assertGreaterThan( 0, $light[ 'looser' ] );
		$this->assertSame( 0, $light[ 'stricter' ] );
		// Fewer offences allowed is the stricter setting, so allowing more again reads as looser.
		$this->assertSame( 'looser', $this->rowsByChange( $light, 'transgression_limit' ) );
	}

	public function test_a_site_that_blocks_outright_with_crowdsec_is_loosened_by_a_level_that_lets_visitors_unblock() :void {
		$con = $this->requireController();
		$con->comps->security_profiles->applyLevel( Levels::MEDIUM );
		$con->opts->optSet( 'cs_block', 'block' );

		$medium = ( new CompareLevelWithCurrent() )->build( Levels::MEDIUM );

		$this->assertSame( 'looser', $this->rowsByChange( $medium, 'cs_block' ) );
		$this->assertSame( 1, $medium[ 'looser' ] );
		$this->assertSame( 0, $medium[ 'stricter' ] );
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'providerLevels' )]
	public function test_turning_automatic_blocking_on_is_stricter_than_having_it_off( string $level ) :void {
		$this->requireController()->opts->optSet( 'transgression_limit', 0 );

		$this->assertSame( 'stricter', $this->rowsByChange( ( new CompareLevelWithCurrent() )->build( $level ), 'transgression_limit' ) );
	}

	/**
	 * The comparison is built on every Shield admin page while the setup guide shows, so a stored value that
	 * was never one of the setting's choices must not stop the page.
	 */
	public function test_a_stored_crowdsec_value_that_is_not_a_choice_still_gives_a_comparison() :void {
		$opts = $this->requireController()->opts;
		$opts->optGet( 'cs_block' );
		$values = new \ReflectionProperty( $opts, 'values' );
		$values->setAccessible( true );

		foreach ( [ new \stdClass(), [ 'block' ], 'a_retired_choice' ] as $stored ) {
			$values->setValue( $opts, \array_merge( $values->getValue( $opts ), [ 'cs_block' => $stored ] ) );

			$comparison = ( new CompareLevelWithCurrent() )->build( Levels::MEDIUM );

			$this->assertContains( $this->rowsByChange( $comparison, 'cs_block' ), [ 'stricter', 'same' ] );
		}
	}

	public function test_every_setting_is_counted_once() :void {
		$comparison = ( new CompareLevelWithCurrent() )->build( Levels::MEDIUM );

		$rows = 0;
		foreach ( $comparison[ 'groups' ] as $group ) {
			$rows += \count( $group[ 'rows' ] );
		}
		$this->assertSame( $rows, $comparison[ 'stricter' ] + $comparison[ 'looser' ] + $comparison[ 'unchanged' ] );
	}

	public static function providerLevels() :array {
		return [
			Levels::LIGHT  => [ Levels::LIGHT ],
			Levels::MEDIUM => [ Levels::MEDIUM ],
			Levels::STRONG => [ Levels::STRONG ],
		];
	}

	/**
	 * How the comparison describes the row for one profile item.
	 */
	private function rowsByChange( array $comparison, string $itemKey ) :string {
		$position = 0;
		foreach ( $this->requireController()->comps->security_profiles->getStructure() as $section ) {
			foreach ( $section[ 'opts' ] as $opt ) {
				if ( $opt[ 'item_key' ] === $itemKey ) {
					return \array_merge( ...\array_column( $comparison[ 'groups' ], 'rows' ) )[ $position ][ 'change' ];
				}
				$position++;
			}
		}
		$this->fail( 'No such profile item: '.$itemKey );
	}
}
