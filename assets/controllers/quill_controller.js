import { Controller } from "@hotwired/stimulus"
import Quill from "quill"

import "quill/dist/quill.snow.css"

export default class extends Controller {
    static targets = ["editor", "input"]

    connect() {
        this.quill = new Quill(this.editorTarget, {
            theme: "snow",
            placeholder: "Type potatoes here pls :3...",
            modules: {
                toolbar: [
                    [{ header: [1, 2, false] }],
                    ["bold", "italic", "underline"],
                    ["link", "blockquote", "code-block", "image"],
                    [{ list: "ordered" }, { list: "bullet" }]
                ]
            }
        })

        // Load initial value from textarea into Quill
        if (this.inputTarget.value) {
            this.quill.root.innerHTML = this.inputTarget.value
        }

        // Sync changes back to hidden textarea
        this.quill.on("text-change", () => {
            this.inputTarget.value = this.quill.root.innerHTML
        })
    }
}
