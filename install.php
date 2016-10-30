<?php
    /**
     * File: Installer
     * Builds the site configuration, creates the SQL tables, and configures the .htaccess file.
     */

    header("Content-Type: text/html; charset=UTF-8");

    define('DEBUG',          true);
    define('CHYRP_VERSION',  "2016.04");
    define('CHYRP_CODENAME', "Iago");
    define('CACHE_TWIG',     false);
    define('JAVASCRIPT',     false);
    define('MAIN',           false);
    define('ADMIN',          false);
    define('AJAX',           false);
    define('XML_RPC',        false);
    define('UPGRADING',      false);
    define('INSTALLING',     true);
    define('TESTER',         isset($_SERVER['HTTP_USER_AGENT']) and $_SERVER['HTTP_USER_AGENT'] == "TESTER");
    define('DIR',            DIRECTORY_SEPARATOR);
    define('MAIN_DIR',       dirname(__FILE__));
    define('INCLUDES_DIR',   MAIN_DIR.DIR."includes");
    define('CACHES_DIR',     INCLUDES_DIR.DIR."caches");
    define('USE_OB',         true);
    define('USE_ZLIB',       false);

    # Constant: JSON_PRETTY_PRINT
    # Define a safe value to avoid warnings pre-5.4.
    if (!defined('JSON_PRETTY_PRINT'))
        define('JSON_PRETTY_PRINT', 0);

    # Constant: JSON_UNESCAPED_SLASHES
    # Define a safe value to avoid warnings pre-5.4.
    if (!defined('JSON_UNESCAPED_SLASHES'))
        define('JSON_UNESCAPED_SLASHES', 0);

    if (version_compare(PHP_VERSION, "5.3.2", "<"))
        exit("Chyrp Lite requires PHP 5.3.2 or greater. Installation cannot continue.");

    # Make sure E_STRICT is on so Chyrp remains errorless.
    error_reporting(E_ALL | E_STRICT);

    ob_start();

    # File: Error
    # Error handling functions.
    require_once INCLUDES_DIR.DIR."error.php";

    # File: Helpers
    # Various functions used throughout the codebase.
    require_once INCLUDES_DIR.DIR."helpers.php";

    # File: Config
    # See Also:
    #     <Config>
    require_once INCLUDES_DIR.DIR."class".DIR."Config.php";

    # File: SQL
    # See Also:
    #     <SQL>
    require INCLUDES_DIR.DIR."class".DIR."SQL.php";

    # File: Model
    # See Also:
    #     <Model>
    require_once INCLUDES_DIR.DIR."class".DIR."Model.php";

    # File: User
    # See Also:
    #     <User>
    require_once INCLUDES_DIR.DIR."model".DIR."User.php";

    # Register our autoloader.
    spl_autoload_register("autoload");

    # Boolean: $installed
    # Has Chyrp Lite been installed?
    $installed = false;

    # Prepare the Config interface.
    $config = Config::current();

    # Prepare the SQL interface.
    $sql = SQL::current();

    # Set the timezone for time calculations (Atlantic/Reykjavik is 0 offset).
    $timezone = isset($_POST['timezone']) ? $_POST['timezone'] : oneof(ini_get("date.timezone"), "Atlantic/Reykjavik") ;
    set_timezone($timezone);

    # Ask PHP for the default locale and try to load an appropriate translator.
    if (class_exists("Locale")) {
        $locale = Locale::getDefault();
        $language = Locale::getPrimaryLanguage($locale)."_".Locale::getRegion($locale);
        load_translator("chyrp", INCLUDES_DIR.DIR."locale".DIR.$language.".mo");
    }

    # Sanitize all input depending on magic_quotes_gpc's enabled status.
    sanitize_input($_GET);
    sanitize_input($_POST);
    sanitize_input($_COOKIE);
    sanitize_input($_REQUEST);

    # Where are we?
    $url = str_ireplace("/install.php", "", self_url());
    $url_path = oneof(parse_url($url, PHP_URL_PATH), "/");

    # Already installed?
    if (file_exists(INCLUDES_DIR.DIR."config.json.php"))
        if ($sql->connect(true) and !empty($config->url) and $sql->count("users"))
            redirect($config->url);

    # Test if we can write to MAIN_DIR (needed for the .htaccess file).
    if (!is_writable(MAIN_DIR))
        $errors[] = __("Please CHMOD or CHOWN the installation directory to make it writable.");

    # Test if we can write to INCLUDES_DIR (needed for config.json.php).
    if (!is_writable(INCLUDES_DIR))
        $errors[] = __("Please CHMOD or CHOWN the <em>includes</em> directory to make it writable.");

    /**
     * Function: posted
     * Echoes a $_POST value if set, otherwise echoes the fallback value.
     *
     * Parameters:
     *     $index - The named index to test in the $_POST array.
     *     $fallback - The value to echo if the $_POST value is not set.
     */
    function posted($index, $fallback = "") {
        echo isset($_POST[$index]) ? fix($_POST[$index], true) : $fallback ;
    }

    /**
     * Function: selected
     * Echoes " selected" HTML attribute if the supplied values are equal.
     *
     * Parameters:
     *     $val1 - Compare this value...
     *     $val2 - ... with this value.
     */
    function selected($val1, $val2) {
        if ($val1 == $val2)
                echo " selected";
    }

    #---------------------------------------------
    # Output Starts
    #---------------------------------------------
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo __("Chyrp Lite Installer"); ?></title>
        <meta name="viewport" content="width = 520, user-scalable = no">
        <style type="text/css">
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('./fonts/OpenSans-Regular.woff') format('woff');
                font-weight: normal;
                font-style: normal;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('./fonts/OpenSans-Semibold.woff') format('woff');
                font-weight: bold;
                font-style: normal;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('./fonts/OpenSans-Italic.woff') format('woff');
                font-weight: normal;
                font-style: italic;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('./fonts/OpenSans-SemiboldItalic.woff') format('woff');
                font-weight: bold;
                font-style: italic;
            }
            @font-face {
                font-family: 'Hack webfont';
                src: url('./fonts/Hack-Regular.woff') format('woff');
                font-weight: normal;
                font-style: normal;
            }
            @font-face {
                font-family: 'Hack webfont';
                src: url('./fonts/Hack-Bold.woff') format('woff');
                font-weight: bold;
                font-style: normal;
            }
            @font-face {
                font-family: 'Hack webfont';
                src: url('./fonts/Hack-Italic.woff') format('woff');
                font-weight: normal;
                font-style: italic;
            }
            @font-face {
                font-family: 'Hack webfont';
                src: url('./fonts/Hack-BoldItalic.woff') format('woff');
                font-weight: bold;
                font-style: italic;
            }
            *::selection {
                color: #ffffff;
                background-color: #4f4f4f;
            }
            html {
                font-size: 14px;
            }
            html, body, ul, ol, li,
            h1, h2, h3, h4, h5, h6,
            form, fieldset, a, p {
                margin: 0em;
                padding: 0em;
                border: 0em;
            }
            body {
                font-size: 1rem;
                font-family: "Open Sans webfont", sans-serif;
                line-height: 1.5;
                color: #4a4747;
                background: #efefef;
                padding: 0em 0em 5em;
            }
            h1 {
                font-size: 2em;
                text-align: center;
                font-weight: bold;
                margin: 0.5em 0em;
                line-height: 1;
            }
            h1:first-child {
                margin-top: 0em;
            }
            h2 {
                font-size: 1.25em;
                text-align: center;
                font-weight: bold;
                margin: 0.75em 0em;
            }
            input, textarea, select {
                font-family: inherit;
                font-size: inherit;
                font-weight: inherit;
            }
            input[type="text"],
            input[type="email"],
            input[type="url"],
            input[type="number"],
            input[type="password"],
            textarea {
                font-size: 1.25em;
                padding: 0.2em;
                border: 1px solid #dfdfdf;
                background-color: #ffffff;
                background-image: -webkit-linear-gradient(top, rgba(0,0,0,0) 0%, rgba(0,0,0,0) 100%);
            }
            input[type="text"],
            input[type="email"],
            input[type="url"],
            input[type="number"],
            input[type="password"],
            textarea,
            select {
                box-sizing: border-box;
                width: 100%;
                margin: 0em;
            }
            input[type="text"]:focus,
            input[type="email"]:focus,
            input[type="url"]:focus,
            input[type="number"]:focus,
            input[type="password"]:focus,
            textarea:focus {
                border-color: #1e57ba;
                outline: none;
            }
            input[type="text"].error,
            input[type="email"].error,
            input[type="url"].error,
            input[type="number"].error,
            input[type="password"].error,
            textarea.error {
                background-color: #faebe4;
                border: 1px solid #d51800;
            }
            input[type="password"].strong {
                background-color: #ebfae4;
                border: 1px solid #189100;
            }
            form hr {
                border: none;
                clear: both;
                border-top: 1px solid #ddd;
                margin: 2em 0em;
            }
            form p {
                padding-bottom: 1em;
            }
            .sub {
                font-size: .8em;
                color: #777;
                font-weight: normal;
            }
            .sub.inline {
                float: left;
                margin-top: -1.5em !important;
            }
            .window {
                width: 30em;
                background: #fff;
                padding: 2em;
                margin: 2em auto 0em auto;
                border-radius: 2em;
            }
            .window:first-child {
                margin-top: 5em;
            }
            code {
                font-family: "Hack webfont", monospace;
                font-style: normal;
                word-wrap: break-word;
                background-color: #efefef;
                padding: 2px;
                color: #4f4f4f;
            }
            strong {
                font-weight: normal;
                color: #f00;
            }
            ul, ol {
                margin: 0em 0em 2em 2em;
                list-style-position: outside;
            }
            label {
                display: block;
                font-weight: bold;
                line-height: 1.5;
            }
            .footer {
                color: #777;
                margin-top: 1em;
                font-size: .9em;
                text-align: center;
            }
            a:link, a:visited {
                color: #4a4747;
            }
            a:hover, a:focus {
                color: #1e57ba;
            }
            pre.pane {
                height: 15em;
                overflow-y: auto;
                margin: 1em -2em 1em -2em;
                padding: 2em;
                background: #4a4747;
                color: #fff;
            }
            pre.pane:empty {
                display: none;
            }
            pre.pane:empty + h1 {
                margin-top: 0em;
            }
            span.yay {
                color: #76b362;
            }
            span.boo {
                color: #d94c4c;
            }
            a.big,
            button {
                box-sizing: border-box;
                display: block;
                clear: both;
                font-family: inherit;
                font-size: 1.25em;
                text-align: center;
                color: #4a4747;
                text-decoration: none;
                line-height: 1.25;
                margin: 0.75em 0em;
                padding: 0.4em 0.6em;
                background-color: #f2fbff;
                border: 1px solid #b8cdd9;
                border-radius: 0.3em;
                cursor: pointer;
                text-decoration: none;
            }
            button {
                width: 100%;
            }
            a.big:last-child,
            button:last-child {
                margin-bottom: 0em;
            }
            a.big:hover,
            button:hover,
            a.big:focus,
            button:focus,
            a.big:active,
            button:active {
                border-color: #1e57ba;
                outline: none;
            }
            p {
                margin-bottom: 1em;
            }
            aside {
                margin-bottom: 1em;
                padding: 0.5em 1em;
                border: 1px solid #e5d7a1;
                border-radius: 0.25em;
                background-color: #fffecd;
            }
        </style>
        <script src="includes/common.js" type="text/javascript" charset="UTF-8"></script>
        <script type="text/javascript">
            function toggle_adapter() {
                if ($("#adapter").val() == "sqlite") {
                    $("#database_sub, #database_aside").fadeIn("fast");
                    $("#host_field, #username_field, #password_field, #prefix_field").fadeOut("fast");
                } else {
                    $("#database_sub, #database_aside").fadeOut("fast");
                    $("#host_field, #username_field, #password_field, #prefix_field").fadeIn("fast");
                }
            }
            $(function() {
                $("#adapter").change(toggle_adapter).trigger("change");

                $("#password1").keyup(function(e) {
                    if (passwordStrength($(this).val()) > 99)
                        $(this).addClass("strong");
                    else
                        $(this).removeClass("strong");
                });

                $("#password1, #password2").keyup(function(e) {
                    if ($("#password1").val() != "" && $("#password1").val() != $("#password2").val())
                        $("#password2").addClass("error");
                    else
                        $("#password2").removeClass("error");
                });

                $("#installer").on("submit", function(e) {
                    if ($("#password1").val() != $("#password2").val()) {
                        e.preventDefault();
                        alert('<?php echo __("Passwords do not match."); ?>');
                    }
                });

                $("#email").keyup(function(e) {
                    if ($(this).val() != "" && !isEmail($(this).val()))
                        $(this).addClass("error");
                    else
                        $(this).removeClass("error");
                });
            });
        </script>
    </head>
    <body>
        <div class="window">
            <pre role="status" class="pane"><?php

    #---------------------------------------------
    # Installation Starts
    #---------------------------------------------

    if (!empty($_POST)) {
        if (empty($_POST['database']))
            $errors[] = __("Database cannot be blank.");

        if (empty($_POST['name']))
            $errors[] = __("Please enter a name for your website.");

        if (!isset($_POST['timezone']))
            $errors[] = __("Time zone cannot be blank.");

        if (empty($_POST['login']))
            $errors[] = __("Please enter a username for your account.");

        if (empty($_POST['password1']) or empty($_POST['password2']))
            $errors[] = __("Passwords cannot be blank.");
        elseif ($_POST['password1'] != $_POST['password2'])
            $errors[] = __("Passwords do not match.");

        if (empty($_POST['email']))
            $errors[] = __("Email address cannot be blank.");
        elseif (!is_email($_POST['email']))
            $errors[] = __("Invalid email address.");

        if (!class_exists("MySQLi") and !class_exists("PDO"))
            $errors[] = __("MySQLi or PDO is required for database access.");

        if (empty($errors) and $_POST['adapter'] == "sqlite")
            if ($realpath = realpath(dirname($_POST['database'])))
                $_POST['database'] = $realpath.DIR.basename($_POST['database']);
            else
                $errors[] = __("Could not determine the absolute path to the SQLite database.");

        if (empty($errors) and $_POST['adapter'] == "sqlite")
            if (!is_writable(dirname($_POST['database'])))
                $errors[] = __("Please make the SQLite database writable by the server.");

        if (empty($errors) and $_POST['adapter'] == "mysql")
            if (empty($_POST['username']) or empty($_POST['password']))
                $errors[] = __("Please enter a username and password for the MySQL database.");

        if (empty($errors)) {
            # Build the SQL settings based on user input.
            $settings = ($_POST['adapter'] == "sqlite") ?
                array("host"     => "",
                      "username" => "",
                      "password" => "",
                      "database" => $_POST['database'],
                      "prefix"   => "",
                      "adapter"  => $_POST['adapter']) :
                array("host"     => $_POST['host'],
                      "username" => $_POST['username'],
                      "password" => $_POST['password'],
                      "database" => $_POST['database'],
                      "prefix"   => $_POST['prefix'],
                      "adapter"  => $_POST['adapter']) ;

            # Configure the SQL interface.
            $sql = SQL::current($settings);

            # Test the database connection.
            if (!$sql->connect(true))
                $errors[] = __("Could not connect to the database:")."\n".fix($sql->error);
        }

        if (empty($errors)) {
            # Configure the .htaccess file.
            if (htaccess_conf($url_path) === false)
                $errors[] = __("Clean URLs will not be available because the <em>.htaccess</em> file is not writable.");

            # Build the configuration file.
            $set = array($config->set("sql", $settings),
                         $config->set("name", $_POST['name']),
                         $config->set("description", $_POST['description']),
                         $config->set("url", rtrim($url, "/")),
                         $config->set("chyrp_url", rtrim($url, "/")),
                         $config->set("email", $_POST['email']),
                         $config->set("timezone", $_POST['timezone']),
                         $config->set("locale", "en_US"),
                         $config->set("cookies_notification", true),
                         $config->set("check_updates", true),
                         $config->set("check_updates_last", 0),
                         $config->set("theme", "blossom"),
                         $config->set("posts_per_page", 5),
                         $config->set("admin_per_page", 25),
                         $config->set("feed_items", 20),
                         $config->set("feed_url", ""),
                         $config->set("uploads_path", DIR."uploads".DIR),
                         $config->set("uploads_limit", 10),
                         $config->set("send_pingbacks", false),
                         $config->set("enable_xmlrpc", true),
                         $config->set("enable_ajax", true),
                         $config->set("enable_emoji", true),
                         $config->set("enable_markdown", true),
                         $config->set("can_register", false),
                         $config->set("email_activation", false),
                         $config->set("email_correspondence", true),
                         $config->set("enable_captcha", false),
                         $config->set("default_group", 0),
                         $config->set("guest_group", 0),
                         $config->set("clean_urls", false),
                         $config->set("enable_homepage", false),
                         $config->set("post_url", "(year)/(month)/(day)/(url)/"),
                         $config->set("enabled_modules", array()),
                         $config->set("enabled_feathers", array("text")),
                         $config->set("routes", array()),
                         $config->set("secure_hashkey", random(32)));

            if (in_array(false, $set))
                $errors[] = __("Could not write the configuration file.");

            # Reconnect to the database.
            $sql->connect();

            # Posts table.
            $sql->query("CREATE TABLE IF NOT EXISTS __posts (
                             id INTEGER PRIMARY KEY AUTO_INCREMENT,
                             feather VARCHAR(32) DEFAULT '',
                             clean VARCHAR(128) DEFAULT '',
                             url VARCHAR(128) DEFAULT '',
                             pinned BOOLEAN DEFAULT FALSE,
                             status VARCHAR(32) DEFAULT 'public',
                             user_id INTEGER DEFAULT 0,
                             created_at DATETIME DEFAULT NULL,
                             updated_at DATETIME DEFAULT NULL
                         ) DEFAULT CHARSET=utf8");

            # Post attributes table.
            $sql->query("CREATE TABLE IF NOT EXISTS __post_attributes (
                             post_id INTEGER NOT NULL,
                             name VARCHAR(100) DEFAULT '',
                             value LONGTEXT,
                             PRIMARY KEY (post_id, name)
                         ) DEFAULT CHARSET=utf8");

            # Pages table.
            $sql->query("CREATE TABLE IF NOT EXISTS __pages (
                             id INTEGER PRIMARY KEY AUTO_INCREMENT,
                             title VARCHAR(250) DEFAULT '',
                             body LONGTEXT,
                             public BOOLEAN DEFAULT '1',
                             show_in_list BOOLEAN DEFAULT '1',
                             list_order INTEGER DEFAULT 0,
                             clean VARCHAR(128) DEFAULT '',
                             url VARCHAR(128) DEFAULT '',
                             user_id INTEGER DEFAULT 0,
                             parent_id INTEGER DEFAULT 0,
                             created_at DATETIME DEFAULT NULL,
                             updated_at DATETIME DEFAULT NULL
                         ) DEFAULT CHARSET=utf8");

            # Users table.
            $sql->query("CREATE TABLE IF NOT EXISTS __users (
                             id INTEGER PRIMARY KEY AUTO_INCREMENT,
                             login VARCHAR(64) DEFAULT '',
                             password VARCHAR(128) DEFAULT '',
                             full_name VARCHAR(250) DEFAULT '',
                             email VARCHAR(128) DEFAULT '',
                             website VARCHAR(128) DEFAULT '',
                             group_id INTEGER DEFAULT 0,
                             approved BOOLEAN DEFAULT '1',
                             joined_at DATETIME DEFAULT NULL,
                             UNIQUE (login)
                         ) DEFAULT CHARSET=utf8");

            # Groups table.
            $sql->query("CREATE TABLE IF NOT EXISTS __groups (
                             id INTEGER PRIMARY KEY AUTO_INCREMENT,
                             name VARCHAR(100) DEFAULT '',
                             UNIQUE (name)
                         ) DEFAULT CHARSET=utf8");

            # Permissions table.
            $sql->query("CREATE TABLE IF NOT EXISTS __permissions (
                             id VARCHAR(100) DEFAULT '',
                             name VARCHAR(100) DEFAULT '',
                             group_id INTEGER DEFAULT 0,
                             PRIMARY KEY (id, group_id)
                         ) DEFAULT CHARSET=utf8");

            # Sessions table.
            $sql->query("CREATE TABLE IF NOT EXISTS __sessions (
                             id VARCHAR(40) DEFAULT '',
                             data LONGTEXT,
                             user_id INTEGER DEFAULT 0,
                             created_at DATETIME DEFAULT NULL,
                             updated_at DATETIME DEFAULT NULL,
                             PRIMARY KEY (id)
                         ) DEFAULT CHARSET=utf8");

            $names = array("change_settings" => "Change Settings",
                           "toggle_extensions" => "Toggle Extensions",
                           "view_site" => "View Site",
                           "view_private" => "View Private Posts",
                           "view_scheduled" => "View Scheduled Posts",
                           "view_draft" => "View Drafts",
                           "view_own_draft" => "View Own Drafts",
                           "add_post" => "Add Posts",
                           "add_draft" => "Add Drafts",
                           "edit_post" => "Edit Posts",
                           "edit_draft" => "Edit Drafts",
                           "edit_own_post" => "Edit Own Posts",
                           "edit_own_draft" => "Edit Own Drafts",
                           "delete_post" => "Delete Posts",
                           "delete_draft" => "Delete Drafts",
                           "delete_own_post" => "Delete Own Posts",
                           "delete_own_draft" => "Delete Own Drafts",
                           "view_page" => "View Pages",
                           "add_page" => "Add Pages",
                           "edit_page" => "Edit Pages",
                           "delete_page" => "Delete Pages",
                           "add_user" => "Add Users",
                           "edit_user" => "Edit Users",
                           "delete_user" => "Delete Users",
                           "add_group" => "Add Groups",
                           "edit_group" => "Edit Groups",
                           "delete_group" => "Delete Groups",
                           "export_content" => "Export Content");

            foreach ($names as $id => $name)
                $sql->replace("permissions",
                              array("id", "group_id"),
                              array("id" => $id,
                                    "name" => $name,
                                    "group_id" => 0));

            $groups = array("admin"  => array_keys($names),
                            "member" => array("view_site"),
                            "friend" => array("view_site", "view_private", "view_scheduled"),
                            "banned" => array(),
                            "guest"  => array("view_site"));

            # Insert the default groups (see above).
            $group_id = array();

            foreach ($groups as $name => $permissions) {
                $sql->replace("groups", "name", array("name" => ucfirst($name)));

                $group_id[$name] = $sql->latest("groups");

                foreach ($permissions as $permission)
                    $sql->replace("permissions",
                                  array("id", "group_id"),
                                  array("id" => $permission,
                                        "name" => $names[$permission],
                                        "group_id" => $group_id[$name]));
            }

            $config->set("default_group", $group_id["member"]);
            $config->set("guest_group", $group_id["guest"]);

            if (!$sql->select("users", "id", array("login" => $_POST['login']))->fetchColumn())
                $sql->insert("users",
                             array("login" => $_POST['login'],
                                   "password" => User::hashPassword($_POST['password1']),
                                   "email" => $_POST['email'],
                                   "website" => $config->url,
                                   "group_id" => $group_id["admin"],
                                   "approved" => true,
                                   "joined_at" => datetime()));

            if (password_strength($_POST['password1']) < 100)
                $errors[] = __("Please consider setting a stronger password for your account.");

            $installed = true;
        }
    }

    #---------------------------------------------
    # Installation Ends
    #---------------------------------------------

    foreach ($errors as $error)
        echo '<span role="alert">'.$error."</span>\n";

          ?></pre>
<?php if (!$installed): ?>
            <form action="install.php" method="post" accept-charset="UTF-8" id="installer">
                <h1><?php echo __("Database Setup"); ?></h1>
                <p id="adapter_field">
                    <label for="adapter"><?php echo __("Adapter"); ?></label>
                    <select name="adapter" id="adapter">
                        <?php if ((class_exists("PDO") and in_array("mysql", PDO::getAvailableDrivers())) or class_exists("MySQLi")): ?>
                        <option value="mysql"<?php selected("mysql", fallback($_POST['adapter'], "mysql")); ?>>MySQL</option>
                        <?php endif; ?>
                        <?php if (class_exists("PDO") and in_array("sqlite", PDO::getAvailableDrivers())): ?>
                        <option value="sqlite"<?php selected("sqlite", fallback($_POST['adapter'], "mysql")); ?>>SQLite</option>
                        <?php endif; ?>
                    </select>
                </p>
                <p id="host_field">
                    <label for="host"><?php echo __("Host"); ?> <span class="sub"><?php echo __("(usually ok as \"localhost\")"); ?></span></label>
                    <input type="text" name="host" value="<?php posted("host", (isset($_ENV['DATABASE_SERVER']) ? $_ENV['DATABASE_SERVER'] : "localhost")); ?>" id="host">
                </p>
                <p id="username_field">
                    <label for="username"><?php echo __("Username"); ?></label>
                    <input type="text" name="username" value="<?php posted("username"); ?>" id="username">
                </p>
                <p id="password_field">
                    <label for="password"><?php echo __("Password"); ?></label>
                    <input type="password" name="password" value="<?php posted("password"); ?>" id="password">
                </p>
                <p id="database_field">
                    <label for="database"><?php echo __("Database"); ?>
                        <span id="database_sub" class="sub">
                            <?php echo __("(absolute or relative path)"); ?>
                        </span>
                    </label>
                    <input type="text" name="database" value="<?php posted("database"); ?>" id="database">
                </p>
                <aside id="database_aside">
                    <?php echo __("Be sure to put your SQLite database outside the document root directory, otherwise visitors will be able to download it."); ?>
                </aside>
                <p id="prefix_field">
                    <label for="prefix"><?php echo __("Table Prefix"); ?> <span class="sub"><?php echo __("(optional)"); ?></span></label>
                    <input type="text" name="prefix" value="<?php posted("prefix"); ?>" id="prefix">
                </p>
                <hr>
                <h1><?php echo __("Website Setup"); ?></h1>
                <p id="name_field">
                    <label for="name"><?php echo __("Site Name"); ?></label>
                    <input type="text" name="name" value="<?php posted("name", __("My Awesome Site")); ?>" id="name">
                </p>
                <p id="description_field">
                    <label for="description"><?php echo __("Description"); ?></label>
                    <input type="text" name="description" value="<?php posted("description"); ?>" id="description">
                </p>
                <p id="timezone_field">
                    <label for="timezone"><?php echo __("What time is it?"); ?></label>
                    <select name="timezone" id="timezone">
                    <?php foreach (timezones() as $zone): ?>
                        <option value="<?php echo $zone["name"]; ?>"<?php selected($zone["name"], $timezone); ?>>
                            <?php echo when(__("%I:%M %p on %B %d, %Y"), $zone["now"], true); ?> &mdash;
                            <?php echo str_replace(array("_", "St "), array(" ", "St. "), $zone["name"]); ?>
                        </option>
                    <?php endforeach; ?>
                    </select>
                </p>
                <hr>
                <h1><?php echo __("Admin Account"); ?></h1>
                <p id="login_field">
                    <label for="login"><?php echo __("Username"); ?></label>
                    <input type="text" name="login" value="<?php posted("login", "Admin"); ?>" id="login">
                </p>
                <p id="password1_field">
                    <label for="password1"><?php echo __("Password"); ?></label>
                    <input type="password" name="password1" value="<?php posted("password1"); ?>" id="password1">
                </p>
                <p id="password2_field">
                    <label for="password2"><?php echo __("Password"); ?> <span class="sub"><?php echo __("(again)"); ?></span></label>
                    <input type="password" name="password2" value="<?php posted("password2"); ?>" id="password2">
                </p>
                <p id="email_field">
                    <label for="email"><?php echo __("Email Address"); ?></label>
                    <input type="email" name="email" value="<?php posted("email"); ?>" id="email">
                </p>
                <button type="submit"><?php echo __("Install me!"); ?></button>
            </form>
<?php else: ?>
            <h1><?php echo __("Chyrp Lite has been installed"); ?></h1>
            <h2><?php echo __("What now?"); ?></h2>
            <ol>
                <li><?php echo __("Delete <em>install.php</em>, you won't need it anymore."); ?></li>
            <?php if (!is_writable(CACHES_DIR)): ?>
                <li><?php echo _f("Please make <em>%s</em> writable by the server.", CACHES_DIR) ?></li>
            <?php endif; ?>
                <li><a href="https://github.com/xenocrat/chyrp-lite/wiki"><?php echo __("Learn more about Chyrp Lite."); ?></a></li>
            </ol>
            <a class="big" href="<?php echo $config->chyrp_url; ?>"><?php echo __("Take me to my site!"); ?></a>
<?php endif; ?>
        </div>
    </body>
</html>
