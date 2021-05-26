<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class PerformScan extends ExecOnceModConsumer {

	/**
	 * @var false|\WP_Error
	 */
	private $checkResult = false;

	/**
	 * @return false|\WP_Error
	 */
	public function getCheckResult() {
		return $this->checkResult;
	}

	protected function canRun() :bool {
		return ( new CanScan() )
			->setMod( $this->getMod() )
			->run();
	}

	protected function run() {
		$opts = $this->getOptions();

		$params = ( new ParametersToScan() )
			->setMod( $this->getMod() )
			->retrieve();

		$standardChecker = ( new Checks\Standard() )
			->setMod( $this->getMod() );
		foreach ( $this->getStandardChecks() as $opt => $check ) {
			if ( $opts->isOpt( 'block_'.$opt, 'Y' ) ) {
				$this->checkResult = $standardChecker
					->setCheck( $check )
					->run( $params );
				if ( is_wp_error( $this->checkResult ) ) {
					break;
				}
			}
		}

		if ( !is_wp_error( $this->checkResult ) ) {
			$this->checkResult = ( new Checks\ExeFiles() )
				->setMod( $this->getMod() )
				->setCheck( 'exefile' )
				->run();
		}
	}

	private function getStandardChecks() :array {
		return [
			'dir_traversal'    => 'dirtraversal',
			'sql_queries'      => 'sqlqueries',
			'wordpress_terms'  => 'wpterms',
			'field_truncation' => 'fieldtruncation',
			'php_code'         => 'phpcode',
			'leading_schema'   => 'schema',
			'aggressive'       => 'aggressive',
		];
	}
}