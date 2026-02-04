<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Route;

abstract class OptionsBulkBase extends OptionsBase {

	public function getRoutePath() :string {
		return '';
	}

	protected function getRouteArgsDefaults() :array {
		return \array_merge(
			parent::getRouteArgsDefaults(),
			[
				'filter_keys' => [
					'description' => '[Filter][Comma-Separated] Option keys to include.',
					'type'        => 'array', // WordPress kindly converts CSV to array
					'pattern'     => sprintf( '^(((%s),?)+)?$', \implode( '|', $this->getAllPossibleOptKeys() ) ),
					'required'    => false,
				],
			]
		);
	}
}