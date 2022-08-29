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

	public function render() :string {

		$db = $this->getDbHandler();
		if ( $db && !$this->getDbHandler()->isReady() ) {
			$render = __( 'There was an error retrieving entries.', 'wp-simple-firewall' );
		}
		else {
			$this->preBuildTable();

			if ( $this->countTotal() > 0 ) {
				$table = $this->getTableRenderer()
							  ->setItemEntries( $this->getEntriesFormatted() )
							  ->setPerPage( $this->getParams()[ 'limit' ] )
							  ->setTotalRecords( $this->countTotal() )
							  ->prepare_items();
				ob_start();
				$table->display();
				$render = ob_get_clean();
			}
			else {
				$render = $this->buildEmpty();
			}
		}

		return $render;
	}

	/**
	 * @return $this
	 */
	protected function preBuildTable() {
		return $this;
	}

	protected function buildEmpty() :string {
		return sprintf( '<div class="alert alert-success m-0">%s</div>',
			__( "No entries to display.", 'wp-simple-firewall' ) );
	}

	/**
	 * @return array[]|Shield\Databases\Base\EntryVO[]|string[]
	 */
	public function getEntriesFormatted() :array {
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
	protected function getEntriesRaw() :array {
		$aEntries = $this->startQueryProcess()
						 ->applyDefaultQueryFilters()
						 ->applyCustomQueryFilters()
						 ->getWorkingSelector()
						 ->query();
		return $this->postSelectEntriesFilter( $aEntries );
	}

	/**
	 * Override this to filter entries that cannot be filtered using SQL WHERE
	 * @param array[] $entries
	 * @return array[]
	 */
	protected function postSelectEntriesFilter( $entries ) {
		return $entries;
	}

	/**
	 * @return Shield\Tables\Render\WpListTable\Base
	 */
	protected function getTableRenderer() {
		return new Shield\Tables\Render\WpListTable\Base();
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

	private function getFormParams() :array {
		parse_str( Services::Request()->post( 'form_params', '' ), $formParams );
		return Services::DataManipulation()->arrayMapRecursive( $formParams, 'trim' );
	}

	protected function getParamDefaults() :array {
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
	protected function getCustomParams() :array {
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

	protected function startQueryProcess() :self {
		unset( $this->oWorkingSelector );
		return $this;
	}

	protected function getIpAnalysisLink( string $ip ) :string {
		$srvIP = Services::IP();
		$href = $srvIP->isValidIpRange( $ip ) ? $srvIP->getIpWhoisLookup( $ip ) :
			$this->getCon()->getModule_Insights()->getUrl_IpAnalysis( $ip );
		return sprintf(
			'<a href="%s" target="_blank" title="%s" class="ip-whois %s" data-ip="%s">%s</a>',
			$href,
			__( 'IP Analysis' ),
			$srvIP->isValidIpRange( $ip ) ? '' : 'render_ip_analysis',
			$ip,
			$ip
		);
	}
}