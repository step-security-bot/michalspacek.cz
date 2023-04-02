document.addEventListener('DOMContentLoaded', function () {
	const dateElement = document.querySelector('#termin a[href="#prihlaska"]');
	if (dateElement) {
		dateElement.addEventListener('click', function () {
			document.querySelector('#frm-application-trainingId').value = this.dataset.id;
		});
	}

	const countryElement = document.querySelector('#frm-application-country');
	const companyIdElement = document.querySelector('#frm-application-companyId');
	const APPLICATION = APPLICATION || {};
	APPLICATION.hideLoadControls = function () {
		document.querySelectorAll('#loadDataControls span').forEach(function (item) {
			item.classList.add('hidden');
		});
	};
	APPLICATION.showLoadControls = function (selector) {
		document.querySelectorAll(selector).forEach(function (item) {
			item.classList.remove('hidden');
		});
	};
	APPLICATION.loadData = function (event) {
		event.preventDefault();
		if (!countryElement || countryElement.value === '' || companyIdElement.value.replace(/ /g, '') === '') {
			alert(document.querySelector('#errorCountryCompanyMissing').innerText);
			return;
		}
		APPLICATION.hideLoadControls();
		APPLICATION.showLoadControls('#loadDataWait');
		const loadDataElement = document.querySelector('#loadData')
		if (!loadDataElement) {
			return;
		}

		const url = new URL(loadDataElement.dataset.url);
		url.searchParams.set('country', countryElement ? countryElement.value : '');
		url.searchParams.set('companyId', companyIdElement ? companyIdElement.value.replace(/ /g, '') : '');
		const controller = new AbortController();
		const timeoutId = setTimeout(() => controller.abort(), 10000);
		fetch(url.toString(), {signal: controller.signal})
			.then((response) => {
				clearTimeout(timeoutId);
				if (!response.ok) {
					throw new Error('Network response not ok');
				}
				return response.json()
			})
			.then((data) => {
				APPLICATION.hideLoadControls();
				APPLICATION.showLoadControls('#loadDataAgain');
				if (data.status === 200) {
					['companyId', 'companyTaxId', 'company', 'street', 'city', 'zip', 'country'].forEach(function (value) {
						const companyElement = document.querySelector('#company');
						if (companyElement) {
							companyElement.querySelector('#frm-application-' + value).value = data[value];
						}
					});
				} else if (data.status === 400) {
					APPLICATION.showLoadControls('#loadDataNotFound');
				} else {
					APPLICATION.showLoadControls('#loadDataError');
				}
			})
			.catch((error) => {
				APPLICATION.hideLoadControls();
				APPLICATION.showLoadControls('#loadDataAgain, #loadDataError');
				console.error('🐶⚾ fetch error:', error);
			});
	};
	document.querySelectorAll('#loadData a, #loadDataAgain a').forEach(function (item) {
		item.addEventListener('click', APPLICATION.loadData);
	});
	if (companyIdElement) {
		companyIdElement.addEventListener('keypress', function (e) {
			if (e.which === 13) {
				APPLICATION.loadData(e);
			}
		});
	}
	const loadDataDisabledElement = document.querySelector('#loadDataDisabled');
	if (loadDataDisabledElement) {
		loadDataDisabledElement.classList.add('hidden');
	}
	APPLICATION.hideLoadControls();
	APPLICATION.showLoadControls('#loadDataControls, #loadData');
	APPLICATION.changeLabels = function () {
		document.querySelectorAll('#frm-application label').forEach(function (item) {
			if (countryElement) {
				let label = item.dataset[countryElement.value];
				if (label) {
					item.innerText = label;
				}
			}
		});
	};
	if (countryElement) {
		countryElement.addEventListener('change', APPLICATION.changeLabels);
	}
	APPLICATION.changeLabels();

	const columnContentElement = document.querySelector('.column-content');
	if (columnContentElement && window.location.hash) {
		const columnContentHighlightElement = columnContentElement.querySelector(window.location.hash);
		if (columnContentHighlightElement) {
			columnContentHighlightElement.classList.add('highlight');
		}
	}

	const highlightElement = document.querySelector('.column-content .highlight');
	if (highlightElement) {
		highlightElement.scrollIntoView({
			'behavior': 'smooth',
		});
	}
});
