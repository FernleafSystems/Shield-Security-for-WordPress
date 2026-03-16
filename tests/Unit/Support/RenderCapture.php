<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

class RenderCapture {

	/**
	 * @var list<array{action:string,action_data:array<string,mixed>}>
	 */
	public array $calls = [];

	public function record( string $action, array $actionData = [] ) :void {
		$this->calls[] = [
			'action'      => $action,
			'action_data' => $actionData,
		];
	}
}
