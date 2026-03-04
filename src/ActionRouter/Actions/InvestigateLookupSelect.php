<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\InvestigateAssetLookupOptionsBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\IpLookupSearch;
use FernleafSystems\Wordpress\Services\Services;

class InvestigateLookupSelect extends BaseAction {

	public const SLUG = 'investigate_lookup_select';
	private const RESULT_LIMIT = 20;

	protected function getDefaults() :array {
		return [
			'subject' => '',
			'search'  => '',
		];
	}

	protected function exec() {
		$subject = sanitize_key( (string)$this->action_data[ 'subject' ] );
		$search = \strtolower( \trim( sanitize_text_field( (string)$this->action_data[ 'search' ] ) ) );
		$minimumLength = $subject === 'user' ? 1 : 2;

		$results = \strlen( $search ) < $minimumLength
			? []
			: $this->searchBySubject( $subject, $search );

		$this->response()->setPayload( [
			'results' => $results,
		] )->setPayloadSuccess( true );
	}

	/**
	 * @return array<int,array{id:string,text:string}>
	 */
	private function searchBySubject( string $subject, string $search ) :array {
		switch ( $subject ) {
			case 'user':
				return $this->searchUsers( $search );
			case 'ip':
				return $this->searchIps( $search );
			case 'plugin':
				return $this->searchPlugins( $search );
			case 'theme':
				return $this->searchThemes( $search );
			default:
				return [];
		}
	}

	/**
	 * @return array<int,array{id:string,text:string}>
	 */
	private function searchUsers( string $search ) :array {
		$results = [];
		$seen = [];

		if ( \ctype_digit( $search ) ) {
			$user = Services::WpUsers()->getUserById( (int)$search );
			if ( $user instanceof \WP_User ) {
				$results[] = $this->buildUserResult( $user );
				$seen[ $user->ID ] = true;
			}
		}

		$remaining = self::RESULT_LIMIT - \count( $results );
		if ( $remaining < 1 ) {
			return $results;
		}

		$query = new \WP_User_Query( [
			'number'         => $remaining,
			'search'         => '*'.$search.'*',
			'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
			'orderby'        => 'user_login',
			'order'          => 'ASC',
			'fields'         => [ 'ID', 'user_login', 'user_email', 'display_name' ],
		] );

		foreach ( $query->get_results() as $userRow ) {
			if ( !\is_object( $userRow ) ) {
				continue;
			}
			$userId = (int)( $userRow->ID ?? 0 );
			if ( $userId < 1 || isset( $seen[ $userId ] ) ) {
				continue;
			}
			$seen[ $userId ] = true;
			$results[] = $this->buildUserResultFromFields(
				$userId,
				(string)( $userRow->user_login ?? '' ),
				(string)( $userRow->display_name ?? '' ),
				(string)( $userRow->user_email ?? '' )
			);
			if ( \count( $results ) >= self::RESULT_LIMIT ) {
				break;
			}
		}

		return $results;
	}

	/**
	 * @return array{id:string,text:string}
	 */
	private function buildUserResult( \WP_User $user ) :array {
		return $this->buildUserResultFromFields(
			(int)$user->ID,
			(string)$user->user_login,
			(string)$user->display_name,
			(string)$user->user_email
		);
	}

	/**
	 * @return array{id:string,text:string}
	 */
	private function buildUserResultFromFields( int $userId, string $userLogin, string $displayName, string $userEmail ) :array {
		$parts = [ $userLogin ];
		if ( !empty( $displayName ) && $displayName !== $userLogin ) {
			$parts[] = $displayName;
		}
		if ( !empty( $userEmail ) ) {
			$parts[] = $userEmail;
		}

		return [
			'id'   => (string)$userId,
			'text' => \implode( ' | ', $parts ),
		];
	}

	/**
	 * @return array<int,array{id:string,text:string}>
	 */
	private function searchIps( string $search ) :array {
		$ips = ( new IpLookupSearch() )->findMatchingIps( $search, self::RESULT_LIMIT );

		return \array_map(
			static fn( string $ip ) :array => [
				'id'   => $ip,
				'text' => $ip,
			],
			$ips
		);
	}

	/**
	 * @return array<int,array{id:string,text:string}>
	 */
	private function searchPlugins( string $search ) :array {
		$options = ( new InvestigateAssetLookupOptionsBuilder() )->build(
			Services::WpPlugins()->getPluginsAsVo(),
			'file',
			$search,
			self::RESULT_LIMIT
		);

		return \array_map(
			static fn( array $option ) :array => [
				'id'   => $option[ 'value' ],
				'text' => $option[ 'label' ],
			],
			$options
		);
	}

	/**
	 * @return array<int,array{id:string,text:string}>
	 */
	private function searchThemes( string $search ) :array {
		$options = ( new InvestigateAssetLookupOptionsBuilder() )->build(
			Services::WpThemes()->getThemesAsVo(),
			'stylesheet',
			$search,
			self::RESULT_LIMIT
		);

		return \array_map(
			static fn( array $option ) :array => [
				'id'   => $option[ 'value' ],
				'text' => $option[ 'label' ],
			],
			$options
		);
	}
}
