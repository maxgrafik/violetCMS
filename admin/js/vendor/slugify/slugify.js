/**!
 * violetCMS – Slugify
 *
 * Simplyfied version of slugify by
 *
 * @author Simeon Velichkov <simeonvelichkov@gmail.com>
 * @url https://github.com/simov/slugify
 * @license MIT
 */

define(["text!./charmap.json"], function(c) {
    "use strict";

    const charMap = JSON.parse(c);

    function Slugify(string) {

        if (typeof string !== "string") {
            return null;
        }

        const slug = string.split("").reduce(function (result, ch) {
            return result + (charMap[ch] || ch).replace(/[^\w\s$_-]/g, "");
        }, "").trim().replace(/[-\s]+/g, "-");

        return slug.toLowerCase();
    }

    return Slugify;
});
