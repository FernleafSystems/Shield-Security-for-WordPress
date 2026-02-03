<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class VerifyConfig {

	use PluginControllerConsumer;

	public function run() {
		$sectionDuplicateExceptions = [];
		$optsDuplicateExceptions = [];

		$allSections = [];
		$allOpts = [];
		$sectionsMissingModule = [];
		$config = self::con()->cfg->configuration;

		foreach ( $config->sections as $sectionKey => $section ) {
			if ( empty( $section[ 'module' ] ) ) {
				$sectionsMissingModule[] = $sectionKey;
			}
		}

		foreach ( self::con()->modules as $mod ) {
			$sections = \array_keys( $config->sectionsForModule( $mod->cfg->slug ) );
			$duplicates = \array_diff( \array_intersect( $allSections, $sections ), $sectionDuplicateExceptions );
			if ( \count( $duplicates ) > 0 ) {
				var_dump( sprintf( 'Mod %s has duplicate section slugs: %s', $mod->cfg->slug, \implode( ', ', $duplicates ) ) );
			}
			$allSections = \array_unique( \array_merge( $allSections, $sections ) );

			$optKeys = \array_keys( $config->optsForModule( $mod->cfg->slug ) );
			$duplicates = \array_diff( \array_intersect( $allOpts, $optKeys ), $optsDuplicateExceptions );
			if ( \count( $duplicates ) > 0 ) {
				var_dump( sprintf( 'Mod %s has duplicate option slugs: %s', $mod->cfg->slug, \implode( ', ', $duplicates ) ) );
			}
			$allOpts = \array_unique( \array_merge( $allOpts, $optKeys ) );
//			$this->verifyCfg( $mod );
		}

		if ( !empty( $sectionsMissingModule ) ) {
			var_dump( 'sections missing module setting: '.\implode( ', ', $sectionsMissingModule ) );
		}
	}
}