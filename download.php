<?php

appLog("Start of include");


//$app = new TrackDownloads(true);
$app = new TrackDownloads();

$app->run();
$app->conn->close();

/**
 * Class TrackDownloads
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
 *
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
        $baseDir = "/home/eugene/code/track-downloads";
//        $path    = realpath($baseDir . "/" . basename($_GET['file']));

        $filename = basename($_GET['file']);
//        $filename = "brochure.pdf";

        $path = realpath($baseDir . "/$filename");

//        if (dirname($path) == $baseDir) {
//            if (!$this->isBot())
        $this->sql("
                  INSERT INTO downloads
                    SET filename='" . mysqli_real_escape_string($this->conn, $filename) . "' ON DUPLICATE KEY UPDATE stats = stats + 1");

        header("Cache-Control: public");
        header("Content-Description: File Transfer");
        header("Content-Disposition: attachment; filename=" . basename($_GET['file']));
        header("Content-Length: " . filesize($path));
        header("Content-Type: application/force-download");
        header("Content-Transfer-Encoding: binary");

        ob_clean();
        ob_end_flush();

//        readfile($path);

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
        $host     = "localhost";
        $username = "root";
        $password = "password";
        $db       = "track_downloads";
        return [$host, $username, $password, $db];
    }

    function sql($sql)
    {
        if ($this->conn->query($sql) === TRUE) {
            echo "SQL: $sql: OK\n";
        } else {
            echo "Error with SQL query: " . $this->conn->error;
        }
    }

    function execute($sql)
    {

        list($host, $username, $password) = $this->credentials();

        $conn = new mysqli($host, $username, $password);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        if ($conn->query($sql) === TRUE) {
            echo "Execute: $sql: OK\n";
        } else {
            echo "Error executing SQL: " . $conn->error;
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

