<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use WP_CLI;

class ScanRun extends Base\WpCli\BaseWpCliCmd {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		$scansCon = $this->mod()->getScansCon();

		$params = [];
		foreach ( $scansCon->getScanSlugs() as $slug ) {
			$params[] = [
				'type'        => 'flag',
				'name'        => $slug,
				'optional'    => true,
				'description' => sprintf( '%s: %s', __( 'Run Scan' ), $scansCon->getScanCon( $slug )->getScanName() ),
			];
		}

		WP_CLI::add_command(
			$this->buildCmd( [ 'scan_run' ] ),
			[ $this, 'cmdScanRun' ], $this->mergeCommonCmdArgs( [
			'shortdesc' => 'Run All Shield Scans',
			'synopsis'  => \array_merge( [
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
	public function cmdScanRun( array $null, array $args ) {
		$scansCon = self::con()->getModule_HackGuard()->getScansCon();

		$scans = ( $args[ 'all' ] ?? false ) ? $scansCon->getScanSlugs() : \array_keys( $args );
		if ( empty( $scans ) ) {
			WP_CLI::error( sprintf( 'Please specify scans to run. Use `--all` or specify any of: `--%s`',
				\implode( '`, `--', $scansCon->getScanSlugs() ) ) );
		}

		$scansCon->startNewScans( $scans );
	}
}