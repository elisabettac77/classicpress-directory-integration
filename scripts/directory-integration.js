/**
 * Functionality for the ClassicPress directory integration screens.
 */
document.addEventListener('DOMContentLoaded', function () {
	var openers = document.querySelectorAll('.cp-details-button');
	var dialog = document.getElementById('cp-details-modal');
	var dialogContent = dialog ? dialog.querySelector('.cp-modal-content') : null;
	var closeButton = dialog ? dialog.querySelector('.cp-modal-close') : null;

	if (!dialog || !dialogContent || !closeButton) {
		return;
	}

	var __ = wp.i18n.__;

	openers.forEach(function (opener) {
		opener.addEventListener('click', function (e) {
			e.preventDefault();

			var itemData = JSON.parse(this.getAttribute('data-item-data'));
			var card = this.closest('.cp-card');
			
			// Extract the action buttons from the grid card to reuse in the modal footer
			var actionsHtml = card.querySelector('.cp-card-action').innerHTML;
			
			// 1. EXTRACT TEXTUAL DATA FIRST (So we can use the title for the SVG)
			var title = itemData.title && itemData.title.rendered ? itemData.title.rendered : __('Details', 'classicpress-directory-integration');
			var author = itemData.meta && itemData.meta.developer_name ? itemData.meta.developer_name : __('Unknown', 'classicpress-directory-integration');
			var version = itemData.meta && itemData.meta.current_version ? itemData.meta.current_version : '';
			var content = itemData.content && itemData.content.rendered ? itemData.content.rendered : __('No description provided.', 'classicpress-directory-integration');

			// 2. FIGURE OUT VISUAL IMAGE
			var visualUrl = '';
			if (itemData.meta && itemData.meta.screenshot_url) {
				visualUrl = itemData.meta.screenshot_url;
			} else if (itemData.meta && itemData.meta.banner_url) {
				visualUrl = itemData.meta.banner_url;
			}

			// 3. GENERATE VISUAL STYLES (Including the SVG fallback using the 'title' variable)
			var visualStyles = '';
			if (visualUrl) {
				visualStyles = 'background-image: url(\'' + visualUrl + '\'); background-size: cover;';
			} else {
				// Create an SVG with the first letter of the item name
				var firstLetter = title.charAt(0).toUpperCase();
				var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 250" preserveAspectRatio="none"><rect width="800" height="250" fill="#2271b1"/><text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" font-family="system-ui, sans-serif" font-size="120" font-weight="bold" fill="rgba(255,255,255,0.2)">' + firstLetter + '</text><text x="50%" y="75%" dominant-baseline="middle" text-anchor="middle" font-family="system-ui, sans-serif" font-size="24" fill="rgba(255,255,255,0.4)">' + title + '</text></svg>';
				visualStyles = 'background-image: url(\'data:image/svg+xml;utf8,' + encodeURIComponent(svg) + '\'); background-size: cover;';
			}
			
			// 4. BUILD THE HTML
			var html = '';
			
			// Top Visual Area
			html += '<div class="cp-modal-visual" style="' + visualStyles + '"></div>';
			
			// Scrollable Body
			html += '<div class="cp-modal-body">';
			html += '<h2 class="cp-modal-title">' + title + ' <span class="cp-modal-version">' + version + '</span></h2>';
			html += '<p class="cp-modal-author">' + __('By', 'classicpress-directory-integration') + ' ' + author + '</p>';
			html += '<div class="cp-modal-description">' + content + '</div>';
			html += '</div>';

			// Sticky Footer
			html += '<div class="cp-modal-footer">';
			html += actionsHtml;
			html += '</div>';

			dialogContent.innerHTML = html;

			// Reset scroll position to top before opening
			var modalBody = dialogContent.querySelector('.cp-modal-body');
			if (modalBody) {
				modalBody.scrollTop = 0;
			}
			
			// Show the modal natively
			dialog.showModal();
			closeButton.focus();
		});
	});

	/**
	 * Smooth close function (slides out before clearing DOM)
	 */
	function closeModal() {
		dialog.classList.add('cp-is-closing');
		dialog.addEventListener('animationend', function handleClose() {
			dialog.classList.remove('cp-is-closing');
			dialog.close();
			dialogContent.innerHTML = ''; 
			dialog.removeEventListener('animationend', handleClose);
		}, { once: true });
	}

	closeButton.addEventListener('click', closeModal);

	// Close on backdrop click
	dialog.addEventListener('click', function(e) {
		var rect = dialog.getBoundingClientRect();
		var isInDialog = (rect.top <= e.clientY && e.clientY <= rect.top + rect.height && rect.left <= e.clientX && e.clientX <= rect.left + rect.width);
		if (!isInDialog) {
			closeModal();
		}
	});
});
