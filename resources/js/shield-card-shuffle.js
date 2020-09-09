var Shuffle = window.Shuffle;

var ShieldCardShuffle = function ( element, itemSelector ) {
	this.element = element;

	this.shuffle = new Shuffle( element, {
		itemSelector: itemSelector,
		filterMode: Shuffle.FilterMode.ANY,
	} );

	// Log events.
	// this.addShuffleEventListeners();

	this._activeModFilters = [];
	this._activeStateFilters = [];
	this._activeFilters = [];

	this.addFilterButtons();
	// this.addSorting();
	// this.addSearchFilter();

	this.mode = 'additive';
};

ShieldCardShuffle.prototype.addFilterButtons = function () {
	var filterByMod = document.querySelector( '.filter-groups' );
	if ( filterByMod ) {
		var filterButtons = Array.from( filterByMod.children );
		filterButtons.forEach( function ( button ) {
			button.addEventListener( 'click', this._handleFilterClick.bind( this ), false );
		}, this );
	}

	var filterByStates = document.querySelector( '.filter-states' );
	if ( filterByStates ) {
		var filterStatesButtons = Array.from( filterByStates.children );
		filterStatesButtons.forEach( function ( button ) {
			button.addEventListener( 'click', this._handleFilterClick.bind( this ), false );
		}, this );
	}
};

ShieldCardShuffle.prototype._handleFilterClick = function ( evt ) {
	var btn = evt.currentTarget;
	var isActive = btn.classList.contains( 'active' );
	var btnGroup = btn.getAttribute( 'data-filter' );
	var btnCategory = btn.getAttribute( 'data-category' );
	// You don't need _both_ of these modes. This is only for the demo.

	// For this custom 'additive' mode in the demo, clicking on filter buttons
	// doesn't remove any other filters.
	if ( this.mode === 'additive' ) {

		var workingFilters;
		if ( btnCategory === 'mod' ) {
			workingFilters = this._activeModFilters;
		}
		else { //'state'
			workingFilters = this._activeStateFilters;
		}

		// If this button is already active, remove it from the list of filters.
		if ( isActive ) {
			workingFilters.splice( workingFilters.indexOf( btnGroup ), 1 );
		}
		else {
			workingFilters.push( btnGroup );
		}

		btn.classList.toggle( 'active' );

		// Filter elements
		// this.shuffle.filter( workingFilter );

		var modFilters = this._activeModFilters;
		var stateFilters = this._activeStateFilters;

		this.shuffle.filter( function ( element, shuffle ) {
			// If there is a current filter applied, ignore elements that don't match it.
			// if ( shuffle.group !== Shuffle.ALL_ITEMS ) {
			// Get the item's groups.
				// Get the item's groups.
				var elemGroups = JSON.parse( element.getAttribute( 'data-groups' ) );
				showItem = elemGroups.filter( x => modFilters.includes( x ) ).length > 0
					&& elemGroups.filter( x => stateFilters.includes( x ) ).length > 0;
			return showItem;
		} );

		// 'exclusive' mode lets only one filter button be active at a time.
	}
	else {
		this._removeActiveClassFromChildren( btn.parentNode );

		var filterGroup;
		if ( isActive ) {
			btn.classList.remove( 'active' );
			filterGroup = Shuffle.ALL_ITEMS;
		}
		else {
			btn.classList.add( 'active' );
			filterGroup = btnGroup;
		}

		this.shuffle.filter( filterGroup );
	}
};

ShieldCardShuffle.prototype._removeActiveClassFromChildren = function ( parent ) {
	var children = parent.children;
	for ( var i = children.length - 1; i >= 0; i-- ) {
		children[ i ].classList.remove( 'active' );
	}
};

ShieldCardShuffle.prototype.addSorting = function () {
	var buttonGroup = document.querySelector( '.sort-options' );
	if ( buttonGroup ) {
		buttonGroup.addEventListener( 'change', this._handleSortChange.bind( this ) );
	}
};

ShieldCardShuffle.prototype._handleSortChange = function ( evt ) {
	// Add and remove `active` class from buttons.
	var buttons = Array.from( evt.currentTarget.children );
	buttons.forEach( function ( button ) {
		if ( button.querySelector( 'input' ).value === evt.target.value ) {
			button.classList.add( 'active' );
		}
		else {
			button.classList.remove( 'active' );
		}
	} );

	// Create the sort options to give to Shuffle.
	var value = evt.target.value;
	var options = {};

	function sortByDate( element ) {
		return Date.parse( element.getAttribute( 'data-date-created' ) );
	}

	function sortByTitle( element ) {
		return element.getAttribute( 'data-title' ).toLowerCase();
	}

	if ( value === 'date-created' ) {
		options = {
			reverse: true,
			by: sortByDate,
		};
	}
	else if ( value === 'title' ) {
		options = {
			by: sortByTitle,
		};
	}

	this.shuffle.sort( options );
};

// Advanced filtering
ShieldCardShuffle.prototype.addSearchFilter = function () {
	var searchInput = document.querySelector( '.js-shuffle-search' );
	if ( searchInput ) {
		searchInput.addEventListener( 'input', this._handleSearchKeyup.bind( this ) );
	}
};

/**
 * Filter the shuffle instance by items with a title that matches the search input.
 * @param {Event} evt Event object.
 */
ShieldCardShuffle.prototype._handleSearchKeyup = function ( evt ) {
	var searchText = evt.target.value.toLowerCase();

	this.shuffle.filter( function ( element, shuffle ) {

		// If there is a current filter applied, ignore elements that don't match it.
		if ( shuffle.group !== Shuffle.ALL_ITEMS ) {
			// Get the item's groups.
			var groups = JSON.parse( element.getAttribute( 'data-groups' ) );
			var isElementInCurrentGroup = groups.indexOf( shuffle.group ) !== -1;

			// Only search elements in the current group
			if ( !isElementInCurrentGroup ) {
				return false;
			}
		}

		var titleElement = element.querySelector( '.picture-item__title' );
		var titleText = titleElement.textContent.toLowerCase().trim();

		return titleText.indexOf( searchText ) !== -1;
	} );
};