<?php

define('CACHE_DIR', './cache');
define('CACHE_LIFETIME', 60);
define('COINMARKETCAP_TICKER_API', 'https://api.coinmarketcap.com/v1/ticker/?limit=0&convert=JPY');

function get_from_cache_or_remote($cache, $remote) {
    $cache_path = CACHE_DIR . '/' . $cache;

    if (!file_exists(CACHE_DIR)) {
        mkdir(CACHE_DIR, 0777, true);
    }

    if (!file_exists($cache_path) || time() - filemtime($cache_path) > CACHE_LIFETIME) {
        if (!file_exists($cache_path)) {
            $fp = fopen($cache_path, 'w');
        } else {
            $fp = fopen($cache_path, 'r+');
        }

        if (flock($fp, LOCK_EX | LOCK_NB)) {
            $contents = @file_get_contents($remote);

            if ($contents) {
                ftruncate($fp, 0);
                fwrite($fp, $contents);
                fflush($fp);
            }

            flock($fp, LOCK_UN);
        }

        fclose($fp);
    }

    return file_get_contents($cache_path);
}

function fetch_coinmarketcap_ticker_api() {
    return get_from_cache_or_remote('coinmarketcap.json', COINMARKETCAP_TICKER_API);
}

$data = fetch_coinmarketcap_ticker_api();

if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && ($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest')) {
    echo $data;
    exit;
}
?>
<!doctype html>
<html lang="ja">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" id="viewport" content="width=device-width">
    <title>幻想計算機</title>
    <meta name="description" content="暗号通貨（仮想通貨）の幻想総資産を日本円でわかりやすく管理">
    <link type="text/css" rel="stylesheet" href="https://cdn.firebase.com/libs/firebaseui/3.1.1/firebaseui.css" />
    <link rel="stylesheet" href="https://unpkg.com/blaze@3.6.3/dist/blaze.min.css">
    <style>
        html, body {
            height: 100%;
        }

        body {
            margin: 0;
            padding: 0;
            -webkit-text-size-adjust: 100%;
        }

        .storage-selection-area {
            text-align: center;
        }

        .auth-area {
            text-align: center;
        }
    </style>
<?php
    include('./firebase-config.php');
?>
    <script src="https://cdn.firebase.com/libs/firebaseui/3.1.1/firebaseui.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/riot@3.8/riot+compiler.min.js"></script>
    <script src="js/fetch.js"></script>
    <script src="js/session-manager.js"></script>
    <script src="js/utils.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function(event) {
            var ui = new firebaseui.auth.AuthUI(firebase.auth());

            ui.start('#firebaseui-auth-container', {
                signInSuccessUrl: document.location.href,
                signInOptions: [
                    firebase.auth.GoogleAuthProvider.PROVIDER_ID
                ],
            });
        });
        var signOutButton = document.createElement("button");
        signOutButton.textContent = 'サインアウト';
        signOutButton.classList.add('c-button', 'c-button--ghost', 'u-xsmall');
        signOutButton.addEventListener('click', function(event) {
            firebase.auth().signOut();
        });
        firebase.auth().onAuthStateChanged(function(user) {
            var signOutArea = document.getElementById('firebase-auth-sign-out');
            if (user) {
                // User is signed in.
                user.getIdToken().then(function(accessToken) {
                    document.getElementById('firebase-auth-message').textContent = user.email + 'でログイン中です';
                });
                if (! signOutArea.contains(signOutButton)) {
                    signOutArea.appendChild(signOutButton);
                }
            } else {
                // User is signed out.
                document.getElementById('firebase-auth-message').textContent = 'ログインしていません';
                if (signOutArea.contains(signOutButton)) {
                    signOutArea.removeChild(signOutButton);
                }
            }
        });
    </script>
</head>
<body>

    <app></app>

    <div class="storage-selection-area">
        <h3>ストレージ変更</h3>
        <button type="button" class="c-button c-button--ghost u-xsmall" id="use-local-storage">オフライン</button>
        <button type="button" class="c-button c-button--ghost u-xsmall" id="use-firebase">オンライン</button>
        <h3>データコピー</h3>
        <button type="button" class="c-button c-button--ghost u-xsmall" id="copy-offline-to-online">オフライン→オンライン</button>
        <button type="button" class="c-button c-button--ghost u-xsmall" id="copy-online-to-offline">オンライン→オフライン</button>
    </div>

    <div class="auth-area">
        <h3>ログイン</h3>
        <div id="firebase-auth-message"></div>
        <div id="firebase-auth-sign-out"></div>
        <div id="firebaseui-auth-container"></div>
    </div>

    <script src="tag/modal.html" type="riot/tag"></script>
    <script src="tag/app.html" type="riot/tag"></script>

    <script>
        (function () {
            var dispatcher = riot.observable();
            var data = <?= $data ?>;
            var sessionManager = new SessionManager();

            var setStorage = function (storageType) {
                if (storageType === 'firebase') {
                    var unsubscribe = firebase.auth().onAuthStateChanged(function (user) {
                        if (user) {
                            sessionManager.setStorage(new Firestore('portfolios', user.uid));
                            unsubscribe();
                        }
                    });
                } else {
                    sessionManager.setStorage(new LocalStorage());
                }
            };

            riot.mount('app', {
                initial_data: data,
                dispatcher: dispatcher,
                sessionManager: sessionManager
            });

            // マウントを待つためちょっと遅らせる
            setTimeout(function () {
                new LocalStorage().getItem('storage_type', function (storageType) {
                    setStorage(storageType);
                });
            }, 100);

            document.getElementById('use-local-storage').addEventListener('click', function (event) {
                setStorage('localStorage');
                new LocalStorage().setItem('storage_type', 'localStorage');
            });
            document.getElementById('use-firebase').addEventListener('click', function (event) {
                setStorage('firebase');
                new LocalStorage().setItem('storage_type', 'firebase');
            });
            document.getElementById('copy-offline-to-online').addEventListener('click', function (event) {
                if (! confirm('オンラインのデータは削除されます。よろしいですか？')) {
                    return;
                }
                var unsubscribe = firebase.auth().onAuthStateChanged(function (user) {
                    if (user) {
                        var localMan = new SessionManager();
                        var storeMan = new SessionManager();
                        localMan.setStorage(new LocalStorage());
                        storeMan.setStorage(new Firestore('portfolios', user.uid));
                        localMan.getSession(function (session) {
                            storeMan.saveSession(session.portfolio);
                        });
                        unsubscribe();
                        alert('移行完了しました。オフラインモードに切り替えます。');
                        setStorage('localStorage');
                        new LocalStorage().setItem('storage_type', 'localStorage');
                    }
                });
            });
            document.getElementById('copy-online-to-offline').addEventListener('click', function (event) {
                if (! confirm('オフラインのデータは削除されます。よろしいですか？')) {
                    return;
                }
                var unsubscribe = firebase.auth().onAuthStateChanged(function (user) {
                    if (user) {
                        var localMan = new SessionManager();
                        var storeMan = new SessionManager();
                        localMan.setStorage(new LocalStorage());
                        storeMan.setStorage(new Firestore('portfolios', user.uid));
                        storeMan.getSession(function (session) {
                            localMan.saveSession(session.portfolio);
                        });
                        unsubscribe();
                        alert('移行完了しました。オンラインモードに切り替えます。');
                        setStorage('firebase');
                        new LocalStorage().setItem('storage_type', 'firebase');
                    }
                });
            });
        })();
    </script>
</body>
</html>
