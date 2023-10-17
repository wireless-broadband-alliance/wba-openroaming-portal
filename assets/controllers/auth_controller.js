import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["onSAML", "offSAML"]
    SAMLtoggle = true;

    disableSAML(){
        console.log("off")
        document.getElementById('offSAML').classList.add('border-2','border-black','rounded-md');
        document.getElementById('onSAML').classList.remove('border-2','border-black','rounded-md');
        document.getElementById('offSAMLicon').classList.remove('invisible');
        document.getElementById('onSAMLicon').classList.add('invisible');
        //this.onSAML.classList.remove('border-2')
        // border-black rounded-md")
        //SAML_On_Div.classList.remove('border-2')
        // border-black rounded-md")
    }
    enableSAML(){
        //.add('border-2','border-black','rounded-md'); - selected
        //nonselected - 'bg-gray-50', 'border-gray-300', 'text-gray-800'
        console.log("on")
        document.getElementById('onSAML').classList.add('border-2','border-black','rounded-md');
        document.getElementById('offSAML').classList.remove('border-2','border-black','rounded-md');
        document.getElementById('onSAMLicon').classList.remove('invisible');
        document.getElementById('offSAMLicon').classList.add('invisible');
        //SAML_On_Div = document.getElementById('SAML-On');
        //SAML_On_Div.classList.add('border-2')
        // border-black rounded-md")
        //this.offSAMLTarget.classList.add('border-2')

    }
    connect() {
        document.addEventListener('DOMContentLoaded', () => {

            const description_values = document.getElementsByName("description");
            const description_targets = document.getElementsByName("descriptionIcon");
            description_targets.forEach((description_target, index) => {
                let timeout; // Initialize a timeout variable
                description_target.addEventListener('mouseover', function handleMouseOver() {
                    console.log("in");
                    // Delay showing the description box for 500 milliseconds (adjust as needed)
                    timeout = setTimeout(() => {
                        description_values[index].classList.remove('hidden');
                        description_values[index].classList.add('opacity-100');
                    }, 500);
                });

                description_target.addEventListener('mouseout', function handleMouseOut() {
                    console.log("out");
                    clearTimeout(timeout); // Clear the timeout if the user moves the mouse out before the delay
                    description_values[index].classList.add('hidden');
                    description_values[index].classList.remove('opacity-100');
                });
            });
        
            const onSAMLdiv = document.getElementById('onSAML');
            const offSAMLdiv = document.getElementById('offSAML');

            onSAMLdiv.addEventListener('click', () => {
                this.enableSAML()})
            offSAMLdiv.addEventListener('click', () => {
                this.disableSAML()})

        });
        console.log("connect1")
        /* LOAD INITIAL VALUE FROM USER SETTINGS */
        if(this.SAMLtoggle)
            this.enableSAML();
        else
            this.disableSAML();
    }
}