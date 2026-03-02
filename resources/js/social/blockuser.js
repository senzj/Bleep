document.addEventListener('DOMContentLoaded', () => {
	const toggle = document.getElementById('block_confirm_modal_toggle');
	const title = document.getElementById('block-confirm-title');
	const message = document.getElementById('block-confirm-message');
	const confirmButton = document.getElementById('block-confirm-submit');
	const forms = document.querySelectorAll('form[data-block-confirm]');

	if (!toggle || !title || !message || !confirmButton || forms.length === 0) {
		return;
	}

	let pendingForm = null;

	forms.forEach((form) => {
		form.addEventListener('submit', (event) => {
			if (form.dataset.confirmed === '1') {
				form.dataset.confirmed = '0';
				return;
			}

			event.preventDefault();
			pendingForm = form;

			const action = form.dataset.blockConfirm;
			const username = form.dataset.username || 'this user';

			if (action === 'block') {
				title.textContent = 'Block user?';
				message.textContent = `You are about to block ${username}. You won't see each other's content.`;
				confirmButton.textContent = 'Yes, block user';
			} else {
				title.textContent = 'Unblock user?';
				message.textContent = `You are about to unblock ${username}. You may see each other's content again.`;
				confirmButton.textContent = 'Yes, unblock user';
			}

			toggle.checked = true;
		});
	});

	confirmButton.addEventListener('click', () => {
		if (!pendingForm) {
			toggle.checked = false;
			return;
		}

		pendingForm.dataset.confirmed = '1';
		toggle.checked = false;
		pendingForm.requestSubmit();
		pendingForm = null;
	});
});
