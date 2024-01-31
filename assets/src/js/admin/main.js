/**
 * Plausible Analytics
 *
 * Admin JS
 */
document.addEventListener('DOMContentLoaded', () => {
	if (!document.location.href.includes('plausible_analytics')) {
		return;
	}

	if (document.location.hash === '' && document.getElementById('plausible-analytics-wizard') !== null) {
		document.location.hash = 'welcome_slide';
	}

	plausibleToggleWizardStep();

	/**
	 * Toggle active state for wizard menu item.
	 */
	window.addEventListener('hashchange', () => {
		plausibleToggleWizardStep();
	});

	document.addEventListener('click', (e) => {
		if (e.target.id !== 'plausible-create-api-token') {
			return;
		}

		plausibleCreateAPIToken(e);
	});

	/**
	 * Disable Connect button if required fields aren't entered.
	 */
	document.addEventListener('change', (e) => {
		if (e.target.id !== 'domain_name' && e.target.id !== 'api_token') {
			return;
		}

		let target = e.target;
		let button = document.getElementById('connect_plausible_analytics');
		let buttonIsHref = false;

		if (button === null) {
			let slide_id = document.location.hash;
			button = document.querySelector(slide_id + ' .plausible-analytics-wizard-next-step');
			buttonIsHref = true;
		}

		if (button === null) {
			return;
		}

		if (target.value !== '') {
			if (!buttonIsHref) {
				button.disabled = false;
			} else {
				button.classList.remove('pointer-events-none');
				button.classList.replace('bg-gray-200', 'bg-indigo-600')
			}

			return;
		}

		if (!buttonIsHref) {
			button.disabled = true;
		} else {
			button.classList += ' pointer-events-none';
			button.classList.replace('bg-indigo-600', 'bg-gray-200')
		}
	});

	/**
	 * Save Options on Next click.
	 */
	document.addEventListener('click', (e) => {
		if (e.target.classList === undefined || !e.target.classList.contains('plausible-analytics-wizard-next-step')) {
			return;
		}

		let hash = document.location.hash.replace('#', '');

		if (hash === 'api_token_slide' || hash === 'domain_name_slide') {
			let form = e.target.closest('.plausible-analytics-wizard-step-section');
			let inputs = form.getElementsByTagName('INPUT');
			let options = [];

			for (let input of inputs) {
				options.push({name: input.name, value: input.value});
			}

			let data = new FormData();
			data.append('action', 'plausible_analytics_save_options');
			data.append('_nonce', document.getElementById('_wpnonce').value);
			data.append('options', JSON.stringify(options));

			plausibleAjax(data, null, false);
		}
	});

	/**
	 * Quit Wizard
	 */
	document.addEventListener('click', (e) => {
		if (e.target.id !== 'plausible-analytics-wizard-quit') {
			return;
		}

		const form = new FormData();

		form.append('action', 'plausible_analytics_quit_wizard');
		form.append('_nonce', e.target.dataset.nonce);

		fetch(
			ajaxurl,
			{
				method: 'POST',
				body: form,
			}
		).then(response => {
			window.location.reload();
		});
	});

	/**
	 * Save Options.
	 */
	document.addEventListener('click', (e) => {
		if (!e.target.classList.contains('plausible-analytics-button')) {
			return;
		}

		const button = e.target;
		const section = button.closest('.plausible-analytics-section');
		const inputs = section.querySelectorAll('input, textarea');
		const form = new FormData();
		const options = [];

		inputs.forEach(function (input) {
			options.push({name: input.name, value: input.value});
		});

		form.append('action', 'plausible_analytics_save_options');
		form.append('options', JSON.stringify(options));
		form.append('_nonce', document.getElementById('_wpnonce').value);

		button.children[0].classList.remove('hidden');
		button.setAttribute('disabled', 'disabled');

		plausibleAjax(form, button);
	});

	/**
	 * Toggle Options.
	 */
	document.addEventListener('click', (e) => {
		if (!e.target.classList.contains('plausible-analytics-toggle')) {
			return;
		}

		const button = e.target.closest('button');
		let toggle = '';
		let toggleStatus = '';

		// The button element is clicked.
		if (e.target.type === 'submit') {
			toggle = button.querySelector('span');
		} else {
			// The span element is clicked.
			toggle = e.target.closest('span');
		}

		if (button.classList.contains('bg-indigo-600')) {
			// Toggle: off
			button.classList.replace('bg-indigo-600', 'bg-gray-200');
			toggle.classList.replace('translate-x-5', 'translate-x-0');
			toggleStatus = '';
		} else {
			// Toggle: on
			button.classList.replace('bg-gray-200', 'bg-indigo-600');
			toggle.classList.replace('translate-x-0', 'translate-x-5');
			toggleStatus = 'on';
		}

		const form = new FormData();
		form.append('action', 'plausible_analytics_toggle_option');
		form.append('option_name', button.name);
		form.append('option_value', button.value);
		form.append('option_label', button.nextElementSibling.innerHTML);
		form.append('toggle_status', toggleStatus);
		form.append('is_list', button.dataset.list);
		form.append('_nonce', document.getElementById('_wpnonce').value);

		let reload = false;
		let showNotice = true;

		if (button.name === 'proxy_enabled' || button.name === 'enable_analytics_dashboard') {
			reload = true;
			showNotice = false;
		}

		plausibleAjax(form, null, showNotice, reload);
	});
});

/**
 * Do AJAX request and (optionally) show a notice.
 *
 * @param data
 * @param button
 * @param showNotice
 * @param reload
 */
function plausibleAjax(data, button = null, showNotice = true, reload = false) {
	fetch(
		ajaxurl,
		{
			method: 'POST',
			body: data,
		}
	).then(response => {
		if (button) {
			button.children[0].classList += ' hidden';
			button.removeAttribute('disabled');
		}

		if (response.status === 200) {
			return response.json();
		}

		return false;
	}).then(response => {
		if (showNotice === true) {
			if (response.success === true) {
				plausibleShowNotice(response.data);
			} else {
				plausibleShowNotice(response.data, true);
			}
		}

		let event = new CustomEvent('plausibleAjaxDone', {detail: response});

		document.dispatchEvent(event);

		if (reload === true) {
			window.location.reload();
		}

		return response.success;
	});
}

/**
 * Open Create API Token dialog.
 */
function plausibleCreateAPIToken(e) {
	e.preventDefault();

	let domain = document.getElementById('domain_name').value;

	window.open(`https://plausible.io/${domain}/settings/integrations?new_token=WordPress`, '_blank', 'location=yes,height=768,width=1024,scrollbars=yes,status=no');
}

/**
 * Show notice.
 *
 * @param message
 * @param isError
 */
function plausibleShowNotice(message, isError = false) {
	if (isError === true) {
		document.getElementById('icon-error').classList.remove('hidden');
		document.getElementById('icon-success').classList += ' hidden';
	} else {
		document.getElementById('icon-success').classList.remove('hidden');
		document.getElementById('icon-error').classList += ' hidden';
	}

	let notice = document.getElementById('plausible-analytics-notice');

	document.getElementById('plausible-analytics-notice-text').innerHTML = message;

	notice.classList.remove('hidden');

	setTimeout(function () {
		notice.classList.replace('opacity-0', 'opacity-100');
	}, 200)

	if (isError === false) {
		setTimeout(function () {
			notice.classList.replace('opacity-100', 'opacity-0');
			setTimeout(function () {
				notice.classList += ' hidden';
			}, 200)
		}, 2500);
	}
}

/**
 * Toggles the font-weight of the wizard's steps.
 */
function plausibleToggleWizardStep() {
	if (document.getElementById('plausible-analytics-wizard') === null) {
		return;
	}

	const hash = document.location.hash.substring(1).replace('_slide', '');

	/**
	 * Reset all steps to inactive.
	 */
	let allSteps = document.querySelectorAll('.plausible-analytics-wizard-step');
	let activeSteps = document.querySelectorAll('.plausible-analytics-wizard-active-step');
	let completedSteps = document.querySelectorAll('.plausible-analytics-wizard-completed-step');

	for (var i = 0; i < allSteps.length; i++) {
		allSteps[i].classList.remove('hidden');
	}

	for (var i = 0; i < activeSteps.length; i++) {
		activeSteps[i].classList += ' hidden';
	}

	for (var i = 0; i < completedSteps.length; i++) {
		completedSteps[i].classList += ' hidden';
	}

	/**
	 * Mark current step as active.
	 */
	let currentStep = document.getElementById('active-step-' + hash);
	let inactiveCurrentStep = document.getElementById('step-' + hash);

	currentStep.classList.remove('hidden');
	inactiveCurrentStep.classList += ' hidden';

	/**
	 * Mark steps as completed.
	 *
	 * @type {string[]}
	 */
	let currentlyCompletedSteps = currentStep.dataset.completedSteps.split(',');

	/**
	 * Filter empty array elements.
	 * @type {string[]}
	 */
	currentlyCompletedSteps = currentlyCompletedSteps.filter(n => n);

	if (currentlyCompletedSteps.length < 1) {
		return;
	}

	currentlyCompletedSteps.forEach(function (step) {
		let completedStep = document.getElementById('completed-step-' + step);
		let inactiveStep = document.getElementById('step-' + step);

		completedStep.classList.remove('hidden');
		inactiveStep.classList += ' hidden';
	});
}
