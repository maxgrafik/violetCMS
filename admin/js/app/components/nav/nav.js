/**!
 * violetCMS – Navigation
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

define(["knockout", "grapnel", "utils", "text!components/components.json"], function(ko, Grapnel, ux, componentData) {
    "use strict";

    /**
     * Handles the navigation of the admin backend by setting the
     * view and request observables in the admin component which
     * dynamically loads components.
     * All components also have access to the config via params.config
     *
     * @param {object}     params         The params object
     * @param {object}     params.root    The root ViewModel (Access, User, Config)
     * @param {observable} params.view    The view observable from admin
     * @param {observable} params.request The request observable from admin
     */

    function Navigation(params) {

        const self = this;

        self.User = params.root.User;
        self.Access = params.root.Access;

        self.Components = ko.observableArray([]);
        self.Router = new Grapnel({ pushState: false, hashBang: true });

        // register only components the user is allowed to access
        const data = JSON.parse(componentData);
        ko.utils.arrayForEach(data.components, function(component) {
            if (self.Access.includes(component.name) || !component.reqAuth) {
                self.Components.push(component);
            }
        });

        ko.utils.objectForEach(self.Components(), function(index, component) {
            if (!ko.components.isRegistered(component.name)) {
                ko.components.register(component.name, { require: component.src });
            }
            self.Router.add(component.route, function(req) {
                params.view(component.name);
                params.request(req.params);
            });
        });

        self.selectMenuItem = function(menuItem) {
            const el = document.querySelector("main > *");
            const currentVM = ko.dataFor(el);

            if (Object.prototype.hasOwnProperty.call(currentVM, "hasUnsavedChanges")) {
                if (currentVM.hasUnsavedChanges() === true) {
                    currentVM.confirmClose({
                        next: function() {
                            self.Router.navigate(menuItem.route);
                        }
                    });
                    return;
                }
            }

            self.Router.navigate(menuItem.route);
        };

        self.UserPrefs = ko.pureComputed(function() {
            return ko.utils.arrayFirst(self.Components(), function(component) {
                return component.name === "Prefs";
            });
        }, self);

        self.UserMenu = ko.pureComputed(function() {
            return ko.utils.arrayFilter(self.Components(), function(component) {
                return component.menu === "User";
            });
        }, self);

        self.AdminMenu = ko.pureComputed(function() {
            return ko.utils.arrayFilter(self.Components(), function(component) {
                return component.menu === "Admin";
            });
        }, self);

        self.activeMenuItem = ko.pureComputed(function() {
            return self.Components().find(item => item.name === params.view());
        }, self);

        ux.createMobileMenu();
    }

    Navigation.prototype.logout = function() {
        this.logout();
    };

    Navigation.prototype.dispose = function() {
        const self = this;
        self.Router = null;
    };

    return {
        viewModel: Navigation,
        template: { require: "text!components/nav/nav.html" }
    };

});
