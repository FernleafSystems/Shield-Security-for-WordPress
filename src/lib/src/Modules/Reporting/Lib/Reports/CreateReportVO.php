<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;

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
									->filterByFrequency( $this->rep->interval )
									->setOrderBy( 'sent_at', 'DESC' )
									->first();
		return $this;
	}

	/**
	 * Here we test whether the report time boundary overlaps with the boundaries of the previous report.
	 * If it does overlap, we're creating a duplicate report.
	 *
	 * @return $this
	 * @throws \Exception
	 */
	private function setIntervalBoundaries() {

		$oC = Services::Request()->carbon( true );
		$nAddition = -1; // the previous hour, day, week, month

		switch ( $this->rep->interval ) {
//			case 'realtime':
//				break;
			case 'hourly':
				$oC->addHours( $nAddition );
				$nStart = $oC->startOfHour()->timestamp;
				$nEnd = $oC->endOfHour()->timestamp;
				break;
			case 'daily':
				$oC->addDays( $nAddition );
				$nStart = $oC->startOfDay()->timestamp;
				$nEnd = $oC->endOfDay()->timestamp;
				break;
			case 'weekly':
				$oC->addWeeks( $nAddition );
				$nStart = $oC->startOfWeek()->timestamp;
				$nEnd = $oC->endOfWeek()->timestamp;
				break;
			case 'monthly':
				$oC->addMonths( $nAddition );
				$nStart = $oC->startOfMonth()->timestamp;
				$nEnd = $oC->endOfMonth()->timestamp;
				break;
			case 'yearly':
				$oC->addYears( $nAddition );
				$nStart = $oC->startOfYear()->timestamp;
				$nEnd = $oC->endOfYear()->timestamp;
				break;
			default:
				throw new \Exception( 'Not a supported frequency' );
				break;
		}

		if ( $this->rep->previous instanceof Reports\EntryVO
			 && $nEnd <= $this->rep->previous->interval_end_at ) {
			throw new \Exception( 'Attempting to create a duplicate report based on interval.' );
		}

		$this->rep->interval_start_at = $nStart;
		$this->rep->interval_end_at = $nEnd;

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

	/**
	 * TODO
	 * @return bool
	 */
	private function isOnDemandReport() {
		return !Services::WpGeneral()->isCron();
	}
}
