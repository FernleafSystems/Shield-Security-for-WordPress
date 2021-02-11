<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Upgrade extends Base\Upgrade {

	protected function upgrade_1021() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$schema = $mod->getDbHandler_FileLocker()->getTableSchema();
		Services::WpDb()->doSql(
			sprintf( "ALTER TABLE `%s` MODIFY `%s` %s;",
				$schema->table, 'content', $schema->enumerateColumns()[ 'content' ] )
		);
	}

	protected function upgrade_900() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		if ( $opts->getOpt( 'ptg_enable' ) === 'enabled' ) {
			$opts->setOpt( 'ptg_enable', 'Y' );
		}
		elseif ( $opts->getOpt( 'ptg_enable' ) === 'disabled' ) {
			$opts->setOpt( 'ptg_enable', 'N' );
		}

		$aRepairAreas = $opts->getRepairAreas();
		$aMap = [
			'attempt_auto_file_repair' => 'wp',
			'mal_autorepair_plugins'   => 'plugin',
		];
		foreach ( $aMap as $sOld => $sNew ) {
			if ( $opts->getOpt( $sOld ) !== false ) {
				$bWasEnabled = $opts->isOpt( $sOld, 'Y' );
				$nIsEnabled = array_search( $sNew, $aRepairAreas );
				if ( $bWasEnabled && ( $nIsEnabled === false ) ) {
					$aRepairAreas[] = $sNew;
				}
				elseif ( !$bWasEnabled && ( $nIsEnabled !== false ) ) {
					unset( $aRepairAreas[ $nIsEnabled ] );
				}
			}
		}
		$opts->setOpt( 'file_repair_areas', $aRepairAreas );

		{ // migrate old scan options
			if ( $opts->getOpt( 'enable_unrecognised_file_cleaner_scan' ) == 'enabled_delete_report' ) {
				$opts->setOpt( 'enable_unrecognised_file_cleaner_scan', 'enabled_delete_only' );
			}
			$sApcOpt = $opts->getOpt( 'enabled_scan_apc' );
			if ( strlen( $sApcOpt ) > 1 ) {
				$opts->setOpt( 'enabled_scan_apc', $sApcOpt == 'disabled' ? 'N' : 'Y' );
			}
			$sWpvOpt = $opts->getOpt( 'enable_wpvuln_scan' );
			if ( strlen( $sWpvOpt ) > 1 ) {
				$opts->setOpt( 'enable_wpvuln_scan', $sWpvOpt == 'disabled' ? 'N' : 'Y' );
			}
		}
	}
}