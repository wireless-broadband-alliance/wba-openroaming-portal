import {Controller} from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["preview"];

    connect() {
        super.connect();
    }

    update(event) {
        const file = event.target.files[0];

        if (file) {
            const reader = new FileReader();

            reader.onload = (event) => {
                this.previewTarget.src = event.target.result;
            };

            reader.readAsDataURL(file);
        }
    }
}
