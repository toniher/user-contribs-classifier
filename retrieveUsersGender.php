<?php

require_once __DIR__ . '/vendor/autoload.php';

use Addwiki\Mediawiki\Api\Client\Action\Request\ActionRequest;
use Addwiki\Mediawiki\Api\Client\Auth\UserAndPassword;
use Addwiki\Mediawiki\Api\Client\MediaWiki;

// Detect commandline args
$conffile = 'config.json';
$userfile = null;

if (count($argv) > 1) {
    $conffile = $argv[1];
}

if (count($argv) > 2) {
    $userfile = $argv[2];
}

// Detect if files
if (! file_exists($conffile) && ! file_exists($userfile)) {
    die("Config file needed");
}

$confjson = json_decode(file_get_contents($conffile), 1);

$wikiconfig = null;

if (array_key_exists("wikipedia", $confjson)) {
    $wikiconfig = $confjson["wikipedia"];
}

$wpapi = null;

// Login
if (array_key_exists("user", $wikiconfig) && array_key_exists("password", $wikiconfig)) {
    $userAndPassword = new UserAndPassword($wikiconfig["user"], $wikiconfig["password"]);
    $wpapi = MediaWiki::newFromEndpoint($wikiconfig["url"], $userAndPassword);
}

processUserFile($userfile, $wpapi);

function processUserFile($userfile, $wpapi)
{

    echo "User\tGender\tBot\n";

    $userfileText = file_get_contents($userfile);

    $lines = explode("\n", $userfileText);

    $users = array();

    $count = 0;

    foreach ($lines as $line) {

        $count++;

        $user = rtrim($line);

        array_push($users, $user);

        if ($count >= 50) {

            getUsersGender($users, $wpapi);
            $count = 0;
            $users = array();
        }

    }

    getUsersGender($users, $wpapi);

}

function getUsersGender($users, $wpapi)
{

    $usersStr = implode("|", $users);
    $params = array();

    $params["list"] = "users";
    $params["ususers"] = $usersStr;
    $params["usprop"] = "gender|groups";

    $outcome = $wpapi->action()->request(ActionRequest::simplePost('query', $params));

    if (array_key_exists("query", $outcome)) {

        if (array_key_exists("users", $outcome["query"])) {

            foreach ($outcome["query"]["users"] as $user) {

                $output = "";

                if (array_key_exists("gender", $user) && array_key_exists("name", $user)) {

                    $output = $user["name"]."\t".$user["gender"]."\t";
                }

                $bot = 0;

                if (array_key_exists("groups", $user)) {

                    if (in_array("bot", $user["groups"])) {

                        $bot = 1;
                    }

                }

                echo $output.$bot."\n";
            }

        }
    }

}
