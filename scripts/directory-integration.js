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
			
			// Figure out the visual image (Banner for plugins, Screenshot for themes)
			var visualUrl = '';
			if (itemData.meta && itemData.meta.screenshot_url) {
				visualUrl = itemData.meta.screenshot_url;
			} else if (itemData.meta && itemData.meta.banner_url) {
				visualUrl = itemData.meta.banner_url;
			}

			// Extract textual data with fallbacks
			var title = itemData.title && itemData.title.rendered ? itemData.title.rendered : __('Details', 'classicpress-directory-integration');
			var author = itemData.meta && itemData.meta.developer_name ? itemData.meta.developer_name : __('Unknown', 'classicpress-directory-integration');
			var version = itemData.meta && itemData.meta.current_version ? itemData.meta.current_version : '';
			var content = itemData.content && itemData.content.rendered ? itemData.content.rendered : __('No description provided.', 'classicpress-directory-integration');
			
			var html = '';
			
			// Top Visual Area
			if (visualUrl) {
				html += '<div class="cp-modal-visual" style="background-image: url(\'' + visualUrl + '\');"></div>';
			} else {
				html += '<div class="cp-modal-visual cp-modal-visual-fallback"></div>';
			}
			
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
