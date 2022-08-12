/**!
 * violetCMS – Admin Entry Point
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

require.config({
    baseUrl: "js/vendor",
    paths: {
        knockout:   "knockout-3.5.1",
        sortable:   "sortable-1.10.2",
        app:        "../app/",
        components: "../app/components",
        dialog    : "../app/dialogs"
    },
    packages: [{
        name: "cm",
        location: "codemirror-5.65.7",
        main: "lib/codemirror"
    }]
});

require(["domReady!", "ajax", "utils"], function(doc, ajax, ux) {
    "use strict";

    ajax.refresh(init, login);

    function login() {
        document.getElementById("loginForm").onsubmit = function(event) {

            event.preventDefault();
            document.activeElement.blur();

            const email = document.getElementById("inputEmail");
            const pass = document.getElementById("inputPass");
            const credentials = JSON.stringify({email: email.value, pass: pass.value});

            ajax.login(credentials, init, function() {
                ux.shakeModal();
            });
        };

        ux.showModal("login", false);
    }

    function init(JWTpayload) {
        const dlg = document.getElementById("login");
        dlg.parentNode.removeChild(dlg);

        require(["knockout"], function(ko) {
            ko.components.register("admin", { require: "app/admin" });
            ko.applyBindings({Access: JWTpayload.access});
        });
    }

});
