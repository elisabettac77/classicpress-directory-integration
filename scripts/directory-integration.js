/**
 * Functionality for the ClassicPress Directory Integration screens.
 */
document.addEventListener( 'DOMContentLoaded', function () {
	// 1. Identify the Dialog
	const dialog = document.getElementById( 'cp-details-modal' );
	if ( ! dialog ) return;

	// Elements inside the modal where we will inject content
	const modalContent = dialog.querySelector( '.cp-modal-content' );
	const closeBtn     = dialog.querySelector( '.cp-modal-close' );

	// 2. Open Modal Logic (Delegated Event Listener)
	// We use delegation on the body so it automatically works for cards loaded via infinite scroll later
	document.body.addEventListener( 'click', function ( e ) {
		const triggerBtn = e.target.closest( '.cp-details-button' );
		
		if ( ! triggerBtn ) return;

		e.preventDefault();

		// Parse the plugin/theme data attached to the button by PHP
		const itemDataRaw = triggerBtn.getAttribute( 'data-item-data' );
		if ( ! itemDataRaw ) return;

		let item;
		try {
			item = JSON.parse( itemDataRaw );
		} catch ( err ) {
			console.error( 'CPDI: Failed to parse item data', err );
			return;
		}

		// Grab the action buttons container from the card so we can clone it into the modal footer
		const card         = triggerBtn.closest( '.cp-card' );
		const actionGroup  = card.querySelector( '.cp-card-action' );
		const actionsClone = actionGroup ? actionGroup.innerHTML : '';

		// Build the HTML for the drawer
		// (We will style this later to look like the sliding sidebar)
		const html = `
			<div class="cp-drawer-header">
				<div class="cp-drawer-header-title">
					<h2>${ wp.i18n.escapeHTML( item.title.rendered ) }</h2>
					<span class="cp-drawer-author">By ${ wp.i18n.escapeHTML( item.meta.developer_name || 'Unknown' ) }</span>
				</div>
			</div>

			<div class="cp-drawer-meta-pills">
				<span class="cp-pill cp-pill-installs">⬇ ${ item.meta.active_installations || 0 } active installs</span>
				${ item.meta.requires_cp ? `<span class="cp-pill cp-pill-cp">CP ${ wp.i18n.escapeHTML( item.meta.requires_cp ) }+</span>` : '' }
				${ item.meta.requires_php ? `<span class="cp-pill cp-pill-php">PHP ${ wp.i18n.escapeHTML( item.meta.requires_php ) }+</span>` : '' }
			</div>

			<div class="cp-drawer-body">
				<div class="cp-drawer-description">
					${ reduceHeaders( item.content.rendered || '' ) }
				</div>
			</div>

			<div class="cp-drawer-footer">
				${ actionsClone }
			</div>
		`;

		// Inject the HTML into the Dialog
		// Note: We keep the close button intact by appending to innerHTML, or just overriding a container.
		// Since we only have the close button currently in the dialog, we'll append.
		
		// First, clear any previous content except the close button
		const existingDynamicContent = dialog.querySelector( '.cp-drawer-container' );
		if ( existingDynamicContent ) {
			existingDynamicContent.remove();
		}

		// Inject new content
		const container = document.createElement( 'div' );
		container.className = 'cp-drawer-container';
		container.innerHTML = html;
		modalContent.appendChild( container );

		// Show the native dialog
		dialog.showModal();

		// Set focus to the close button for accessibility
		if ( closeBtn ) {
			closeBtn.focus();
		}
	});

	// 3. Close Modal Logic
	function closeDialog() {
		dialog.close();
		// Optional: Return focus to the grid if needed, though browsers handle this decently now
	}

	// Close button click
	if ( closeBtn ) {
		closeBtn.addEventListener( 'click', closeDialog );
	}

	// Click on the backdrop (the area outside the dialog)
	dialog.addEventListener( 'click', function ( e ) {
		const rect = dialog.getBoundingClientRect();
		// If the click is outside the bounds of the dialog itself, close it
		const isInDialog = ( rect.top <= e.clientY && e.clientY <= rect.top + rect.height && rect.left <= e.clientX && e.clientX <= rect.left + rect.width );
		
		if ( ! isInDialog ) {
			closeDialog();
		}
	});

	// Escape key is handled natively by <dialog>, so we don't strictly need a keydown listener for it!

	// 4. Helper to demote heading tags (H1 -> H3, H2 -> H3) to prevent breaking accessibility hierarchy
	function reduceHeaders( content ) {
		return content.replace( /<(\/?)h[12]/gi, '<$1h3' );
	}

});
