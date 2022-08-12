/**!
 * violetCMS – Base64 Functions
 */

define(function() {
    "use strict";

    function UrlDecode(str) {
        let data = str.replace(/-/g, "+").replace(/_/g, "/");
        let pad = data.length % 4;
        if (pad) {
            if (pad === 1) {
                throw new Error("InvalidLengthError: Input base64url string is the wrong length to determine padding");
            }
            data += new Array(5-pad).join("=");
        }
        return Decode(data);
    }

    function Decode(str) {
        const bytes = Uint8Array.from(atob(str), c => c.charCodeAt(0));
        return new TextDecoder("utf-8").decode(bytes);
    }

    function UrlEncode(str) {
        const data = Encode(str);
        return data.replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
    }

    function Encode(str) {
        const bytes = new TextEncoder("utf-8").encode(str);
        return btoa(String.fromCharCode(...bytes));
    }

    return {
        UrlDecode : UrlDecode,
        UrlEncode : UrlEncode
    };
});
