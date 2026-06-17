<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\CloakedPlugins;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins\{
	AdminPluginVisibilitySnapshot,
	CloakedPluginFinding,
	CloakReason,
	PluginEntry,
	PluginType,
	PluginVisibilityComparator
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class PluginVisibilityComparatorTest extends BaseUnitTest {

	public function testReportsStandardPluginMissingFromWordpressDiscovery() :void {
		$finding = $this->compareOne(
			new PluginEntry( PluginType::Standard, 'cloaked/cloaked.php', 'Cloaked', '1.0', '/plugins/cloaked/cloaked.php' ),
			$this->snapshot( [], [], [], true, [ 'all' => [] ] )
		);

		$this->assertSame( [ CloakReason::WpDiscoveryCacheGap ], $finding->cloakReasons );
		$this->assertSame( 'inactive', $finding->status() );
	}

	public function testReportsStandardPluginRemovedByAllPluginsFilter() :void {
		$finding = $this->compareOne(
			new PluginEntry( PluginType::Standard, 'cloaked/cloaked.php', 'Cloaked', '1.0', '/plugins/cloaked/cloaked.php' ),
			$this->snapshot(
				[ 'cloaked/cloaked.php' => [ 'Name' => 'Cloaked' ] ],
				[],
				[],
				true,
				[ 'all' => [] ]
			)
		);

		$this->assertSame( [ CloakReason::AllPlugins ], $finding->cloakReasons );
	}

	public function testReportsStandardPluginRemovedFromFinalPluginsList() :void {
		$finding = $this->compareOne(
			new PluginEntry( PluginType::Standard, 'cloaked/cloaked.php', 'Cloaked', '1.0', '/plugins/cloaked/cloaked.php' ),
			$this->snapshot(
				[ 'cloaked/cloaked.php' => [ 'Name' => 'Cloaked' ] ],
				[ 'cloaked/cloaked.php' => [ 'Name' => 'Cloaked' ] ],
				[],
				true,
				[ 'all' => [], 'active' => [], 'inactive' => [] ]
			)
		);

		$this->assertSame( [ CloakReason::PluginsList ], $finding->cloakReasons );
	}

	public function testReportsMustUsePluginsCloakedByAdvancedPluginsFilter() :void {
		$finding = $this->compareOne(
			new PluginEntry( PluginType::MustUse, 'cloaked-mu.php', 'Cloaked MU', '', '/mu/cloaked-mu.php' ),
			$this->snapshot(
				[],
				[],
				[ 'cloaked-mu.php' => [ 'Name' => 'Cloaked MU' ] ],
				false,
				[ 'mustuse' => [] ]
			)
		);

		$this->assertSame( [ CloakReason::ShowAdvancedPlugins ], $finding->cloakReasons );
		$this->assertSame( 'must-use', $finding->status() );
	}

	private function compareOne( PluginEntry $entry, AdminPluginVisibilitySnapshot $snapshot ) :CloakedPluginFinding {
		$findings = ( new PluginVisibilityComparator() )->compare( [ $entry ], $snapshot );
		$this->assertCount( 1, $findings );
		return $findings[ 0 ];
	}

	private function snapshot(
		array $wp = [],
		array $admin = [],
		array $mu = [],
		bool $showMu = true,
		?array $final = null
	) :AdminPluginVisibilitySnapshot {
		return new AdminPluginVisibilitySnapshot(
			$wp,
			$admin,
			$mu,
			$showMu,
			$showMu ? $mu : [],
			$final,
			[],
			[]
		);
	}
}
