import { Controller } from "@hotwired/stimulus"
import Quill from "quill"

import "quill/dist/quill.snow.css"

export default class extends Controller {
    static targets = ["editor", "input"]

    resizeEditor() {
        const windowHeight = window.innerHeight
        // Set editor height to 40% of viewport height, minimum 120px
        this.editorTarget.style.height = `${Math.max(windowHeight * 0.4, 120)}px`
    }

    connect() {
        this.quill = new Quill(this.editorTarget, {
            theme: "snow",
            modules: {
                toolbar: [
                    [{ header: [1, 2, false] }],
                    ["bold", "italic", "underline"],
                    ["link", "blockquote", "code-block"],
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

        // Initial resize
        this.resizeEditor()
        window.addEventListener("resize", () => this.resizeEditor())
    }
}
