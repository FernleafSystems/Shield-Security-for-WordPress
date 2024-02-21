<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class VerifyConfig {

	use PluginControllerConsumer;

	public function run() {
		$sectionDuplicateExceptions = [ 'section_non_ui' ];
		$optsDuplicateExceptions = [ 'xfer_excluded' ];

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
			$opts = $mod->opts();
			$sections = \array_keys( $config->sectionsForModule( $mod->cfg->slug ) );
			$duplicates = \array_diff( \array_intersect( $allSections, $sections ), $sectionDuplicateExceptions );
			if ( \count( $duplicates ) > 0 ) {
				var_dump( sprintf( 'Mod %s has duplicate section slugs: %s', $mod->cfg->slug, \implode( ', ', $duplicates ) ) );
			}
			$allSections = \array_unique( \array_merge( $allSections, $sections ) );

			$optKeys = $opts->getOptionsKeys();
			$duplicates = \array_diff( \array_intersect( $allOpts, $optKeys ), $optsDuplicateExceptions );
			if ( \count( $duplicates ) > 0 ) {
				var_dump( sprintf( 'Mod %s has duplicate option slugs: %s', $mod->cfg->slug, \implode( ', ', $duplicates ) ) );
			}
			$allOpts = \array_unique( \array_merge( $allOpts, $optKeys ) );
//			$this->verifyCfg( $mod );
		}

		if ( !empty( $sectionsMissingModule ) ) {
			var_dump( 'sections missing module setting: '.implode( ', ', $sectionsMissingModule ) );
		}
	}

	public function verifyCfg( ModCon $mod ) {
		$opts = $mod->opts();
		foreach ( $opts->getOptionsKeys() as $sKey ) {
			$optType = $opts->getOptionType( $sKey );
			if ( empty( $optType ) ) {
				var_dump( $sKey.': no type' );
				continue;
			}

			$mDefault = $opts->getOptDefault( $sKey );
			if ( \is_null( $mDefault ) ) {
				var_dump( sprintf( '%s: opt has no default.', $sKey ) );
				continue;
			}

			$mVal = $opts->getOpt( $sKey );
			$valType = gettype( $mVal );

			$isBroken = false;
			switch ( $optType ) {

				case 'integer':
					if ( $valType != 'integer' ) {
						$isBroken = true;
					}
					break;

				case 'text':
					if ( $valType != 'string' ) {
						$isBroken = true;
					}
					break;

				default:
					break;
			}

			if ( $isBroken ) {
				var_dump( sprintf( '%s: opt type is %s, value is %s at "%s". Default is: %s',
					$sKey, $optType, $valType, var_export( $mVal, true ), $opts->getOptDefault( $sKey ) ) );
//				$opts->resetOptToDefault( $sKey );
			}
		}
	}
}