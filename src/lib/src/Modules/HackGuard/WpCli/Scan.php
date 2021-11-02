<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	ModCon,
	Options
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use WP_CLI;

class Scan extends Base\WpCli\BaseWpCliCmd {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$params = [];
		foreach ( $opts->getScanSlugs() as $scanSlug ) {
			$sCon = $mod->getScanCon( $scanSlug );
			$params[] = [
				'type'        => 'flag',
				'name'        => $scanSlug,
				'optional'    => true,
				'description' => sprintf( '%s: %s', __( 'Run Scan' ), $sCon->getScanName() ),
			];
		}

		WP_CLI::add_command(
			$this->buildCmd( [ 'scan' ] ),
			[ $this, 'cmdScan' ], $this->mergeCommonCmdArgs( [
			'shortdesc' => 'Run All Shield Scans',
			'synopsis'  => array_merge( [
				[
					'type'        => 'flag',
					'name'        => 'all',
					'optional'    => true,
					'description' => 'Run all available scans.',
				]
			], $params )
		] ) );
	}

	/**
	 * @throws WP_CLI\ExitException
	 */
	public function cmdScan( array $null, array $args ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();
		$availableScans = $opts->getScanSlugs();

		$scans = ( $args[ 'all' ] ?? false ) ? $availableScans : array_keys( $args );

		if ( empty( $scans ) ) {
			WP_CLI::error( sprintf( 'Please specify scans to run. Use `--all` or specify any of: `--%s`',
				implode( '`, `--', $availableScans ) ) );
		}

		$mod->getScansCon()->startNewScans( $scans );
	}
}