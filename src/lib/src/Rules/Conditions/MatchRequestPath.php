<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\PathsToMatchUnavailableException;

/**
 * @property bool   $is_match_regex
 * @property string $match_path
 */
class MatchRequestPath extends Base {

	use Traits\RequestPath;
	use Traits\TypeRequest;

	public const SLUG = 'match_request_path';

	public function getDescription() :string {
		return __( 'Does the request path match the given path.', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		if ( empty( $this->match_path ) ) {
			throw new PathsToMatchUnavailableException();
		}

		$path = $this->getRequestPath();
		$this->addConditionTriggerMeta( 'matched_path', $path );
		return $this->is_match_regex ?
			(bool)\preg_match( sprintf( '#%s#i', $this->match_path ), $path )
			: $this->match_path === $path;
	}

	public function getParamsDef() :array {
		return [
			'match_path'     => [
				'type'  => 'string',
				'label' => __( 'Path To Match', 'wp-simple-firewall' ),
			],
			'is_match_regex' => [
				'type'    => 'bool',
				'label'   => __( 'Is Match Regex', 'wp-simple-firewall' ),
				'default' => true,
			],
		];
	}
}