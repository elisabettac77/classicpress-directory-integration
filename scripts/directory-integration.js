/**
 * Functionality for the ClassicPress directory integration screens.
 */
document.addEventListener('DOMContentLoaded', function () {
	// Find all our new Details buttons
	var openers = document.querySelectorAll('.cp-details-button');
	
	// Ensure we have a dialog element to work with
	var dialog = document.getElementById('cp-details-modal');
	var dialogContent = dialog ? dialog.querySelector('.cp-modal-content') : null;
	var closeButton = dialog ? dialog.querySelector('.cp-modal-close') : null;

	if (!dialog || !dialogContent || !closeButton) {
		return; // Failsafe in case HTML isn't present
	}

	// Helper function to handle localized strings
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;

	/**
	 * Render the content inside the modal when a button is clicked.
	 */
	openers.forEach(function (opener) {
		opener.addEventListener('click', function (e) {
			e.preventDefault();

			// Parse the JSON data attached to the button
			var itemData = JSON.parse(this.getAttribute('data-item-data'));

			// Fallbacks
			var title = itemData.title && itemData.title.rendered ? itemData.title.rendered : __('Details', 'classicpress-directory-integration');
			var author = itemData.meta && itemData.meta.developer_name ? itemData.meta.developer_name : __('Unknown', 'classicpress-directory-integration');
			var version = itemData.meta && itemData.meta.current_version ? itemData.meta.current_version : '';
			var content = itemData.content && itemData.content.rendered ? itemData.content.rendered : __('No description provided.', 'classicpress-directory-integration');
			
			// Build the modal HTML inject
			var html = '';
			html += '<div class="cp-modal-header">';
			html += '<h2>' + title + ' <span class="cp-version">' + version + '</span></h2>';
			html += '<p class="cp-author">' + __('By', 'classicpress-directory-integration') + ' ' + author + '</p>';
			html += '</div>';
			
			html += '<div class="cp-modal-body">';
			html += content; // The API provides this pre-rendered
			html += '</div>';

			// Inject the HTML
			dialogContent.innerHTML = html;

			// Show the modal natively
			dialog.showModal();
			
			// Move focus to close button for accessibility
			closeButton.focus();
		});
	});

	/**
	 * Close the modal via the close button
	 */
	closeButton.addEventListener('click', function () {
		dialog.close();
		dialogContent.innerHTML = ''; // Clear out data
	});

	/**
	 * Close the modal by clicking outside of it (on the backdrop)
	 */
	dialog.addEventListener('click', function(e) {
		var rect = dialog.getBoundingClientRect();
		var isInDialog = (rect.top <= e.clientY && e.clientY <= rect.top + rect.height && rect.left <= e.clientX && e.clientX <= rect.left + rect.width);
		if (!isInDialog) {
			dialog.close();
			dialogContent.innerHTML = '';
		}
	});

});
