$(document).ready(function () {
    setTimeout(function () {
        $('.scheme-container').remove();
        $('button.authorization__btn[aria-label="authorization button unlocked"]').replaceWith(`
      <button class="authorization__btn" aria-label="authorization button locked">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="locked" width="20" height="20" aria-hidden="true" focusable="false">
          <path d="M15.8 8H14V5.6C14 2.703 12.665 1 10 1 7.334 1 6 2.703 6 5.6V8H4c-.553 0-1 .646-1 1.199V17c0 .549.428 1.139.951 1.307l1.197.387C5.672 18.861 6.55 19 7.1 19h5.8c.549 0 1.428-.139 1.951-.307l1.196-.387c.524-.167.953-.757.953-1.306V9.199C17 8.646 16.352 8 15.8 8zM12 8H8V5.199C8 3.754 8.797 3 10 3c1.203 0 2 .754 2 2.199V8z"></path>
        </svg>
      </button>
    `);
    }, 1500);
});
