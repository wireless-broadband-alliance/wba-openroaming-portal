import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["content", "tab"];

    connect() {
        const defaultOs =
            this.data.get("defaultOs") || (this.hasContentTarget ? this.contentTargets[0].dataset.os : null);
        console.log(defaultOs);
        if (defaultOs) this.showTab(defaultOs);
    }

    activate(event) {
        event.preventDefault();
        const os = event.currentTarget.dataset.os;
        this.showTab(os);
    }

    showTab(os) {
        const activeClasses = ["bg-white", "shadow-md", "rounded-t-lg", "border-b-2", "border-[#8AB742]", "-mb-[2px]"];
        const inactiveClasses = [
            "text-gray-400",
            "hover:text-black",
            "bg-transparent",
            "border-b-2",
            "border-transparent",
        ];

        // Toggle content visibility
        this.contentTargets.forEach((el) => el.classList.toggle("hidden", el.dataset.os !== os));

        // Toggle tab styles
        this.tabTargets.forEach((btn) => {
            const isActive = btn.dataset.os === os;

            btn.classList.remove(...(isActive ? inactiveClasses : activeClasses));
            btn.classList.add(...(isActive ? activeClasses : inactiveClasses));
        });
    }
}
