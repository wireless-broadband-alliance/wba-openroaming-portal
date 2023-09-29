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

		initializeDropdown('dropdownButton', 'dropdown');

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

		initializeDropdown_Select('selectDropdownButton', 'selectDropDown');
		initializeDropdown_Select('optionsDropdownButton', 'optionsDropdown');
	}
}
