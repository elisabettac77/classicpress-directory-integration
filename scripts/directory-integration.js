/**
 * Functionality for the ClassicPress directory integration screens.
 */
document.addEventListener('DOMContentLoaded', function () {
	var openers = document.querySelectorAll('.cp-details-button');
	var dialog  = document.getElementById('cp-details-modal');
	if (!dialog) return;

	var dialogContent = dialog.querySelector('.cp-modal-content');
	var closeButton   = dialog.querySelector('.cp-modal-close');
	var __            = wp.i18n.__;

	openers.forEach(function (opener) {
		opener.addEventListener('click', function (e) {
			e.preventDefault();

			var itemData = JSON.parse(this.getAttribute('data-item-data') || '{}');
			var card = this.closest('.cp-card');
			var actionsHtml = card ? card.querySelector('.cp-card-action').innerHTML : '';

			// Copy the visual directly from the grid card (real image OR PHP SVG fallback)
			var visualElement = card.querySelector('.cp-card-banner, .cp-card-screenshot');
			var bgStyle = (visualElement && visualElement.style.backgroundImage) 
				? "background-image: " + visualElement.style.backgroundImage + ";" 
				: "";

			// Textual data safely extracted
			var title = (itemData.title && itemData.title.rendered) ? itemData.title.rendered : __('Details', 'classicpress-directory-integration');
			var author = (itemData.meta && itemData.meta.developer_name) ? itemData.meta.developer_name : __('Unknown', 'classicpress-directory-integration');
			var version = (itemData.meta && itemData.meta.current_version) ? itemData.meta.current_version : '';
			var content = (itemData.content && itemData.content.rendered) ? itemData.content.rendered : __('No description provided.', 'classicpress-directory-integration');

			// Build HTML with the autofocus trap to prevent scroll jump
			dialogContent.innerHTML =
				'<button autofocus class="cp-autofocus-trap" tabindex="-1" aria-hidden="true"></button>' +
				'<div class="cp-modal-visual" style="' + bgStyle + '"></div>' +
				'<div class="cp-modal-body">' +
					'<h2 class="cp-modal-title">' + title + (version ? ' <span class="cp-modal-version">' + version + '</span>' : '') + '</h2>' +
					'<p class="cp-modal-author">' + __('By', 'classicpress-directory-integration') + ' ' + author + '</p>' +
					'<div class="cp-modal-description">' + content + '</div>' +
				'</div>' +
				'<div class="cp-modal-footer">' + actionsHtml + '</div>';

			dialog.showModal();

			// Force scroll to top
			if (window.requestAnimationFrame) {
				window.requestAnimationFrame(function () {
					var body = dialogContent.querySelector('.cp-modal-body');
					if (body) body.scrollTop = 0;
				});
			}
		});
	});

	function closeModal() {
		if (!dialog.hasAttribute('open')) return;
		dialog.classList.add('cp-is-closing');
		
		function onEnd() {
			dialog.classList.remove('cp-is-closing');
			dialog.close();
			dialogContent.innerHTML = '';
			dialog.removeEventListener('animationend', onEnd);
		}
		dialog.addEventListener('animationend', onEnd);
	}

	if (closeButton) {
		closeButton.addEventListener('click', closeModal);
	}

	dialog.addEventListener('click', function (e) {
		var r = dialog.getBoundingClientRect();
		if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) closeModal();
	});

	dialog.addEventListener('cancel', function (e) {
		e.preventDefault();
		closeModal();
	});
});
