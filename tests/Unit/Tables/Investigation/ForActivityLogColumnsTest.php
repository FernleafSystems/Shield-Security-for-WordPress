<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables\Investigation;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForActivityLog as FullActivityLogTable;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Investigation\ForActivityLog as InvestigationActivityLogTable;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ForActivityLogColumnsTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
	}

	/**
	 * @dataProvider supportedActivitySubjectsProvider
	 */
	public function testInvestigationActivityColumnsMatchFullActivityColumns( string $subjectType, string $subjectId ) :void {
		$this->assertSame(
			( new FullActivityLogTable() )->exportColumnsToDisplay(),
			( new InvestigationActivityLogTable() )
				->setSubject( $subjectType, $subjectId )
				->exportColumnsToDisplay()
		);
	}

	public function testIpActivityInvestigationKeepsVisibleIdentityColumn() :void {
		$raw = ( new InvestigationActivityLogTable() )
			->setSubject( 'ip', '203.0.113.7' )
			->buildRaw();

		$identityColumn = $this->findColumnByData( $raw[ 'columns' ], 'identity' );

		$this->assertNotNull( $identityColumn );
		$this->assertTrue( (bool)( $identityColumn[ 'visible' ] ?? false ) );
	}

	public function supportedActivitySubjectsProvider() :array {
		return [
			'user subject'   => [ 'user', '7' ],
			'ip subject'     => [ 'ip', '203.0.113.7' ],
			'plugin subject' => [ 'plugin', 'akismet/akismet.php' ],
			'theme subject'  => [ 'theme', 'twentytwentyfive' ],
			'core subject'   => [ 'core', 'core' ],
		];
	}

	private function findColumnByData( array $columns, string $data ) :?array {
		foreach ( $columns as $column ) {
			if ( ( $column[ 'data' ] ?? null ) === $data ) {
				return $column;
			}
		}
		return null;
	}
}
