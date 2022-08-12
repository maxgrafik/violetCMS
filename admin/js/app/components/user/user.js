/**!
 * violetCMS – User
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

define(["knockout", "knockout-mapping", "ajax", "text!app/i18n/lang.json", "text!components/components.json"], function(ko, koMapping, ajax, lang, components) {
    "use strict";

    /**
     * Get/Set User info
     *
     * @param {object}     params         The params object
     * @param {object}     params.user    The user object ($root.User)
     * @param {object}     params.config  The config object ($root.Config)
     * @param {observable} params.request The request observable from admin
     */

    function User(params) {

        const self = this;


        /* ----- VIEW MODELS ----- */

        self.Shortname = params.request()[0];

        self.User = {
            name     : ko.observable(null),
            title    : ko.observable(null),
            email    : ko.observable(null),
            language : ko.observable(null),
            enabled  : ko.observable(false),
            password : ko.observable(null),
            access   : ko.observableArray([])
        };

        self.Languages = koMapping.fromJS(JSON.parse(lang).languages);
        self.Components = koMapping.fromJS(JSON.parse(components).components);

        self.hasAccess = function(componentName) {
            return ko.pureComputed({
                read: function() {
                    return self.User.access().includes(componentName);
                },
                write: function(enabled) {
                    if (enabled) {
                        self.User.access.push(componentName);
                    } else {
                        self.User.access.remove(componentName);
                    }
                }
            });
        };


        /* ----- COMPUTED ----- */

        self.LanguageSelect = ko.pureComputed(function() {
            const options = [];
            ko.utils.arrayForEach(self.Languages(), function(language) {
                options.push({ title: language.name(), value: language.code() });
            });
            return options;
        }, self);


        /* ----- HELPERS ----- */

        self.confirmDelete = ko.observable(null);


        /* ----- REGISTER DIALOGS ----- */

        if (!ko.components.isRegistered("deleteUser")) {
            ko.components.register("deleteUser", {
                viewModel: { require: "dialog/dialog" },
                template: { require: "text!dialog/user-delete.html" }
            });
        }


        /* ----- INIT ----- */

        self.update();
    }

    User.prototype.update = function() {
        const self = this;
        ajax.get("?q=users&name="+self.Shortname, function(data) {
            if (data) {
                koMapping.fromJS(data.Users, {}, self.User);
            }
        });
    };

    User.prototype.save = function() {
        const self = this;
        const mapping = {
            "include": ["password"]
        };
        ajax.post("?q=users&action=update&name="+self.Shortname, koMapping.toJSON(self.User, mapping));
    };

    User.prototype.getConfirmDelete = function() {
        const self = this;
        self.confirmDelete(self.User);
    };

    User.prototype.deleteUser = function() {
        const self = this;
        ajax.post("?q=users&action=delete&name="+self.Shortname, null);
        window.location.hash = "!/users"; // not the smartest solution, but it works
    };

    User.prototype.dispose = function() {};

    return {
        viewModel: User,
        template: { require: "text!components/user/user.html" }
    };

});
