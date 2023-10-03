import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	connect() {
		function initializeDropdown(buttonId, menuId) {
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

		initializeDropdown('adminActionsDropdownButton', 'adminActionsDropdown');

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

		function initializeDropdown_Items(buttonId, menuId) {
			const dropdownButtonItems = document.getElementById(buttonId);
			const dropdownItems = document.getElementById(menuId);

			dropdownButtonItems.addEventListener('click', () => {
				if (dropdownItems.style.display === 'block') {
					dropdownItems.style.display = 'none';
					console.log('Items Menu OFF')
				} else {
					dropdownItems.style.display = 'block';
					console.log('Items Menu ON')
				}
			});

			// Close the dropdown when clicking outside it
			document.addEventListener('click', (event) => {
				if (!dropdownButtonItems.contains(event.target) && !dropdownItems.contains(event.target)) {
					dropdownItems.style.display = 'none';
				}
			});
		}

		initializeDropdown_Items('itemsDropDownButton', 'itemsDropDown');
	}
}
