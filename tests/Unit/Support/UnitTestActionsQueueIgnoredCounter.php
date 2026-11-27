<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\ActionsQueueIgnoredCounter;

/**
 * Says how many items are ignored without reading a site's to-dos or scan results, and records what it was asked.
 */
class UnitTestActionsQueueIgnoredCounter extends ActionsQueueIgnoredCounter {

	/**
	 * @var list<string>
	 */
	public array $asked = [];

	private int $toDos;
	private int $securityIssues;

	public function __construct( int $toDos = 0, int $securityIssues = 0 ) {
		$this->toDos = $toDos;
		$this->securityIssues = $securityIssues;
	}

	public function toDos() :int {
		$this->asked[] = 'to_dos';
		return $this->toDos;
	}

	public function securityIssues() :int {
		$this->asked[] = 'security_issues';
		return $this->securityIssues;
	}
}
