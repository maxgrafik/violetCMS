/**!
 * violetCMS – AJAX
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

define(["auth", "utils"], function(auth, ux) {
    "use strict";

    function getCORS(url, success) {
        const xhr = new XMLHttpRequest();
        xhr.open("GET", url);
        xhr.onload = success;
        xhr.send();
    }

    function get(url, success, error) {

        const accessToken = auth.getAccessToken();
        if (!accessToken) {
            auth.reset();
        }

        const xhr = new XMLHttpRequest();
        xhr.open("GET", "../violet/api.php"+url);
        xhr.onreadystatechange = function() {
            if (xhr.readyState > 3) {
                if (xhr.status === 200) {
                    if (success && typeof success === "function") {
                        try {
                            success(JSON.parse(xhr.responseText));
                        } catch(e) {
                            success(null);
                        }
                    }
                } else if (xhr.status === 401) {
                    auth.reset();
                } else if (xhr.status === 403) {
                    ux.notify("Forbidden", "error");
                } else if (error && typeof error === "function") {
                    error(xhr.responseText);
                } else {
                    ux.notify(xhr.responseText || "Unknown Error", "error");
                }
            }
        };
        xhr.ontimeout = function() {
            ux.notify("Timeout", "error");
        };

        xhr.setRequestHeader("Authorization", "Bearer " + accessToken);
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.send();
    }

    function post(url, data, success, error) {

        const accessToken = auth.getAccessToken();
        if (!accessToken) {
            auth.reset();
        }

        const xhr = new XMLHttpRequest();
        xhr.open("POST", "../violet/api.php"+url);
        xhr.onreadystatechange = function() {
            if (xhr.readyState > 3) {
                if (xhr.status === 200) {
                    if (success && typeof success === "function") {
                        try {
                            success(JSON.parse(xhr.responseText));
                        } catch(e) {
                            success(null);
                        }
                    }
                } else if (xhr.status === 401) {
                    auth.reset();
                } else if (xhr.status === 403) {
                    ux.notify("Forbidden", "error");
                } else if (error && typeof error === "function") {
                    error(xhr.responseText);
                } else {
                    ux.notify(xhr.responseText || "Unknown Error", "error");
                }
            }
        };
        xhr.ontimeout = function() {
            ux.notify("Timeout", "error");
        };

        xhr.setRequestHeader("Authorization", "Bearer " + accessToken);
        xhr.setRequestHeader("Content-Type", "application/json; charset=utf-8");
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.send(data);
    }

    function upload(target, data, progress, success, error) {

        const accessToken = auth.getAccessToken();
        if (!accessToken) {
            auth.reset();
        }

        const formData = new FormData();
        formData.set("directory", data.directory);

        /* eslint-disable-next-line no-cond-assign */
        for (let i = 0, file; file = data.fileList[i]; i++) {
            formData.append("uploads[]", file, file.fileName);
        }

        const xhr = new XMLHttpRequest();
        if (progress && typeof progress === "function") {
            xhr.upload.onprogress = function (event) {
                if (event.lengthComputable) {
                    progress(Math.round((event.loaded * 100) / event.total));
                }
            };
        }
        xhr.open("POST", "../violet/api.php?q=upload&target="+target);
        xhr.onreadystatechange = function() {
            if (xhr.readyState > 3) {
                if (xhr.status === 200) {
                    if (success && typeof success === "function") {
                        try {
                            success(JSON.parse(xhr.responseText));
                        } catch(e) {
                            success(null);
                        }
                    }
                } else if (xhr.status === 401) {
                    auth.reset();
                } else if (xhr.status === 403) {
                    ux.notify("Forbidden", "error");
                } else if (error && typeof error === "function") {
                    error(xhr.responseText);
                } else {
                    ux.notify(xhr.responseText || "Unknown Error", "error");
                }
            }
        };
        xhr.ontimeout = function() {
            ux.notify("Timeout", "error");
        };

        xhr.setRequestHeader("Authorization", "Bearer " + accessToken);
        // Don't set Content-Type = multipart/form-data ... it lacks the boundary
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.send(formData);
    }

    function login(credentials, success, error) {

        const xhr = new XMLHttpRequest();
        xhr.open("POST", "../violet/api.php?q=login");
        xhr.onreadystatechange = function() {
            if (xhr.readyState > 3) {
                if (xhr.status === 200 && xhr.responseText) {

                    let data = null;
                    try {
                        data = JSON.parse(xhr.responseText);
                    } catch(e) {
                        error && error();
                        return;
                    }

                    const accessToken = data["access_token"] || null;
                    const refreshToken = data["refresh_token"] || null;

                    if (auth.isToken(accessToken) && auth.isToken(refreshToken)) {
                        const payload = auth.getPayload(accessToken);
                        if (payload) {
                            auth.setAccessToken(accessToken);
                            auth.setRefreshToken(refreshToken);
                            success && success(payload);
                        } else {
                            error && error();
                        }
                    } else {
                        error && error();
                    }
                } else {
                    error && error();
                }
            }
        };
        xhr.setRequestHeader("Content-Type", "application/json; charset=utf-8");
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.send(credentials);
    }

    function refresh(success, error) {

        const refreshToken = auth.getRefreshToken();
        if (!refreshToken) {
            error && error();
            return;
        }

        const xhr = new XMLHttpRequest();
        xhr.open("POST", "../violet/api.php?q=refresh");
        xhr.onreadystatechange = function() {
            if (xhr.readyState > 3) {
                if (xhr.status === 200 && xhr.responseText) {

                    let data = null;
                    try {
                        data = JSON.parse(xhr.responseText);
                    } catch(e) {
                        error && error();
                        return;
                    }

                    const accessToken = data["access_token"] || null;
                    const refreshToken = data["refresh_token"] || null;

                    if (auth.isToken(accessToken) && auth.isToken(refreshToken)) {
                        const payload = auth.getPayload(accessToken);
                        if (payload) {
                            auth.setAccessToken(accessToken);
                            auth.setRefreshToken(refreshToken);
                            success && success(payload);
                        } else {
                            error && error();
                        }
                    } else {
                        error && error();
                    }
                } else {
                    error && error();
                }
            }
        };
        xhr.setRequestHeader("Authorization", "Bearer " + refreshToken);
        xhr.setRequestHeader("Content-Type", "application/json; charset=utf-8");
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.send();
    }

    function logout() {
        post("?q=logout", null, function() {
            auth.reset();
        });
    }

    return {
        login   : login,
        refresh : refresh,
        logout  : logout,
        get     : get,
        post    : post,
        getCORS : getCORS,
        upload  : upload
    };

});
