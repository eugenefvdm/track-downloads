<?php

appLog("Start of download tracker include", "DEBUG");

//$app = new TrackDownloads(true); // Use this line when creating a new database
$app = new TrackDownloads();

$app->run();
$app->conn->close();

/**
 * Class TrackDownloads
 *
 * Save to /tracker/download.php
 *
 * Ensure 777 for log file if stored outside of home directory
 *
 * Test with https://site.com/tracker/download.php?file=xyz.pdf
 *
 * https://stackoverflow.com/questions/8155652/best-way-to-track-direct-file-downloads
 * https://www.w3schools.com/pHP/php_mysql_create.asp
 * https://www.w3schools.com/pHP/php_mysql_create_table.asp
 *
 * Apache
 *
 * .htaccess
 *
 * RewriteEngine on
 * RewriteRule ^(.*).(rar|zip|pdf)$ http://xy.com/downloads/download.php?file=$1.$2 [R,L]
 *
 * NGINX
 *
 * location /downloads/ {
 *    rewrite /downloads/(.*).(rar|zip|pdf)$ /tracker/download.php?file=$1.$2;
 * }
 *
 */
class TrackDownloads
{

    public $conn;

    /**
     * TrackDownloads constructor.
     *
     * Calling this constructor with true will drop and recreate the database - be careful
     *
     * @param bool $setup
     */
    function __construct($setup = false)
    {
        if ($setup) {
            $this->resetDatabase();
        }
        $this->conn = $this->dbConnection();
    }

    function run()
    {
        appLog("Now running");

        $baseDir = "/home/xpatwebcom/public_html/downloads";

        $path    = realpath($baseDir . "/" . basename($_GET['file']));

        appLog("The baseDir is $baseDir and the full path is $path");

        $filename = basename($_GET['file']);

        appLog("The filename is $filename");

        $path = realpath($baseDir . "/$filename");

        appLog("The path is $path");

//        if (dirname($path) == $baseDir) {
//            if (!$this->isBot())

        $ip = $_SERVER['REMOTE_ADDR'];

        appLog("The IP is: $ip");

        $this->sql("
                  INSERT INTO downloads
                    SET ip_address='$ip', filename='" . mysqli_real_escape_string($this->conn, $filename) . "'");


        header("Cache-Control: public");
        header("Content-Description: File Transfer");
        // Use inline instead of attachment to display PDFs inline
        header("Content-Disposition: inline; filename=" . basename($_GET['file']));
        header("Content-Length: " . filesize($path));
//        header("Content-Type: application/force-download");
        header("Content-Type: application/pdf");
        header("Content-Transfer-Encoding: binary");

        appLog("The database insert is done, now sending the file to the browser...");

        ob_clean();
        ob_end_flush();

        readfile($path);

//        }
    }

    function dbConnection()
    {
        list($host, $username, $password, $db) = $this->credentials();
        $conn = new mysqli($host, $username, $password, $db);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        return $conn;
    }

    function credentials()
    {
        $host     = "1.2.3.4";
        $username = "username";
        $password = "password";
        $db       = "track_downloads";
        return [$host, $username, $password, $db];
    }

    function sql($sql)
    {
        appLog ("In SQL Function");
        if ($this->conn->query($sql) === TRUE) {
            appLog("SQL: $sql: OK");
        } else {
            appLog("Error with SQL query: " . $this->conn->error);
        }
        // Should we $conn->close(); here?
    }

    function execute($sql)
    {

        list($host, $username, $password) = $this->credentials();

        $conn = new mysqli($host, $username, $password);
        if ($conn->connect_error) {
            appLog("Connection failed: " . $conn->connect_error);
            die("Connection failed: " . $conn->connect_error);
        }

        if ($conn->query($sql) === TRUE) {
            appLog("Execute: $sql: OK");
        } else {
            appLog("Error executing SQL: " . $conn->error);
        }

        $conn->close();

    }

    function isBot()
    {
        $bots = array("Teoma", "alexa", "froogle", "Gigabot", "inktomi",
            "looksmart", "URL_Spider_SQL", "Firefly", "NationalDirectory",
            "Ask Jeeves", "TECNOSEEK", "InfoSeek", "WebFindBot", "girafabot",
            "crawler", "www.galaxy.com", "Googlebot", "Scooter", "Slurp",
            "msnbot", "appie", "FAST", "WebBug", "Spade", "ZyBorg", "rabaz",
            "Baiduspider", "Feedfetcher-Google", "TechnoratiSnoop", "Rankivabot",
            "Mediapartners-Google", "Sogou web spider", "WebAlta Crawler", "TweetmemeBot",
            "Butterfly", "Twitturls", "Me.dium", "Twiceler");

        foreach ($bots as $bot) {
            if (strpos($_SERVER['HTTP_USER_AGENT'], $bot) !== false)
                return true;    // Is a bot
        }
        return false;
    }

    private function resetDatabase()
    {
        $this->execute("DROP DATABASE track_downloads");
        $this->execute("CREATE DATABASE track_downloads");
        $this->conn = $this->dbConnection();
        $this->sql("
              CREATE TABLE `downloads` (
                `filename` varchar(255),
                `ip_address` varchar(255),
                  `stats` int(11),
                   `created_at` datetime default current_timestamp,
              PRIMARY KEY  (`filename`))");
    }

}

function appLog($message, $severity = 'INFO')
{
    $message  = date("Y-m-d H:i:s") . ":" . $severity . ":" . $message;
    file_put_contents(
        '/tmp/app.log',
        $message . "\n",
        FILE_APPEND
    );
}
