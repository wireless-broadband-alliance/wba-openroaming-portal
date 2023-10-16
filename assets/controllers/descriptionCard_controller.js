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

		// Listen to changes in the select input of the capport form
// Function to display the message
		function toggleMessage() {
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

// Function to manage background colors and input disable actions
		function toggleBackgroundColorsAndInputState() {
			const capportEnabledSelect = document.querySelector('[name="capport[CAPPORT_ENABLED]"]');
			const capportPortalUrlInput = document.querySelector('[name="capport[CAPPORT_PORTAL_URL]"]');
			const capportVenueInfoUrlInput = document.querySelector('[name="capport[CAPPORT_VENUE_INFO_URL]"]');
			const capportPortalUrlCard = document.getElementById('CAPPORT_PORTAL_URL');
			const capportVenueInfoUrlCard = document.getElementById('CAPPORT_VENUE_INFO_URL');

			if (capportEnabledSelect) {
				const toggleInputState = () => {
					const capportEnabledValue = capportEnabledSelect.value;

					if (capportEnabledValue === 'true') {
						// Enable the input fields
						capportPortalUrlInput.disabled = false;
						capportVenueInfoUrlInput.disabled = false;
						// Remove the style classes indicating that the inputs are blocked
						capportPortalUrlInput.classList.remove('bg-gray-400', 'cursor-not-allowed');
						capportVenueInfoUrlInput.classList.remove('bg-gray-400', 'cursor-not-allowed');
						capportPortalUrlCard.classList.add('bg-white');
						capportVenueInfoUrlCard.classList.add('bg-red');
						capportPortalUrlCard.classList.remove('bg-disableCardsColor');
						capportVenueInfoUrlCard.classList.remove('bg-disableCardsColor');
					} else {
						capportPortalUrlInput.disabled = true;
						capportVenueInfoUrlInput.disabled = true;
						// Add style classes to indicate that the inputs are blocked and make the cards appear darker
						capportPortalUrlInput.classList.add('cursor-not-allowed', 'bg-gray-400');
						capportVenueInfoUrlInput.classList.add('cursor-not-allowed', 'bg-gray-400');
						capportPortalUrlCard.classList.remove('bg-white');
						capportVenueInfoUrlCard.classList.remove('bg-white');
						capportPortalUrlCard.classList.add('bg-disableCardsColor');
						capportVenueInfoUrlCard.classList.add('bg-disableCardsColor');
					}
				};

				toggleInputState();
				capportEnabledSelect.addEventListener('input', toggleInputState);
			}
		}

		// Call the functions
		toggleMessage();
		toggleBackgroundColorsAndInputState();
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
