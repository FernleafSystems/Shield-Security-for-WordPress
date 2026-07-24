<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

class BuildScanAction extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BuildScanAction {

	protected function buildScanItems() {
		$this->getScanActionVO()->items = ( new BuildScanItems() )
			->setScanActionVO( $this->getScanActionVO() )
			->run();
	}

	protected function setCustomFields() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		$action->file_exts = $this->getFileExts();
		$action->coverage_families = $this->buildCoverageFamilies( $action );
	}

	/**
	 * @return list<string>
	 */
	private function buildCoverageFamilies( ScanActionVO $action ) :array {
		$scanCon = self::con()->comps->scans->AFS();
		$enabled = [
			ScanActionVO::COVERAGE_FAMILY_CORE_INTEGRITY      => $scanCon->isScanEnabledWpCore(),
			ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY    => $scanCon->isScanEnabledPlugins(),
			ScanActionVO::COVERAGE_FAMILY_THEME_INTEGRITY     => $scanCon->isScanEnabledThemes(),
			ScanActionVO::COVERAGE_FAMILY_WPROOT_UNIDENTIFIED => $scanCon->isScanEnabledWpRoot(),
			ScanActionVO::COVERAGE_FAMILY_WPCONTENT_UNIDENTIFIED => $scanCon->isScanEnabledWpContent(),
			ScanActionVO::COVERAGE_FAMILY_MALWARE             => $scanCon->isEnabledMalwareScanPHP(),
		];

		switch ( $action->scope_type ) {
			case 'plugin':
				$scopeFamilies = [
					ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY,
					ScanActionVO::COVERAGE_FAMILY_MALWARE,
				];
				break;
			case 'theme':
				$scopeFamilies = [
					ScanActionVO::COVERAGE_FAMILY_THEME_INTEGRITY,
					ScanActionVO::COVERAGE_FAMILY_MALWARE,
				];
				break;
			case 'core':
				$scopeFamilies = [
					ScanActionVO::COVERAGE_FAMILY_CORE_INTEGRITY,
				];
				break;
			case 'full':
				$scopeFamilies = ScanActionVO::COVERAGE_FAMILIES;
				break;
			default:
				$scopeFamilies = [];
				break;
		}

		return \array_values( \array_filter(
			$scopeFamilies,
			static fn( string $family ) :bool => $enabled[ $family ]
		) );
	}

	protected function getFileExts() :array {
		$default = $this->normaliseFileExts(
			self::con()->cfg->configuration->def( 'file_scan_extensions' )
		);
		$filtered = apply_filters( 'shield/scan_ptg_file_exts', $default );
		if ( !\is_array( $filtered ) ) {
			return $default;
		}

		$normalised = $this->normaliseFileExts( $filtered );
		return !empty( $filtered ) && empty( $normalised ) ? $default : $normalised;
	}

	/**
	 * @param array<array-key,mixed> $extensions
	 * @return list<string>
	 */
	private function normaliseFileExts( array $extensions ) :array {
		$normalised = [];
		foreach ( $extensions as $extension ) {
			if ( !\is_string( $extension ) ) {
				continue;
			}
			$extension = \strtolower( \trim( $extension ) );
			if ( $extension !== '' && !\in_array( $extension, $normalised, true ) ) {
				$normalised[] = $extension;
			}
		}
		return $normalised;
	}
}
