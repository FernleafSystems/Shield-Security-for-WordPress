<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

class ScansRun extends ScansBase {

	protected function cmdParts() :array {
		return [ 'run' ];
	}

	protected function cmdShortDescription() :string {
		return 'Run All Shield Scans.';
	}

	protected function cmdSynopsis() :array {
		$params = [
			[
				'type'        => 'flag',
				'name'        => 'all',
				'optional'    => true,
				'description' => 'Run all available scans.',
			]
		];
		foreach ( self::con()->comps->scans->getAllScanCons() as $scanCon ) {
			$params[] = [
				'type'        => 'flag',
				'name'        => $scanCon->getSlug(),
				'optional'    => true,
				'description' => sprintf( '%s: %s', __( 'Run Scan' ), $scanCon->getScanName() ),
			];
		}
		return $params;
	}

	public function runCmd() :void {
		$scansCon = self::con()->comps->scans;

		$scans = ( $this->execCmdArgs[ 'all' ] ?? false ) ? $scansCon->getScanSlugs() : \array_keys( $this->execCmdArgs );
		if ( empty( $scans ) ) {
			\WP_CLI::error( sprintf( 'Please specify scans to run. Use `--all` or specify any of: `--%s`',
				\implode( '`, `--', $scansCon->getScanSlugs() ) ) );
		}

		$scansCon->startNewScans( $scans );
	}
}