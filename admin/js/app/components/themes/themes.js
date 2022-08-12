/**!
 * violetCMS – Theme Management
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

define(["knockout"], function(ko) {
    "use strict";

    /**
     * Manage Themes, maybe also HTML/CSS/JS source code
     *
     * @param {object}     params         The params object
     * @param {object}     params.user    The user object ($root.User)
     * @param {object}     params.config  The config object ($root.Config)
     * @param {observable} params.request The request observable from admin
     */

    function Themes() {

        const self = this;

    }

    Themes.prototype.dispose = function() {};

    return {
        viewModel: Themes,
        template: { require: "text!components/themes/themes.html" }
    };

});
