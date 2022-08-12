/**!
 * violetCMS – JWT Handling
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

define(["base64"], function(base64) {
    "use strict";

    let refreshTimer = null;

    function getToken(keyName) {

        const token = sessionStorage.getItem(keyName);
        if (!token) {
            return null;
        }

        const payload = getPayload(token);
        if (!payload || !payload["exp"]) {
            sessionStorage.removeItem(keyName);
            return null;
        }

        const expires = new Date(payload["exp"]*1000);
        const current = Date.now();

        if (expires < current) {
            sessionStorage.removeItem(keyName);
            return null;
        }

        return token;
    }

    function getAccessToken() {
        return getToken("accessToken");
    }

    function getRefreshToken() {
        return getToken("refreshToken");
    }


    function setToken(keyName, token) {
        sessionStorage.setItem(keyName, token);
    }

    function setAccessToken(token) {
        setToken("accessToken", token);
        startRefreshTimer(token);
    }

    function setRefreshToken(token) {
        setToken("refreshToken", token);
    }


    function reset() {
        refreshTimer && clearTimeout(refreshTimer);
        sessionStorage.clear();
        window.location.hash = "";
        window.location.reload();
    }


    function isToken(data) {
        if (data) {
            try {
                const arr = data.split(".");
                if (arr.length !== 3) {
                    return false;
                }
                const header = JSON.parse(base64.UrlDecode(arr[0]));
                return (header["typ"] === "JWT");
            } catch (e) {
                return false;
            }
        }
        return false;
    }

    function getPayload(token) {
        if (token) {
            try {
                const arr = token.split(".");
                return JSON.parse(base64.UrlDecode(arr[1]));
            } catch(e) {
                return null;
            }
        }
        return null;
    }

    function startRefreshTimer(token) {

        refreshTimer && clearTimeout(refreshTimer);

        const payload = getPayload(token);
        if (payload["exp"]) {
            const expires = new Date(payload["exp"]*1000);
            const current = Date.now();
            const remainingTime = (expires-current)-(60*1000);

            if (remainingTime < 0) {
                reset();
                return;
            }

            refreshTimer = setTimeout(function() {
                require(["ajax"], function(ajax) {
                    ajax.refresh(null, reset);
                });
            }, remainingTime);

        } else {
            reset();
        }
    }

    return {
        getAccessToken  : getAccessToken,
        getRefreshToken : getRefreshToken,
        setAccessToken  : setAccessToken,
        setRefreshToken : setRefreshToken,
        isToken         : isToken,
        getPayload      : getPayload,
        reset           : reset
    };

});
