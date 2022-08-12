/**!
 * violetCMS – Admin
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

/* global i18n */
define(["knockout", "knockout-mapping", "knockout-bindings", "utils", "ajax", "i18n"], function(ko, koMapping, koBindings, ux, ajax) {
    "use strict";

    /**
     * Main Admin Controller
     *
     * @param {object} root The root object containing the user's permissions
     */

    function Admin(root) {

        const self = this;


        /* --- GLOBALS --- */

        ko.language = ko.observable(null);
        ko.languageFormats = ko.observable(null);


        /* --- VIEW MODELS --- */

        root.User = {
            name     : ko.observable(null),
            title    : ko.observable(null),
            email    : ko.observable(null),
            language : ko.observable(null)
        };
        root.User.language.subscribe(self.loadLanguage);

        root.Config = {
            Website: {
                Title       : ko.observable(""),
                Description : ko.observable(""),
                Keywords    : ko.observable(""),
                Meta        : ko.observableArray([])
            },
            Routes: {
                Home        : ko.observable(""),
                HideInURL   : ko.observable(true),
                Redirect404 : ko.observable("/error")
            },
            Theme: ko.observable(""),
            Maintainance: ko.observable(false),
            Markdown: {
                AutoLineBreak : ko.observable(false),
                AutoURLLinks  : ko.observable(true),
                EscapeHTML    : ko.observable(true)
            }
        };
        root.Config.dirtyFlag = new ko.dirtyFlag(root.Config);


        /* --- NAVIGATION --- */

        self.currentView = ko.observable("Dashboard");
        self.currentRequest = ko.observable();

        ko.components.register("Navigation", { require: "components/nav/nav" });
        ko.components.register("Dashboard", { require: "components/dashboard/dashboard" });

        ux.show("header");
        ux.show("main");

        self.loadPrefs(root);
        self.loadConfig(root);

    }

    Admin.prototype.loadPrefs = function(root) {
        ajax.get("?q=userprefs", function(data) {
            if (data) {
                koMapping.fromJS(data.Userprefs, {}, root.User);
            }
        });
    };

    Admin.prototype.loadConfig = function(root) {
        ajax.get("?q=config", function(data) {
            if (data) {
                koMapping.fromJS(data.Config, {}, root.Config);
            }
        }, null);
    };

    Admin.prototype.loadLanguage = function(cc) {
        ajax.getCORS("js/app/i18n/"+cc+".json", function(data) {
            const target = data.currentTarget || data.target;
            if (target.status === 200) {
                const response = data.currentTarget.response || data.target.responseText;
                const i18nObj = JSON.parse(response);
                i18n.translator.add(i18nObj);
                ko.languageFormats(i18nObj.formats);
                ko.language(cc);
            }
        });
    };

    Admin.prototype.logout = function() {
        const self = this;
        self.currentView("Dashboard");
        self.currentRequest();
        ajax.logout();
    };

    Admin.prototype.dispose = function() {};

    return {
        viewModel: Admin,
        template: "<header data-bind=\"component:"
                + "{ name: 'Navigation', params: { root: $root, view: currentView, request: currentRequest } }\" hidden>"
                + "</header>"
                + "<main data-bind=\"component: { name: currentView, params: { user: $root.User, config: $root.Config, request: currentRequest } }\" hidden></main>"
    };

});
