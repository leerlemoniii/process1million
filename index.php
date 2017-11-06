<?php
// show errors, for testing only
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
// extend execution time, would normally not do this, as long process I usually run as CLI, done for quick dev
ini_set('max_execution_time', 3000);
// set memory high to limit problems with the memory used by faker library, and so we can handle very large arrays
ini_set('memory_limit','2048M');
// more reporting
error_reporting(E_ALL);
// using this library to make it easier to generate the fake data needed
require_once("fake/autoload.php");
// using config files so I can hide my sql server info
require_once("config.php");
// number of rows per query may need to adjust depending on your mysql config (bigger is better for speed)
$counter = 10000;
// number of batches to get to 1 million (actually any number you want) = $batches*$counter
$batches = 100;
// using 1 mysql connection for this, could maybe get more speed with multiple connection
$conn = new mysqli($myserver, $myuser, $mypassword);
//used for running the create table and creat db only once would be a waste to run multiple times
$dbSelected = false;
$tableCreated = false;
// will throw error and quit on wrong connection info
if (mysqli_connect_errno()) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    exit();
}
// dataset will create our arrays then import them to the db.  this may be slower then using an external file(json or csv), but since 1million records in an array is so large, and I did not have a dataset this was most efficient for this
function dataSet(){
    $faker = Faker\Factory::create();
    global $counter;
    global $batches;
    for($j = 0; $j < $batches; $j++) {
        // using splfixedarray because they are mor memory efficient than regular vars
       $user = new SplFixedArray($counter);
        for ($i = 0; $i < $counter; $i++) {
            $user[$i] = new SplFixedArray(11);
            $user[$i][0] = $faker->userName;
            $user[$i][1] = $faker->firstName;
            $user[$i][2] = $faker->lastName;
            $user[$i][3] = $faker->email;
            $user[$i][4] = $faker->word;
            $user[$i][5] = $faker->phoneNumber;
            $user[$i][6] = $faker->address;
            $user[$i][7] = $faker->city;
            $user[$i][8] = $faker->stateAbbr;
            $user[$i][9] = $faker->word;
            $user[$i][10] = $faker->postcode;
        }
        //create bulkload to mysql table
        echo("loop ran ". $j."<br>" );
        mysqlImport($user);
    }

}
//just used to organize the order that some things need to occur
function mysqlImport($data){
    createDBIfNeeded();
    createTableIfNeeded();
    createBulkImportStatement($data);
}
//create and run the sql query to insert the data
function createBulkImportStatement($data){
    global $conn;
    global $mytable;
    $sql = "INSERT INTO ".$mytable." (user_name,first_name,last_name,email,pass,phone,address,city,state,country,postal_code) VALUES ";
    $datalen = count($data);
    echo("     Running rows per query = ".$datalen."<br>");
    $i = 0;
    foreach($data as $key => $row) {
        //used real_escape_string here as a simple test in the real world would probably create a number of data sanity checks.
        $sql .= "('".$conn->real_escape_string($row[0])."', ";
        $sql .= "'".$conn->real_escape_string($row[1])."', ";
        $sql .= "'".$conn->real_escape_string($row[2])."', ";
        $sql .= "'".$conn->real_escape_string($row[3])."', ";
        $sql .= "'".$conn->real_escape_string($row[4])."', ";
        $sql .= "'".$conn->real_escape_string($row[5])."', ";
        $sql .= "'".$conn->real_escape_string($row[6])."', ";
        $sql .= "'".$conn->real_escape_string($row[7])."', ";
        $sql .= "'".$conn->real_escape_string($row[8])."', ";
        $sql .= "'".$conn->real_escape_string($row[9])."', ";
        if(++$i === $datalen){
            $sql .= "'".$conn->real_escape_string($row[10])."')";
        } else {
            $sql .= "'".$conn->real_escape_string($row[10])."'), ";
        }
    }
    //in real world you would probably want to exit here to ensure good data, and return with the record(s) that errored out we just report to screen here.
    if($conn->query($sql) !== TRUE) {
        //echo($sql."/n");
        printf("Error Creating records %s", $conn->error);
    }
    return true;
}
// see if the database is already in DB.  This creates a tiny amount of delay, but is the safest way to make sure we have a database, or create the one we need.
function checkDatabaseExists(){
    global $conn;
    global $mydb;
    if($result = mysqli_query($conn, "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '".$mydb."'")){
        $row = mysqli_fetch_row($result);
        if($row[0] !== $mydb){
           return false;
        } else {
            return true;
        }
    }
}
// creates the db if it does not exist, and select it as the default db.
function createDBIfNeeded(){
    global $conn;
    global $mydb;
    global $dbSelected;
    if(!$dbSelected) {
        if (!checkDatabaseExists()) {
            $sql = "CREATE DATABASE " . $mydb . ";";
            if ($conn->query($sql) === TRUE) {
                echo 'Database "tests" successfully created';
                mysqli_select_db($conn, $mydb);
                $dbSelected = true;
            } else {
                echo 'Error: ' . $conn->error;
            }
        } else {
            mysqli_select_db($conn, $mydb);
            $dbSelected = true;
        }
    }
}
// create the table if we do not have table already.
function createTableIfNeeded()
{
    global $conn;
    global $mytable;
    global $tableCreated;
    if (!$tableCreated) {
        $sql = "CREATE TABLE IF NOT EXISTS " . $mytable . " (ID int NOT NULL AUTO_INCREMENT, PRIMARY KEY(ID),user_name varchar(50),first_name varchar(50),last_name varchar(50),email varchar(50),pass varchar(50),phone varchar(15),address varchar(100),city varchar(25),state varchar(2),country varchar(50),postal_code varchar(15));";
        if(!$conn->query($sql)){
            printf("Error Creating Table %s", $conn->error);
        } else {
            $tableCreated = true;
        }
    }
}
function cleardb(){
    global $conn;
    global $mytable;
    global $mydb;
    echo("Clearing DB<br>");
    mysqli_select_db($conn, $mydb);
    $sql = "TRUNCATE TABLE ".$mytable;
    if(!$conn->query($sql)){
        printf("Error Clearing Table %s", $conn->error);
    } else {
        echo("success<br>");
    }
}
//used if here switch would probably be cleaner, but this was ok for testing
if(isset($_GET["proc"])) {
    if ($_GET["proc"] === "1") {
        $start = microtime(true);
        dataSet();
        $end = microtime(true);
        echo('It took ' . ($end - $start) . ' seconds!');
    }
    if ($_GET["proc"] === "2") {
        // number of rows per query may need to adjust depending on your mysql config (bigger is better for speed)
        $counter = 10000;
        // number of batches to get to 1 million (actually any number you want) = $batches*$counter
        $batches = 1;
        $start = microtime(true);
        dataSet();
        $end = microtime(true);
        echo('It took ' . ($end - $start) . ' seconds!<br>');
    }
    if ($_GET["proc"] === "3") {
        cleardb();
    }
}
?>
</head>
<body>
<a href="index.php?proc=1">Process Fake Data</a> 1 million<br>
<a href="index.php?proc=2">Process Fake Data</a>  10 thousand<br><br>
<a href="index.php?proc=3">Clear Database</a> so we can start clean
<hr>
<a href="ViewData.html">Tool to view the million records, and second testing reqs.</a>
</body>
</html>
