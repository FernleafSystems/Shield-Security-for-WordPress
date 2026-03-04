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
		$minimumLength = [
							 'user'   => 1,
							 'ip'     => 3,
							 'plugin' => 2,
							 'theme'  => 2,
						 ][ $subject ] ?? 2;

		$this->response()->setPayload( [
			'results' => \strlen( $search ) < $minimumLength ? [] : $this->searchBySubject( $subject, $search ),
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

		$userRows = \ctype_digit( $search )
			? $this->searchUsersByNumericTerm( $search )
			: $this->searchUsersByTextTerm( $search );

		foreach ( $userRows as $userRow ) {
			$this->appendUserResultFromRow( $results, $seen, $userRow );
			if ( \count( $results ) >= self::RESULT_LIMIT ) {
				break;
			}
		}

		return $results;
	}

	private function appendUserResultFromRow( array &$results, array &$seen, $userRow ) :void {
		if ( !\is_object( $userRow ) ) {
			return;
		}
		$userId = (int)( $userRow->ID ?? 0 );
		if ( $userId < 1 || isset( $seen[ $userId ] ) ) {
			return;
		}

		$seen[ $userId ] = true;
		$results[] = $this->buildUserResultFromFields(
			$userId,
			(string)( $userRow->user_login ?? '' ),
			(string)( $userRow->display_name ?? '' ),
			(string)( $userRow->user_email ?? '' )
		);
	}

	private function searchUsersByNumericTerm( string $search ) :array {
		$wpdb = Services::WpDb()->loadWpdb();
		$like = '%'.$wpdb->esc_like( $search ).'%';
		$rows = $wpdb->get_results(
			(string)$wpdb->prepare(
				\sprintf(
					"SELECT `ID`, `user_login`, `display_name`, `user_email`
				FROM `%s`
				WHERE CAST(`ID` AS CHAR) LIKE %%s
					OR `user_login` LIKE %%s
					OR `user_email` LIKE %%s
					OR `display_name` LIKE %%s
				ORDER BY (`ID` = %%d) DESC, `user_login` ASC
				LIMIT %%d",
					$wpdb->users
				),
				$like,
				$like,
				$like,
				$like,
				(int)$search,
				self::RESULT_LIMIT
			)
		);
		return \is_array( $rows ) ? $rows : [];
	}

	private function searchUsersByTextTerm( string $search ) :array {
		$rows = ( new \WP_User_Query( [
			'number'         => self::RESULT_LIMIT,
			'search'         => '*'.$search.'*',
			'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
			'orderby'        => 'user_login',
			'order'          => 'ASC',
			'fields'         => [ 'ID', 'user_login', 'user_email', 'display_name' ],
		] ) )->get_results();
		return \is_array( $rows ) ? $rows : [];
	}

	/**
	 * @return array{id:string,text:string}
	 */
	private function buildUserResultFromFields( int $userId, string $userLogin, string $displayName, string $userEmail ) :array {
		$username = !empty( $userLogin ) ? $userLogin : $displayName;
		$label = \sprintf( '[ID:%d] %s', $userId, $username );
		if ( !empty( $userEmail ) ) {
			$label .= ' | '.$userEmail;
		}

		return [
			'id'   => (string)$userId,
			'text' => $label,
		];
	}

	/**
	 * @return array<int,array{id:string,text:string}>
	 */
	private function searchIps( string $search ) :array {
		return \array_map(
			static fn( string $ip ) :array => [
				'id'   => $ip,
				'text' => $ip,
			],
			( new IpLookupSearch() )->findMatchingIps( $search, self::RESULT_LIMIT )
		);
	}

	/**
	 * @return array<int,array{id:string,text:string}>
	 */
	private function searchPlugins( string $search ) :array {
		return $this->searchAssets(
			Services::WpPlugins()->getPluginsAsVo(),
			'file',
			$search
		);
	}

	/**
	 * @return array<int,array{id:string,text:string}>
	 */
	private function searchThemes( string $search ) :array {
		return $this->searchAssets(
			Services::WpThemes()->getThemesAsVo(),
			'stylesheet',
			$search
		);
	}

	/**
	 * @param object[] $assets
	 * @return array<int,array{id:string,text:string}>
	 */
	private function searchAssets( array $assets, string $valueField, string $search ) :array {
		return \array_map(
			static fn( array $option ) :array => [
				'id'   => $option[ 'value' ],
				'text' => $option[ 'label' ],
			],
			( new InvestigateAssetLookupOptionsBuilder() )->build(
				$assets,
				$valueField,
				$search,
				self::RESULT_LIMIT
			)
		);
	}
}
