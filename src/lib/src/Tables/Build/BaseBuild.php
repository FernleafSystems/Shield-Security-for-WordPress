<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class BaseBuild {

	use Shield\Databases\Base\HandlerConsumer;
	use Shield\Modules\ModConsumer;

	/**
	 * @var Shield\Databases\Base\Select
	 */
	protected $oWorkingSelector;

	/**
	 * @var array
	 */
	protected $aBuildParams;

	/**
	 * @return string
	 */
	public function buildTable() {

		try {
			$bReady = $this->getDbHandler()->isReady();
		}
		catch ( \Exception $oE ) {
			$bReady = false;
		}

		$this->preBuildTable();

		if ( $bReady && $this->countTotal() > 0 ) {
			$oTable = $this->getTableRenderer()
						   ->setItemEntries( $this->getEntriesFormatted() )
						   ->setPerPage( $this->getParams()[ 'limit' ] )
						   ->setTotalRecords( $this->countTotal() )
						   ->prepare_items();
			ob_start();
			$oTable->display();
			$sRendered = ob_get_clean();
		}
		else {
			$sRendered = $this->buildEmpty();
		}

		return empty( $sRendered ) ? __( 'There was an error retrieving entries.', 'wp-simple-firewall' ) : $sRendered;
	}

	/**
	 * @return $this
	 */
	protected function preBuildTable() {
		return $this;
	}

	/**
	 * @return string
	 */
	protected function buildEmpty() {
		return sprintf( '<div class="alert alert-success m-0">%s</div>',
			__( "No entries to display.", 'wp-simple-firewall' ) );
	}

	/**
	 * @return array[]|int|string[]
	 */
	protected function getEntriesFormatted() {
		return $this->getEntriesRaw();
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

	/**
	 * @return array[]|string[]|Shield\Databases\Base\EntryVO[]|array
	 */
	protected function getEntriesRaw() {
		$aEntries = $this->startQueryProcess()
						 ->applyDefaultQueryFilters()
						 ->applyCustomQueryFilters()
						 ->getWorkingSelector()
						 ->query();
		return $this->postSelectEntriesFilter( $aEntries );
	}

	/**
	 * Override this to filter entries that cannot be filtered using SQL WHERE
	 * @param array[] $aEntries
	 * @return array[]
	 */
	protected function postSelectEntriesFilter( $aEntries ) {
		return $aEntries;
	}

	/**
	 * @return Shield\Tables\Render\Base
	 */
	protected function getTableRenderer() {
		return new Shield\Tables\Render\Base();
	}

	/**
	 * @return int
	 */
	public function countTotal() {
		return $this->startQueryProcess()
					->applyCustomQueryFilters()
					->getWorkingSelector()
					->count();
	}

	/**
	 * Override this to apply table-specific query filters.
	 * @return $this
	 */
	protected function applyCustomQueryFilters() {
		return $this;
	}

	/**
	 * @return $this
	 */
	protected function applyDefaultQueryFilters() {
		$aParams = $this->getParams();
		$oSelect = $this->getWorkingSelector();

		if ( isset( $aParams[ 'paged' ] ) ) {
			$oSelect->setPage( $aParams[ 'paged' ] );
		}
		if ( isset( $aParams[ 'orderby' ] ) && isset( $aParams[ 'order' ] ) ) {
			$oSelect->setOrderBy( $aParams[ 'orderby' ], $aParams[ 'order' ] );
		}
		if ( isset( $aParams[ 'limit' ] ) ) {
			$oSelect->setLimit( $aParams[ 'limit' ] );
		}
		$oSelect->setResultsAsVo( true );
		return $this;
	}

	/**
	 * @return array
	 */
	protected function getParams() {
		if ( empty( $this->aBuildParams ) ) {
			$this->aBuildParams = array_merge(
				$this->getParamDefaults(),
				array_merge( $_POST, $this->getFormParams() )
			);
		}
		return $this->aBuildParams;
	}

	/**
	 * @return array
	 */
	private function getFormParams() {
		parse_str( Services::Request()->post( 'form_params', '' ), $aFormParams );
		return Services::DataManipulation()->arrayMapRecursive( $aFormParams, 'trim' );
	}

	/**
	 * @return array
	 */
	protected function getParamDefaults() {
		return array_merge(
			[
				'paged'   => 1,
				'order'   => 'DESC',
				'orderby' => 'created_at',
				'limit'   => 25,
			],
			$this->getCustomParams()
		);
	}

	/**
	 * Override to allow other parameter keys for building the table
	 * @return array
	 */
	protected function getCustomParams() {
		return [];
	}

	/**
	 * @return Shield\Databases\Base\Select
	 */
	public function getWorkingSelector() {
		if ( empty( $this->oWorkingSelector ) ) {
			$this->oWorkingSelector = $this->getDbHandler()->getQuerySelector();
		}
		return $this->oWorkingSelector;
	}

	/**
	 * @return $this
	 */
	protected function startQueryProcess() {
		unset( $this->oWorkingSelector );
		return $this;
	}
}