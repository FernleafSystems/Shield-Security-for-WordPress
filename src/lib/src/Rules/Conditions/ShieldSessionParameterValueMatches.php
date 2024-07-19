<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Enum,
	Utility
};
use FernleafSystems\Wordpress\Services\Services;

class ShieldSessionParameterValueMatches extends Base {

	use Traits\TypeSession;

	protected function execConditionCheck() :bool {
		$matched = false;
		$session = $this->req->session;
		$value = $session->shield[ $this->p->param_name ] ?? null;
		if ( $value !== null ) {
			$matched = ( new Utility\PerformConditionMatch( $value, $this->p->match_pattern, $this->p->match_type ) )->doMatch();
			$user = Services::WpUsers()->getUserById( $session->shield[ 'user_id' ] ?? 0 );
			$this->addConditionTriggerMeta( 'user_login', $user->user_login ?? '' );
			$this->addConditionTriggerMeta( 'match_pattern', $this->p->match_pattern );
			$this->addConditionTriggerMeta( 'match_request_param', $this->p->param_name );
			$this->addConditionTriggerMeta( 'match_request_value', $value );
		}
		return $matched;
	}

	public function getDescription() :string {
		return __( 'Does the value of the given Shield Session parameter match the given pattern.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		$parameters = [
			'ip'               => __( 'IP Address' ),
			'user_id'          => __( 'User ID' ),
			'hostname'         => __( 'Hostname' ),
			'useragent'        => __( 'Useragent' ),
			'idle_interval'    => sprintf( '%s (%s)', __( 'Idle Interval', 'wp-simple-firewall' ), __( 'seconds' ) ),
			'session_duration' => sprintf( '%s (%s)', __( 'Session Duration', 'wp-simple-firewall' ), __( 'seconds' ) ),
			'token_duration'   => sprintf( '%s (%s)', __( 'Session Token Duration', 'wp-simple-firewall' ), __( 'seconds' ) ),
		];
		return [
			'param_name'    => [
				'type'        => Enum\EnumParameters::TYPE_ENUM,
				'type_enum'   => \array_keys( $parameters ),
				'enum_labels' => $parameters,
				'label'       => __( 'Session Parameter', 'wp-simple-firewall' ),
			],
			'match_type'    => [
				'type'      => Enum\EnumParameters::TYPE_ENUM,
				'type_enum' => \array_unique( \array_merge(
					Enum\EnumMatchTypes::MatchTypesForStrings(), Enum\EnumMatchTypes::MatchTypesForNumbers()
				) ),
				'default'   => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_pattern' => [
				'type'  => Enum\EnumParameters::TYPE_STRING,
				'label' => __( 'Compare Parameter Value To', 'wp-simple-firewall' ),
			],
		];
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => Enum\EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => IsLoggedInNormal::class,
				],
				[
					'conditions' => ShieldHasValidCurrentSession::class,
				],
				[
					'conditions' => $this->getDefaultConditionCheckCallable(),
				],
			]
		];
	}
}