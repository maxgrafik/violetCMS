/**!
 * violetCMS – User Prefs
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

define(["knockout", "knockout-mapping", "ajax", "text!app/i18n/lang.json"], function(ko, koMapping, ajax, lang) {
    "use strict";

    /**
     * User preferences
     *
     * @param {object}     params         The params object
     * @param {object}     params.user    The user object ($root.User)
     * @param {object}     params.config  The config object ($root.Config)
     * @param {observable} params.request The request observable from admin
     */

    function Prefs(params) {

        const self = this;


        /* ----- VIEW MODELS ----- */

        self.User = params.user;

        self.Languages = koMapping.fromJS(JSON.parse(lang).languages);


        /* ----- COMPUTED ----- */

        self.LanguageSelect = ko.pureComputed(function() {
            const options = [];
            ko.utils.arrayForEach(self.Languages(), function(language) {
                options.push({ title: language.name(), value: language.code() });
            });
            return options;
        }, self);

    }

    Prefs.prototype.save = function() {
        const self = this;
        const mapping = {
            "include": ["password"]
        };
        ajax.post("?q=userprefs", koMapping.toJSON(self.User, mapping), function(response) {
            if (response) {
                const status = response.Userprefs;
                if (status && status["success"] === "must re-authenticate") {
                    require(["auth"], function(auth) {
                        auth.reset();
                    });
                }
            }
        });
    };

    Prefs.prototype.dispose = function() {};

    return {
        viewModel: Prefs,
        template: { require: "text!components/prefs/prefs.html" }
    };

});
