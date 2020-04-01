<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;

class ReportingController extends Base\OneTimeExecute {

	protected function run() {
		add_action( $this->getCon()->prefix( 'hourly_cron' ), [ $this, 'runHourlyCron' ] );
	}

	public function runHourlyCron() {
		$this->buildAndSendEmail();
	}

	private function buildAndSendEmail() {
		$sBody = '';
		try {
			$sBody .= ( new Reports\BuildAlerts() )
				->setMod( $this->getMod() )
				->build();
		}
		catch ( \Exception $oE ) {
		}

		try {
			$sBody .= ( new Reports\BuildInfo() )
				->setMod( $this->getMod() )
				->build();
		}
		catch ( \Exception $oE ) {
		}

		$this->sendEmail( $sBody );
	}

	/**
	 * @param string $sBody
	 */
	private function sendEmail( $sBody ) {
		if ( !empty( $sBody ) ) {
			$this->getMod()
				 ->getEmailProcessor()
				 ->send(
					 $this->getMod()->getPluginDefaultRecipientAddress(),
					 'Shield Alert',
					 $sBody
				 );
		}
	}
}