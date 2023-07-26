<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class BaseBuild {

	use Shield\Modules\PluginControllerConsumer;

	/**
	 * @var array
	 */
	protected $params;

	public function render() :string {
		if ( $this->countTotal() > 0 ) {
			$table = $this->getTableRenderer()
						  ->setItemEntries( $this->getEntriesFormatted() )
						  ->setPerPage( $this->getParams()[ 'limit' ] )
						  ->setTotalRecords( $this->countTotal() )
						  ->prepare_items();
			\ob_start();
			$table->display();
			$render = \ob_get_clean();
		}
		else {
			$render = $this->buildEmpty();
		}

		return $render;
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
		return $this->getWorkingSelector()->query();
	}

	/**
	 * @return Shield\Tables\Render\WpListTable\Base
	 */
	protected function getTableRenderer() {
		return new Shield\Tables\Render\WpListTable\Base();
	}

	public function countTotal() :int {
		return $this->getWorkingSelector()->count();
	}

	protected function getParams() :array {
		if ( empty( $this->params ) ) {
			$this->params = \array_merge( $this->getParamDefaults(), \array_merge( $_POST, $this->getFormParams() ) );
		}
		return $this->params;
	}

	private function getFormParams() :array {
		\parse_str( Services::Request()->post( 'form_params', '' ), $formParams );
		return Services::DataManipulation()->arrayMapRecursive( $formParams, 'trim' );
	}

	protected function getParamDefaults() :array {
		return \array_merge(
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
		return null;
	}
}