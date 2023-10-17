import { Controller } from '@hotwired/stimulus';

export default class extends Controller {

    disable(name){
        console.log(name)
        document.getElementById(`off${name}`).classList.add('border-2','border-black','rounded-md');
        document.getElementById(`on${name}`).classList.remove('border-2','border-black','rounded-md');
        document.getElementById(`offIcon${name}`).classList.remove('invisible');
        document.getElementById(`onIcon${name}`).classList.add('invisible');
        document.getElementById("warning").classList.add('invisible');

        //ADD LOGIC TO DISABLE LABEL AND DESCRIPTION
    }
    enable(name){
        console.log(name)
        document.getElementById(`on${name}`).classList.add('border-2','border-black','rounded-md');
        document.getElementById(`off${name}`).classList.remove('border-2','border-black','rounded-md');
        document.getElementById(`onIcon${name}`).classList.remove('invisible');
        document.getElementById(`offIcon${name}`).classList.add('invisible');
        document.getElementById("warning").classList.remove('invisible');

        //ADD LOGIC TO ENABLE LABEL AND DESCRIPTION
    }
    connect() {
        document.addEventListener('DOMContentLoaded', () => {
            // Iterate over settings to populate the arrays
            /* LOAD INITIAL VALUE FROM USER SETTINGS */
            this.enable("CAPPORT");
            //Description Animation
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
            //event listeners to Buttons    
            document.getElementById("onCAPPORT").addEventListener('click', () => {
                this.enable('CAPPORT')})
            document.getElementById("offCAPPORT").addEventListener('click', () => {
                this.disable('CAPPORT')})
            
        });
    }
}