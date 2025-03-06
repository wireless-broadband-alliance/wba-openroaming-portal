import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["codesBox"];

    print() {
        if (this.hasCodesBoxTarget) {
            const content = this.codesBoxTarget.innerHTML;
            const win = window.open('', '', 'height=500, width=800');
            win.document.write('<html><head><title>Print Codes</title>');
            win.document.write('<style><body {font-family:monospace; font-size: 16px; }</style>');
            win.document.write('</head><body>');
            win.document.write(content);
            win.document.write('</body></html>');
            win.document.close();
            win.print();
        } else {
            console.error("Element 'codesBox' not found!");
        }
    }
}