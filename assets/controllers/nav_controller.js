import {Controller} from '@hotwired/stimulus';

export default class extends Controller {

	static targets = ["button", "container", "drodown", "hamburguer"];

	connect() {
		const alertElement = document.getElementById('alert-2');
		if (alertElement) {
			alertElement.addEventListener('animationend', () => {
				alertElement.classList.add('hidden');
			});
		}

		function initializeDropdown_Select(buttonId, menuId) {
			const dropdownButtonSelect = document.getElementById(buttonId);
			const dropdownMenuSelect = document.getElementById(menuId);

			dropdownButtonSelect.addEventListener('click', () => {
				if (dropdownMenuSelect.style.display === 'block') {
					dropdownMenuSelect.style.display = 'none';
				} else {
					dropdownMenuSelect.style.display = 'block';
				}
			});
		}

		initializeDropdown_Select('PortalDropdownButton', 'PortalDropDown');
		initializeDropdown_Select('StatisticsDropdownButton', 'StatisticsDropDown');
		initializeDropdown_Select('optionsDropdownButton', 'optionsDropdown');

		function initializeDropdown_ActionItems(buttonName, menuName) {
			const dropdownButtonItems = document.getElementsByName(buttonName);
			const dropdownMenuItems = document.getElementsByName(menuName);

			// Loop through all elements with the given name and attach event listeners
			dropdownButtonItems.forEach((button, index) => {
				button.addEventListener('click', () => {
					const dropdownMenu = dropdownMenuItems[index];
					if (dropdownMenu.style.display === 'block') {
						dropdownMenu.style.display = 'none';
					} else {
						dropdownMenu.style.display = 'block';
					}
				});
			});

			// Close the dropdown when clicking outside it
			document.addEventListener('click', (event) => {
				dropdownButtonItems.forEach((button, index) => {
					const dropdownMenu = dropdownMenuItems[index];
					if (!button.contains(event.target) && !dropdownMenu.contains(event.target)) {
						dropdownMenu.style.display = 'none';
					}
				});
			});
		}

		initializeDropdown_ActionItems('itemsDropDownButton', 'itemsDropDown');
		initializeDropdown_ActionItems('widgetUserButton', 'widgetUser');

		function remove_element(elementId) {
			setTimeout(function () {
				let errorContainer = document.getElementById(elementId);
				if (errorContainer) {
					errorContainer.style.transition = 'opacity 0.5s';
					errorContainer.style.opacity = '0';
					setTimeout(function () {
						errorContainer.remove();
					}, 500); // Wait for the transition to finish and then remove the element
				}
			}, 5000); // set a time of five seconds and then remove the error
		}

		remove_element('errorDisplay');
		remove_element('successDisplay');

		document.addEventListener('DOMContentLoaded', function () {
			const deleteButtons = document.querySelectorAll('.delete-users-button');

			deleteButtons.forEach(button => {
				button.addEventListener('click', () => {
					const userId = button.getAttribute('data-user-id');
					const userUuid = button.getAttribute('data-user-uuid');

					if (confirm(`Are you sure you want to delete the user with UUID ${userUuid}?`)) {
						document.getElementById('deleteForm' + userId).submit();
					}
				});
			});
		});

		function addSortingClickListener(columnName, activeSort, activeOrder) {
			let columnElement = document.getElementById(columnName + 'Column');

			if (columnElement) {
				columnElement.addEventListener('click', function () {
					// Retrieve the current URL
					let currentUrl = new URL(window.location.href);

					// Parse the URL parameters into an object
					let urlParams = new URLSearchParams(currentUrl.search);

					// Check if the 'sort' parameter is present and matches the current column
					if (urlParams.has('sort') && urlParams.get('sort') === columnName) {
						// If yes, toggle the sorting order
						let newOrder = urlParams.get('order') === 'asc' ? 'desc' : 'asc';
						urlParams.set('order', newOrder);
					} else {
						// If not, set the sorting parameters for the current column
						urlParams.set('sort', columnName);
						urlParams.set('order', 'asc');
					}

					// Update the URL with the new parameters
					currentUrl.search = '?' + urlParams.toString();

					// Redirect to the new URL
					window.location.href = currentUrl.toString();
				});

				// Add a class to indicate the active sort column
				if (activeSort === columnName) {
					columnElement.classList.add('active-sort');
					// Optionally, add a class for the active sort order (asc/desc)
					columnElement.classList.toggle('asc', activeOrder === 'asc');
					columnElement.classList.toggle('desc', activeOrder === 'desc');
				}
			}
		}

		addSortingClickListener('uuid', '{{ activeSort }}', '{{ activeOrder }}');
		addSortingClickListener('createdAt', '{{ activeSort }}', '{{ activeOrder }}');
	}

	toggle() {
		this.buttonTarget.classList.toggle('open');

		this.containerTarget.classList.toggle('hidden');

		// What is this used for?
		const bodyElement = document.body;
		if (this.containerTarget.classList.contains('hidden')) {
			bodyElement.classList.remove('overflow-hidden');
		} else {
			bodyElement.classList.add('overflow-hidden');
		}
	}
}
