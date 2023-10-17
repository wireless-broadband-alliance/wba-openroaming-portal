import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	connect() {
		// JavaScript to display the correct icon when the page loads
		document.addEventListener("DOMContentLoaded", function () {
			const onLabel = document.getElementById("onLabel");
			const offLabel = document.getElementById("offLabel");
			const onCustomRadio = document.getElementById("onCustomRadio");
			const offCustomRadio = document.getElementById("offCustomRadio");

			// Check which radio button is selected
			const onRadio = document.querySelector('input[type="radio"][value="true"]');
			const offRadio = document.querySelector('input[type="radio"][value="false"]');

			if (onRadio.checked) {
				onCustomRadio.classList.remove("hidden");
				offCustomRadio.classList.add("hidden");
			} else if (offRadio.checked) {
				offCustomRadio.classList.remove("hidden");
				onCustomRadio.classList.add("hidden");
			}

			onLabel.addEventListener("click", function () {
				onCustomRadio.classList.remove("hidden");
				offCustomRadio.classList.add("hidden");
			});

			offLabel.addEventListener("click", function () {
				offCustomRadio.classList.remove("hidden");
				onCustomRadio.classList.add("hidden");
			});
		});


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

		// Function to display the capport message
		function showMessageCapport() {
			const capportRadioButtons = document.querySelectorAll('[name="capport[CAPPORT_ENABLED]"]');
			const capportMessage = document.getElementById('capportMessage');

			if (capportRadioButtons) {
				const toggleMessageState = () => {
					const capportEnabledValue = document.querySelector('[name="capport[CAPPORT_ENABLED]"]:checked').value;

					if (capportEnabledValue === 'true') {
						capportMessage.classList.remove('hidden');
					} else {
						capportMessage.classList.add('hidden');
					}
				};

				// Attach input event listener to all radio buttons
				capportRadioButtons.forEach(radioButton => {
					radioButton.addEventListener('input', toggleMessageState);
				});

				toggleMessageState(); // Call it initially to handle the default state
			}
		}

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
