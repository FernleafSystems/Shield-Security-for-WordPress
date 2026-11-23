import { BaseComponent } from "../BaseComponent";
import { ObjectOps } from "../../util/ObjectOps";
import { SearchBox } from "./SearchBox";

/**
 * The search dialog's search: the shared search box, its results in the shared list.
 */
export class SuperSearchResults extends BaseComponent {

	init() {
		const root = document.querySelector( '#ModalSuperSearchBox [data-super-search="1"]' );
		this.searchBox = new SearchBox( {
			container: root,
			input: root.querySelector( '#ModalSuperSearchInput' ),
			body: root.querySelector( '[data-super-search-body="1"]' ),
			buildAction: ( query ) => ObjectOps.Merge( ObjectOps.ObjClone( this._base_data.ajax.render_search_results ), { search: query } ),
			loadingText: this._base_data.strings.loading,
			errorText: this._base_data.strings.error,
		} );
	}
}
