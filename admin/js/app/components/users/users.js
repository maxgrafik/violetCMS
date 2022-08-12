/**!
 * violetCMS – Users
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

define(["knockout", "knockout-mapping", "ajax", "slugify/slugify"], function(ko, koMapping, ajax, slugify) {
    "use strict";

    /**
     * List of user accounts
     *
     * @param {object}     params         The params object
     * @param {object}     params.user    The user object ($root.User)
     * @param {object}     params.config  The config object ($root.Config)
     * @param {observable} params.request The request observable from admin
     */

    function Users() {

        const self = this;


        /* ----- VIEW MODELS ----- */

        self.Users = ko.observableArray([]);

        self.User = function() {
            this.name      = ko.observable(null);
            this.email     = ko.observable(null);
            this.password  = ko.observable(null);
        };


        /* ----- COMPUTED ----- */

        self.UsersAlphabetical = ko.pureComputed(function() {
            return self.Users().sort(function(user1, user2) {
                const userName1 = user1.name() ? user1.name().toLowerCase() : "";
                const userName2 = user2.name() ? user2.name().toLowerCase() : "";
                return userName1 === userName2 ? 0 : (userName1 < userName2 ? -1 : 1);
            });
        }, self).extend({ deferred: true });


        /* ----- HELPERS ----- */

        self.newUser = ko.observable(null);


        /* ----- REGISTER DIALOGS ----- */

        if (!ko.components.isRegistered("newUser")) {
            ko.components.register("newUser", {
                viewModel: { require: "dialog/dialog" },
                template: { require: "text!dialog/user-new.html" }
            });
        }


        /* ----- INIT ----- */

        self.update();
    }

    Users.prototype.update = function() {
        const self = this;
        ajax.get("?q=users", function(data) {
            if (data) {
                koMapping.fromJS(data.Users, {}, self.Users);
            }
        });
    };

    Users.prototype.addUser = function() {
        const self = this;
        self.newUser(new self.User());
    };

    Users.prototype.createUser = function(data) {
        const self = this;
        const Shortname = slugify(data().name());
        ajax.post("?q=users&action=create&name="+Shortname, koMapping.toJSON(data), function() {
            self.update();
        });
    };

    Users.prototype.dispose = function() {};

    return {
        viewModel: Users,
        template: { require: "text!components/users/users.html" }
    };

});
