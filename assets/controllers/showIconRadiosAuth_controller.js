import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	connect() {
		// Show/hide icon on each radio button
		function initializeRadioButtons(onLabel, offLabel, onCustomRadio, offCustomRadio) {
			// Check which radio button is selected
			const onRadio = onLabel.parentElement.querySelector('input[type="radio"][value="true"]');
			const offRadio = offLabel.parentElement.querySelector('input[type="radio"][value="false"]');

			if (onRadio && offRadio && onCustomRadio && offCustomRadio) {
				if (onRadio.checked) {
					onCustomRadio.classList.remove("hidden");
					offCustomRadio.classList.add("hidden");
				} else if (offRadio.checked) {
					offCustomRadio.classList.remove("hidden");
					onCustomRadio.classList.add("hidden");
				}

				onRadio.addEventListener("change", function () {
					onCustomRadio.classList.remove("hidden");
					offCustomRadio.classList.add("hidden");
				});

				offRadio.addEventListener("change", function () {
					offCustomRadio.classList.remove("hidden");
					onCustomRadio.classList.add("hidden");
				});
			}
		}

		// Looks for the first element with the name declared
		document.addEventListener("DOMContentLoaded", function () {
			const radioSets = document.querySelectorAll('[name="Cards"]');

			radioSets.forEach(function (radioSet) {
				const onLabel = radioSet.querySelector('[name="onLabel"]');
				const offLabel = radioSet.querySelector('[name="offLabel"]');
				const onCustomRadio = radioSet.querySelector('[name="onCustomRadio"]');
				const offCustomRadio = radioSet.querySelector('[name="offCustomRadio"]');

				initializeRadioButtons(onLabel, offLabel, onCustomRadio, offCustomRadio);
			});
		});

		// Looks for the specific name second name element
		document.addEventListener("DOMContentLoaded", function () {
			const GoogleRadioSets = document.querySelectorAll('[name="GoogleCards"]');
			GoogleRadioSets.forEach(function (radioSet) {
				const onLabelGoogle = radioSet.querySelector('[name="onLabelGoogle"]');
				const offLabelGoogle = radioSet.querySelector('[name="offLabelGoogle"]');
				const onCustomRadioGoogle = radioSet.querySelector('[name="onCustomRadioGoogle"]');
				const offCustomRadioGoogle = radioSet.querySelector('[name="offCustomRadioGoogle"]');

				initializeRadioButtons(onLabelGoogle, offLabelGoogle, onCustomRadioGoogle, offCustomRadioGoogle);
			});
		});

		// Looks for the specific name third name element
		document.addEventListener("DOMContentLoaded", function () {
			const RegisterRadioSets = document.querySelectorAll('[name="RegisterCards"]');
			RegisterRadioSets.forEach(function (radioSet) {
				const onLabelRegister = radioSet.querySelector('[name="onLabelRegister"]');
				const offLabelRegister = radioSet.querySelector('[name="offLabelRegister"]');
				const onCustomRadioRegister = radioSet.querySelector('[name="onCustomRadioRegister"]');
				const offCustomRadioRegister = radioSet.querySelector('[name="offCustomRadioRegister"]');

				initializeRadioButtons(onLabelRegister, offLabelRegister, onCustomRadioRegister, offCustomRadioRegister);
			});
		});

		// Looks for the specific name fourth name element
		document.addEventListener("DOMContentLoaded", function () {
			const LoginRadioSets = document.querySelectorAll('[name="LoginCards"]');
			LoginRadioSets.forEach(function (radioSet) {
				const onLabelLogin = radioSet.querySelector('[name="onLabelLogin"]');
				const offLabelLogin = radioSet.querySelector('[name="offLabelLogin"]');
				const onCustomRadioLogin = radioSet.querySelector('[name="onCustomRadioLogin"]');
				const offCustomRadioLogin = radioSet.querySelector('[name="offCustomRadioLogin"]');

				initializeRadioButtons(onLabelLogin, offLabelLogin, onCustomRadioLogin, offCustomRadioLogin);
			});
		});

		document.addEventListener("DOMContentLoaded", function () {
			const SMSLoginRadioSets = document.querySelectorAll('[name="SMSLoginCards"]');
			SMSLoginRadioSets.forEach(function (radioSet) {
				const SMSonLabelLogin = radioSet.querySelector('[name="SMSonLabelLogin"]');
				const SMSoffLabelLogin = radioSet.querySelector('[name="SMSoffLabelLogin"]');
				const SMSonCustomRadioLogin = radioSet.querySelector('[name="SMSonCustomRadioLogin"]');
				const SMSoffCustomRadioLogin = radioSet.querySelector('[name="SMSoffCustomRadioLogin"]');

				initializeRadioButtons(SMSonLabelLogin, SMSoffLabelLogin, SMSonCustomRadioLogin, SMSoffCustomRadioLogin);
			});
		});

		document.addEventListener("DOMContentLoaded", function () {
			const SMSRegisterRadioSets = document.querySelectorAll('[name="SMSRegisterCards"]');
			SMSRegisterRadioSets.forEach(function (radioSet) {
				const SMSonLabelRegister = radioSet.querySelector('[name="SMSonLabelRegister"]');
				const SMSoffLabelRegister = radioSet.querySelector('[name="SMSoffLabelRegister"]');
				const SMSonCustomRadioRegister = radioSet.querySelector('[name="SMSonCustomRadioRegister"]');
				const SMSoffCustomRadioRegister = radioSet.querySelector('[name="SMSoffCustomRadioRegister"]');

				initializeRadioButtons(SMSonLabelRegister, SMSoffLabelRegister, SMSonCustomRadioRegister, SMSoffCustomRadioRegister);
			});
		});
	}
}
