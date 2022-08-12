document.addEventListener("DOMContentLoaded", function(){

    // mobile menu
    const menuTrigger = document.querySelector(".hamburger");
    if (menuTrigger) {
        menuTrigger.addEventListener("click", function(event) {
            event.preventDefault();
            event.stopPropagation();
            const mobileMenu = document.getElementsByTagName("nav")[0];
            if (menuTrigger.classList.contains("is-active")) {
                menuTrigger.classList.remove("is-active");
                mobileMenu.classList.remove("is-active");
            } else {
                menuTrigger.classList.add("is-active");
                mobileMenu.classList.add("is-active");
            }
            mobileMenu.addEventListener("click", function(e) {
                const anchor = e.target.closest("a");
                if (anchor) {
                    menuTrigger.classList.remove("is-active");
                    mobileMenu.classList.remove("is-active");
                }
            });
        });
    }

});
