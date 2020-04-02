<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting;
use FernleafSystems\Wordpress\Services\Services;

class CreateReportVO {

	use ModConsumer;

	/**
	 * @var ReportVO
	 */
	private $rep;

	/**
	 * CreateReportVO constructor.
	 * @param string $sReportType
	 */
	public function __construct( $sReportType ) {
		$this->rep = new ReportVO();
		$this->rep->type = $sReportType;
	}

	/**
	 * @return ReportVO
	 * @throws \Exception
	 */
	public function create() {
		$this->setReportInterval()
			 ->setPreviousReport()
			 ->setIntervalBoundaries()
			 ->setReportId();
		return $this->rep;
	}

	/**
	 * @return $this
	 * @throws \Exception
	 */
	private function setReportInterval() {
		/** @var Reporting\Options $oOpts */
		$oOpts = $this->getOptions();

		switch ( $this->rep->type ) {
			case Reports\Handler::TYPE_ALERT:
				$this->rep->interval = $oOpts->getFrequencyAlerts();
				break;
			case Reports\Handler::TYPE_INFO:
				$this->rep->interval = $oOpts->getFrequencyInfo();
				break;
			default:
				throw new \Exception( 'Not a supported report type: '.$this->rep->type );
				break;
		}
		return $this;
	}

	/**
	 * @return $this
	 */
	private function setPreviousReport() {
		/** @var \ICWP_WPSF_FeatureHandler_Reporting $oMod */
		$oMod = $this->getMod();
		/** @var Reports\Select $oSel */
		$oSel = $oMod->getDbHandler_Reports()->getQuerySelector();
		/** @var Reports\EntryVO $oLast */
		$this->rep->previous = $oSel->filterByType( $this->rep->type )
									->filterByInterval( $this->rep->interval )
									->setOrderBy( 'sent_at', 'DESC' )
									->first();
		return $this;
	}

	/**
	 * @return $this
	 * @throws \Exception
	 */
	private function setIntervalBoundaries() {

		$bHasPrevious = !empty( $this->rep->previous );
		if ( $bHasPrevious ) {
			$nAddition = 1;
			$oC = ( new Carbon() )->setTimestamp( $this->rep->previous->interval_end_at );
		}
		else {
			$nAddition = -1;
			$oC = Services::Request()->carbon();
		}

		switch ( $this->rep->interval ) {
			case 'realtime':
				break;
			case 'hourly':
				$oC->addHours( $nAddition );
				$this->rep->interval_start_at = $oC->startOfHour();
				$this->rep->interval_end_at = $oC->endOfHour();
				break;
			case 'daily':
				$oC->addDays( $nAddition );
				$this->rep->interval_start_at = $oC->startOfDay();
				$this->rep->interval_end_at = $oC->endOfDay();
				break;
			case 'weekly':
				$oC->addWeeks( $nAddition );
				$this->rep->interval_start_at = $oC->startOfWeek();
				$this->rep->interval_end_at = $oC->endOfWeek();
				break;
			case 'monthly':
				$oC->addMonths( $nAddition );
				$this->rep->interval_start_at = $oC->startOfMonth();
				$this->rep->interval_end_at = $oC->endOfMonth();
				break;
			case 'yearly':
				$oC->addYears( $nAddition );
				$this->rep->interval_start_at = $oC->startOfYear();
				$this->rep->interval_end_at = $oC->endOfYear();
				break;
			default:
				throw new \Exception( 'Not a supported frequency' );
				break;
		}
		return $this;
	}

	/**
	 * @return $this
	 * @throws \Exception
	 */
	private function setReportId() {
		/** @var \ICWP_WPSF_FeatureHandler_Reporting $oMod */
		$oMod = $this->getMod();
		/** @var Reports\Select $oSel */
		$oSel = $oMod->getDbHandler_Reports()->getQuerySelector();
		$nPrevID = $oSel->getLastReportId();
		$this->rep->rid = is_numeric( $nPrevID ) ? $nPrevID + 1 : 1;
		return $this;
	}
}
