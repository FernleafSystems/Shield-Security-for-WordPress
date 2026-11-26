<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;

class WpThemesUpdates extends Base {

	public const SLUG = 'wp_themes_updates';
	public const WEIGHT = 3;

	public function channel() :string {
		return self::CHANNEL_ACTION;
	}

	public function hrefFull() :string {
		return Services::WpGeneral()->getAdminUrl_Themes( true );
	}

	protected function testIfProtected() :bool {
		return $this->countUpdates() === 0;
	}

	public function title() :string {
		return __( 'Themes With Updates', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'All available theme updates have been applied.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		$count = $this->countUpdates();
		return sprintf(
			/* translators: %s: number of theme updates */
			_n( 'There is %s theme update waiting to be applied.', 'There are %s theme updates waiting to be applied.', $count, 'wp-simple-firewall' ),
			$count
		);
	}

	public function guidance() :array {
		return [
			'checks'   => __( 'Shield asks WordPress which of your installed themes have a newer version available.', 'wp-simple-firewall' ),
			'why'      => [
				__( "Themes contain code like plugins do, and security holes are found in them too. Updates are how those holes are closed, including in themes that are installed but not in use.", 'wp-simple-firewall' ),
			],
			'steps'    => [
				__( "Take a backup of your site's files and database.", 'wp-simple-firewall' ),
				__( 'Go to Dashboard -> Updates and update each theme listed there.', 'wp-simple-firewall' ),
				__( 'Check that your site still looks and works as it should.', 'wp-simple-firewall' ),
			],
			'who'      => __( 'Any administrator can do this.', 'wp-simple-firewall' ),
			'practice' => [
				__( "Make your own design changes in a child theme. An update replaces a theme's files, along with any edits made to them.", 'wp-simple-firewall' ),
				__( "Delete the themes you don't use, so there are fewer to keep updated.", 'wp-simple-firewall' ),
			],
		];
	}

	private function countUpdates() :int {
		return \count( Services::WpThemes()->getUpdates() );
	}
}
