<?php
// show errors, for testing only
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once("config.php");
$conn = new mysqli($myserver, $myuser, $mypassword, $mydb);
if(isset($_GET["proc"])) {
    if ($_GET["proc"] === "1") {
        // get reps to fill dropdown
        $sql = "select repname from reps";
        $result = $conn->query($sql)
            or die ($conn->error);
        $response = array();
        while ($row = $result->fetch_assoc()) {
            //echo($row);
            //echo($row["repname"]);
            $repname = $row["repname"];
            $response[] = $repname;
        }
        writeJson($response);
    }
    if ($_GET["proc"] === "2") {
        //get leftjoin data
        $sql = "SELECT user.user_name, user.first_name,user.last_name,user.email,user.state,reps.repname, reps.state FROM (SELECT * from user Limit 100) as user LEFT JOIN reps ON user.state = reps.state ORDER BY user.last_name ";
        runQuery($sql);
    }
    if ($_GET["proc"] === "3") {
        // get first all data from table users
        if(isset($_GET["start"])){
            $start = $_GET["start"];
        } else {
            $start = "0";
        }
        if(isset($_GET["limit"])){
            $limit = $_GET["limit"];
        } else {
            $limit = 10;
        }
        $sql = "select * from user LIMIT ". $limit ." OFFSET ". $start;
        runQuery($sql);
    }
}
function runQuery($sql){
    global $conn;
    $result = $conn->query($sql)
    or die ($conn->error);
    $response = array();
    while($row = $result->fetch_assoc()){
        $response[] = $row;
    }
    writeJson($response);
}
function writeJson($response){
    echo(json_encode($response));
}