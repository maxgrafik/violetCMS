/**!
 * violetCMS – Backups
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

/* global i18n */
define(["knockout", "knockout-mapping", "ajax", "utils", "i18n"], function(ko, koMapping, ajax, ux) {
    "use strict";

    /**
     * Handles creating/deleting VioletCMS backups
     *
     * @param {object}     params         The params object
     * @param {object}     params.user    The user object ($root.User)
     * @param {object}     params.config  The config object ($root.Config)
     * @param {observable} params.request The request observable from admin
     */

    function Backups() {

        const self = this;


        /* ----- VIEW MODEL ----- */

        self.Backups = ko.observableArray();

        self.inProgress = ko.observable(false);


        /* ----- INIT ----- */

        self.update();

    }

    Backups.prototype.update = function() {
        const self = this;
        ajax.get("?q=backups", function(data) {
            if (data) {
                koMapping.fromJS(data.Backups, {}, self.Backups);
            } else {
                self.Backups([]);
            }
        }, null);
    };

    Backups.prototype.createBackup = function() {
        const self = this;

        if (self.inProgress()) { return; }

        self.inProgress(true);
        ajax.post("?q=backups&action=create", null, function() {
            self.inProgress(false);
            ux.notify(i18n("MsgBackupComplete"), "success");
            self.update();
        }, function(errMsg) {
            self.inProgress(false);
            ux.notify(errMsg);
        });
    };

    Backups.prototype.deleteBackup = function(data) {
        const self = this;
        ajax.post("?q=backups&action=delete&name="+data.name(), null, function() {
            self.update();
        }, null);
    };

    Backups.prototype.dispose = function() {};

    return {
        viewModel: Backups,
        template: { require: "text!components/backups/backups.html" }
    };

});
