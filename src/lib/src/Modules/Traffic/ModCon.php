<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\DbTableExport;
use FernleafSystems\Wordpress\Services\Services;

class ModCon extends BaseShield\ModCon {

	public function getDbH_ReqLogs() :DB\ReqLogs\Ops\Handler {
		$this->getCon()->getModule_Plugin()->getDbH_IPs();
		return $this->getDbHandler()->loadDbH( 'req_logs' );
	}

	public function getDbH_ReqMeta() :DB\ReqMeta\Ops\Handler {
		$this->getDbH_ReqLogs();
		return $this->getDbHandler()->loadDbH( 'req_meta' );
	}

	public function getDbHandler_Traffic() :Databases\Traffic\Handler {
		return $this->getDbH( 'traffic' );
	}

	protected function handleFileDownload( string $downloadID ) {
		switch ( $downloadID ) {
			case 'db_traffic':
				( new DbTableExport() )
					->setDbHandler( $this->getDbHandler_Traffic() )
					->toCSV();
				break;
		}
	}

	protected function preProcessOptions() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$aExcls = $opts->getCustomExclusions();
		foreach ( $aExcls as &$sExcl ) {
			$sExcl = trim( esc_js( $sExcl ) );
		}
		$opts->setOpt( 'custom_exclusions', array_filter( $aExcls ) );
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function isReadyToExecute() :bool {
		$IP = Services::IP();
		return $IP->isValidIp_PublicRange( $IP->getRequestIp() )
			   && ( $this->getDbHandler_Traffic() instanceof Databases\Traffic\Handler )
			   && $this->getDbHandler_Traffic()->isReady()
			   && parent::isReadyToExecute();
	}
}