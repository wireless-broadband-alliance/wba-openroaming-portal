import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["input", "button"];
    static values = {
        profileLimitDate: Number,
    };

    connect() {
        console.log(this.profileLimitDateValue);
    }

    updateDate() {
        this.inputTarget.value = this.profileLimitDateValue;
    }
}
