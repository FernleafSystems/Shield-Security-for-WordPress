<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan\Handlers\Base;

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

		foreach ( $this->enumHandlers() as $opt => $handlerInit ) {
			if ( $opts->isOpt( 'block_'.$opt, 'Y' ) ) {
				/** @var Base $handler */
				$handler = $handlerInit();
				$result = $handler->setMod( $this->getMod() )->runCheck();
				if ( !empty( $result->get_error_codes() ) ) {
					$this->checkResult = $result;
					break;
				}
			}
		}
	}

	/**
	 * @return callable[]
	 */
	private function enumHandlers() :array {
		return [
			'dir_traversal'    => function () {
				return new Handlers\DirTraversal();
			},
			'sql_queries'      => function () {
				return new Handlers\SqlQueries();
			},
			'wordpress_terms'  => function () {
				return new Handlers\WpTerms();
			},
			'field_truncation' => function () {
				return new Handlers\FieldTruncation();
			},
			'php_code'         => function () {
				return new Handlers\PhpCode();
			},
			'leading_schema'   => function () {
				return new Handlers\LeadingSchema();
			},
			'aggressive'       => function () {
				return new Handlers\Aggressive();
			},
			'exe_file_uploads' => function () {
				return new Handlers\ExeFiles();
			},
		];
	}
}