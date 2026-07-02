<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\PluginThemesBase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\General;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;

class PluginThemesBaseCachedFlagsTest extends BaseUnitTest {

	private const PLUGIN_FILE = 'example/example.php';

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->resetFlagsCache();
	}

	protected function tearDown() :void {
		$this->resetFlagsCache();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	/**
	 * @dataProvider provideMalformedCachedFlags
	 */
	public function testMalformedCachedFlagsAreRebuilt( $cachedFlags ) :void {
		$general = new PluginThemesBaseCachedFlagsGeneralStub( [
			self::PLUGIN_FILE => $cachedFlags,
		] );
		ServicesState::installItems( [
			'service_wpgeneral' => $general,
		] );

		$flags = ( new PluginThemesBaseCachedFlagsTestDouble() )->exposeCachedFlags(
			new PluginThemesBaseCachedFlagsPluginVo( self::PLUGIN_FILE, true, true )
		);

		$this->assertSame( [
			'is_wporg' => true,
			'has_tag'  => true,
		], $flags );
		$this->assertIsArray( $general->lastStoredTransientValue );
		$this->assertArrayHasKey( self::PLUGIN_FILE, $general->lastStoredTransientValue );
		$this->assertSame( $flags, $general->lastStoredTransientValue[ self::PLUGIN_FILE ] );
		$this->assertSame( \HOUR_IN_SECONDS, $general->lastStoredTransientLifetime );
	}

	public static function provideMalformedCachedFlags() :array {
		return [
			'scalar cached flags'      => [ 'not-an-array' ],
			'missing is_wporg flag'    => [ [ 'has_tag' => true ] ],
			'missing has_tag flag'     => [ [ 'is_wporg' => true ] ],
			'string is_wporg flag'     => [ [ 'is_wporg' => 'yes', 'has_tag' => true ] ],
			'integer has_tag flag'     => [ [ 'is_wporg' => true, 'has_tag' => 1 ] ],
			'null cached flags'        => [ null ],
		];
	}

	private function resetFlagsCache() :void {
		$property = new \ReflectionProperty( PluginThemesBase::class, 'wpOrgDataCache' );
		$property->setAccessible( true );
		$property->setValue( null, false );
	}
}

class PluginThemesBaseCachedFlagsTestDouble extends PluginThemesBase {

	public function exposeCachedFlags( $item ) :array {
		return $this->getCachedFlags( $item );
	}
}

class PluginThemesBaseCachedFlagsPluginVo extends WpPluginVo {

	public string $file;

	private bool $wpOrg;

	private bool $usesTags;

	public function __construct( string $file, bool $wpOrg, bool $usesTags ) {
		$this->file = $file;
		$this->wpOrg = $wpOrg;
		$this->usesTags = $usesTags;
	}

	public function __get( string $key ) {
		$value = null;
		if ( $key === 'unique_id' ) {
			$value = $this->file;
		}
		elseif ( $key === 'svn_uses_tags' ) {
			$value = $this->usesTags;
		}
		elseif ( $key === 'asset_type' ) {
			$value = 'plugin';
		}
		return $value;
	}

	public function isWpOrg() :bool {
		return $this->wpOrg;
	}
}

class PluginThemesBaseCachedFlagsGeneralStub extends General {

	private $transientValue;

	public $lastStoredTransientValue;

	public int $lastStoredTransientLifetime = 0;

	public function __construct( $transientValue ) {
		$this->transientValue = $transientValue;
	}

	public function canUseTransients() :bool {
		return true;
	}

	public function getTransient( $sKey ) {
		return $this->transientValue;
	}

	public function setTransient( $sKey, $mValue, $nExpire = 0 ) {
		$this->lastStoredTransientValue = $mValue;
		$this->lastStoredTransientLifetime = $nExpire;
		return true;
	}
}
