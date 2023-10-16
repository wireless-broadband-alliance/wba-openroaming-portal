import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	connect() {
		const description_values = document.getElementsByName("description");
		const description_targets = document.getElementsByName("descriptionIcon");

		description_targets.forEach((description_target, index) => {
			let timeout; // Initialize a timeout variable

			description_target.addEventListener('mouseover', function handleMouseOver() {
				// Delay showing the description box for 500 milliseconds (adjust as needed)
				timeout = setTimeout(() => {
					description_values[index].classList.remove('hidden');
					description_values[index].classList.add('opacity-100');
				}, 500);
			});

			description_target.addEventListener('mouseout', function handleMouseOut() {
				clearTimeout(timeout); // Clear the timeout if the user moves the mouse out before the delay
				description_values[index].classList.add('hidden');
				description_values[index].classList.remove('opacity-100');
			});
		});

		const imageContainers = document.querySelectorAll('.image-container');
		imageContainers.forEach((container) => {
			const imagePreview = container.querySelector('[data-image-preview]');
			const imageInput = container.querySelector('[data-image-input]');
			// Add and event for every time the upload button is clicked and to change the current image in display

			imageInput.addEventListener('change', (e) => {
				this.handleImageChange(e, imagePreview);
			});
			const uploadButton = container.querySelector('.w-28.h-12');
			uploadButton.addEventListener('click', () => {
				imageInput.click();
			});
		});

		// Function to display the message
		function showMessageCapport() {
			const capportEnabledSelect = document.querySelector('[name="capport[CAPPORT_ENABLED]"]');
			const capportMessage = document.getElementById('capportMessage');

			if (capportEnabledSelect) {
				const toggleMessageState = () => {
					const capportEnabledValue = capportEnabledSelect.value;

					if (capportEnabledValue === 'true') {
						capportMessage.classList.remove('hidden');
					} else {
						capportMessage.classList.add('hidden');
					}
				};

				toggleMessageState();
				capportEnabledSelect.addEventListener('input', toggleMessageState);
			}
		}

		function DisableEnableCards(
			selectInput,
			firstInput,
			secondInput,
			firstCard,
			secondCard,
		) {
			if (selectInput) {
				const toggleInputState = () => {
					const capportEnabledValue = selectInput.value;

					if (capportEnabledValue === 'true') {
						firstInput.disabled = false;
						secondInput.disabled = false;

						firstInput.classList.remove('cursor-not-allowed');
						secondInput.classList.remove('cursor-not-allowed');
						firstCard.classList.add('bg-white');
						secondCard.classList.add('bg-white');
						firstCard.classList.remove('bg-disableCardsColor');
						secondCard.classList.remove('bg-disableCardsColor');
					} else {
						firstInput.disabled = true;
						secondInput.disabled = true;

						firstInput.classList.add('cursor-not-allowed');
						secondInput.classList.add('cursor-not-allowed');
						firstCard.classList.remove('bg-white');
						secondCard.classList.remove('bg-white');
						firstCard.classList.add('bg-disableCardsColor');
						secondCard.classList.add('bg-disableCardsColor');
					}
				};

				toggleInputState();
				selectInput.addEventListener('input', toggleInputState);
			}
		}

		// Call the functions
		DisableEnableCards(
			document.querySelector('[name="capport[CAPPORT_ENABLED]"]'),
			document.querySelector('[name="capport[CAPPORT_PORTAL_URL]"]'),
			document.querySelector('[name="capport[CAPPORT_VENUE_INFO_URL]"]'),
			document.getElementById('CAPPORT_PORTAL_URL'),
			document.getElementById('CAPPORT_VENUE_INFO_URL'),
		);
		showMessageCapport();
	}

	// Loads the uploaded image from the cache
	handleImageChange(e, imagePreview) {
		const file = e.target.files[0];

		if (file) {
			const reader = new FileReader();

			reader.onload = (event) => {
				imagePreview.src = event.target.result;
			};
			reader.readAsDataURL(file);
		}
	}
}
