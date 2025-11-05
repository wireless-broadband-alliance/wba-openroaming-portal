import { Controller } from '@hotwired/stimulus';
import Quill from 'quill';

import 'quill/dist/quill.snow.css';

export default class extends Controller {
  static targets = ['editor', 'input'];

  resizeEditor() {
    const windowHeight = window.innerHeight;
    const newHeight = Math.max(windowHeight * 0, 120); // 40% of viewport, min 120px
    this.editorTarget.style.height = `${newHeight}px`;

    // Also make the inner editor fill the container
    const editorContent = this.editorTarget.querySelector('.ql-editor');
    if (editorContent) {
      editorContent.style.minHeight = '0';
      editorContent.style.height = '100%';
    }
  }

  connect() {
    this.quill = new Quill(this.editorTarget, {
      theme: 'snow',
      modules: {
        toolbar: [
          [{ header: [1, 2, false] }],
          ['bold', 'italic', 'underline'],
          ['link', 'blockquote', 'code-block'],
          [{ list: 'ordered' }, { list: 'bullet' }],
        ],
      },
    });

    // Load initial value from hidden input
    if (this.inputTarget.value) {
      this.quill.root.innerHTML = this.inputTarget.value;
    }

    // Sync changes back to hidden input
    this.quill.on('text-change', () => {
      this.inputTarget.value = this.quill.root.innerHTML;
    });

    // Initial resize
    this.resizeEditor();

    // Resize on window resize
    window.addEventListener('resize', () => this.resizeEditor());
  }
}
