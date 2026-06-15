<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\HiddenPlugins;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\HiddenPlugins\{
	AdminPluginVisibilitySnapshot,
	HiddenPluginFinding,
	HiddenReason,
	PluginEntry,
	PluginType,
	PluginVisibilityComparator
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class PluginVisibilityComparatorTest extends BaseUnitTest {

	public function testReportsStandardPluginMissingFromWordpressDiscovery() :void {
		$finding = $this->compareOne(
			new PluginEntry( PluginType::Standard, 'hidden/hidden.php', 'Hidden', '1.0', '/plugins/hidden/hidden.php' ),
			$this->snapshot( [], [], [], true, [ 'all' => [] ] )
		);

		$this->assertSame( [ HiddenReason::WpDiscoveryCacheGap ], $finding->hiddenReasons );
		$this->assertSame( 'inactive', $finding->status() );
	}

	public function testReportsStandardPluginRemovedByAllPluginsFilter() :void {
		$finding = $this->compareOne(
			new PluginEntry( PluginType::Standard, 'hidden/hidden.php', 'Hidden', '1.0', '/plugins/hidden/hidden.php' ),
			$this->snapshot(
				[ 'hidden/hidden.php' => [ 'Name' => 'Hidden' ] ],
				[],
				[],
				true,
				[ 'all' => [] ]
			)
		);

		$this->assertSame( [ HiddenReason::AllPlugins ], $finding->hiddenReasons );
	}

	public function testReportsStandardPluginRemovedFromFinalPluginsList() :void {
		$finding = $this->compareOne(
			new PluginEntry( PluginType::Standard, 'hidden/hidden.php', 'Hidden', '1.0', '/plugins/hidden/hidden.php' ),
			$this->snapshot(
				[ 'hidden/hidden.php' => [ 'Name' => 'Hidden' ] ],
				[ 'hidden/hidden.php' => [ 'Name' => 'Hidden' ] ],
				[],
				true,
				[ 'all' => [], 'active' => [], 'inactive' => [] ]
			)
		);

		$this->assertSame( [ HiddenReason::PluginsList ], $finding->hiddenReasons );
	}

	public function testReportsMustUsePluginsHiddenByAdvancedPluginsFilter() :void {
		$finding = $this->compareOne(
			new PluginEntry( PluginType::MustUse, 'hidden-mu.php', 'Hidden MU', '', '/mu/hidden-mu.php' ),
			$this->snapshot(
				[],
				[],
				[ 'hidden-mu.php' => [ 'Name' => 'Hidden MU' ] ],
				false,
				[ 'mustuse' => [] ]
			)
		);

		$this->assertSame( [ HiddenReason::ShowAdvancedPlugins ], $finding->hiddenReasons );
		$this->assertSame( 'must-use', $finding->status() );
	}

	private function compareOne( PluginEntry $entry, AdminPluginVisibilitySnapshot $snapshot ) :HiddenPluginFinding {
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
