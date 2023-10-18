import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	connect() {
		const sidebarBtnElement = document.getElementById('sidebarButton');
		const sidebarElement = document.getElementById('sidebar');
		const dropdownButton = document.getElementById('adminActionsDropdownButton');
		const optionsSidebarButton = document.getElementById('optionsDropdownButton');
		const customCards = document.getElementsByName('customCards');

		const alertElement = document.getElementById('alert-2');
		if (alertElement) {
			alertElement.addEventListener('animationend', () => {
				alertElement.classList.add('hidden');
			});
		}

		sidebarBtnElement.addEventListener('click', () => {
			sidebarBtnElement.classList.toggle('open');
			sidebarElement.classList.toggle('hidden');
		});


		const mediaQuery = window.matchMedia('(max-width: 768px)');

		// Function to handle changes in resolution
		function handleResolutionChange(mediaQuery) {
			if (mediaQuery.matches) {
				sidebarElement.classList.add('hidden');
				sidebarElement.classList.add('absolute');
				sidebarElement.classList.remove('relative');
				dropdownButton.classList.remove('relative');
				dropdownButton.classList.add('hidden');
				optionsSidebarButton.classList.remove('hidden');
				optionsSidebarButton.classList.add('relative');

				customCards.forEach((description_target, index) => {
					customCards[index].classList.remove('hover:-translate-y-1');
				});

			} else {
				sidebarElement.classList.remove('hidden');
				sidebarElement.classList.remove('absolute');
				sidebarElement.classList.add('relative');
				dropdownButton.classList.remove('hidden');
				dropdownButton.classList.add('relative');
				optionsSidebarButton.classList.remove('relative');
				optionsSidebarButton.classList.add('hidden');
				customCards.forEach((description_target, index) => {
					customCards[index].classList.add('hover:-translate-y-1');
				});
			}
		}

		// Initial check on a page load
		handleResolutionChange(mediaQuery);

		// Listen for resolution changes
		mediaQuery.addListener(handleResolutionChange);


		function initializeDropdown_AdminActions(buttonId, menuId) {
			const dropdownButton = document.getElementById(buttonId);
			const dropdownMenu = document.getElementById(menuId);

			dropdownButton.addEventListener('click', () => {
				if (dropdownMenu.style.display === 'block') {
					dropdownMenu.style.display = 'none';
					dropdownButton.classList.remove('bg-veryDarkButton', 'text-white');
				} else {
					dropdownMenu.style.display = 'block';
					dropdownButton.classList.add('bg-veryDarkButton', 'text-white');
				}
			});

			// Close the dropdown when clicking outside it
			document.addEventListener('click', (event) => {
				if (!dropdownButton.contains(event.target) && !dropdownMenu.contains(event.target)) {
					dropdownMenu.style.display = 'none';
					dropdownButton.classList.remove('bg-veryDarkButton', 'text-white');
				}
			});
		}

		initializeDropdown_AdminActions('adminActionsDropdownButton', 'adminActionsDropdown');


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

			// Close the dropdown when clicking outside it
			document.addEventListener('click', (event) => {
				if (!dropdownButtonSelect.contains(event.target) && !dropdownMenuSelect.contains(event.target)) {
					dropdownMenuSelect.style.display = 'none';
				}
			});
		}

		initializeDropdown_Select('PortalDropdownButton', 'PortalDropDown');
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
	}
}
