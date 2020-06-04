<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseEntryFormatter {

	use Databases\Base\EntryVoConsumer;
	use Scans\Common\ScanActionConsumer;
	use Scan\Controller\ScanControllerConsumer;
	use ModConsumer;

	/**
	 * @return array
	 */
	abstract public function format();

	/**
	 * @return string[]
	 */
	protected function getSupportedActions() {
		return [
			'ignore'
		];
	}

	/**
	 * @return array[]
	 */
	protected function getActionDefinitions() {
		return [
			'ignore'   => [
				'text'    => __( 'Ignore', 'wp-simple-firewall' ),
				'classes' => [ 'ignore' ],
				'data'    => []
			],
			'delete'   => [
				'text'    => __( 'Delete', 'wp-simple-firewall' ),
				'classes' => [ 'delete', 'text-danger' ],
				'data'    => []
			],
			'repair'   => [
				'text'    => __( 'Repair', 'wp-simple-firewall' ),
				'classes' => [ 'repair', 'text-success' ],
				'data'    => []
			],
			'download' => [
				'text'    => __( 'Download', 'wp-simple-firewall' ),
				'classes' => [ 'href-download', 'text-info' ],
				'data'    => [ 'href-download' => $this->getScanController()->createFileDownloadLink( $this->getEntryVO() ) ]
			],
		];
	}

	/**
	 * @return array
	 */
	protected function getBaseData() {
		return $this->getEntryVO()->getRawDataAsArray();
	}

	/**
	 * @return Scans\Base\BaseResultItem|mixed
	 */
	protected function getResultItem() {
		return ( new Scan\Results\ConvertBetweenTypes() )
			->setScanController( $this->getScanController() )
			->convertVoToResultItem( $this->getEntryVO() );
	}

	/**
	 * @param int $nTimestamp
	 * @return string
	 */
	protected function formatTimestampField( $nTimestamp ) {
		return Services::Request()
					   ->carbon()
					   ->setTimestamp( $nTimestamp )
					   ->diffForHumans()
			   .'<br/><span class="timestamp-small">'
			   .Services::WpGeneral()->getTimeStringForDisplay( $nTimestamp ).'</span>';
	}
}
