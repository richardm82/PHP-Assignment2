<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'storedInfo.php';
// ******************************** USER INPUT REQUIRED HERE **********************************
$mysqli = new mysqli('oniddb.cws.oregonstate.edu', 'millerri-db', $myPassword, 'millerri-db');
// ********************************************************************************************
//$mysqli = new mysqli('127.0.0.1', 'root', $mypassword, 'mydb');
if ($mysqli->connect_errno) {
    echo 'Failed to connect to MySQL: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_errno . '<br>';
    exit();
}
$table = 'videos';
$tblShown = false;
if (!mysqli_num_rows($mysqli->query("SHOW TABLES LIKE '$table'"))) {
    if (!$mysqli->query("CREATE TABLE $table(id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255) NOT NULL UNIQUE,
                        category VARCHAR(255) NOT NULL, length INT NOT NULL, rented BOOLEAN DEFAULT 0)"))
        echo 'Table creation failed: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_errno . '<br>';
}
//displayTable($mysqli, $table, NULL);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnAddVideo'])) {
    $input_valid = true;
    
    if (!isset($_POST['name']) || trim($_POST['name']) == '') {
        echo 'Enter a video name.<br>';
        $input_valid = false;
    }
    if (!isset($_POST['category']) || trim($_POST['category']) == '') {
        echo 'Enter a video category.<br>';
        $input_valid = false;
    }   
    if (!isset($_POST['length']) || trim($_POST['length']) == '' || !isint_ref($_POST['length']) || intval($_POST['length']) < 1) {
        echo 'Enter a positive integer for video length (min).<br>';
        $input_valid = false;
    }
    if ($input_valid) {    
        $name = $_POST['name'];
        
        if (mysqli_num_rows($mysqli->query("SELECT name FROM $table WHERE name = '$name'")))
            echo "$name you entered already exists. No need to add it.<br><br>";
        else {
            $cate = $_POST['category'];
            $len = $_POST['length'];
            if (!$mysqli->query("INSERT INTO $table (name, category, length) VALUES ('$name', '$cate', $len)"))
                echo 'Insert failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
        }
        //displayTable($mysqli, $table, NULL);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnDeleteVideo'])) {
    $name = $_POST['btnDeleteVideo'];
    $name = str_replace('_', ' ', $name);
    
    if (!$mysqli->query("DELETE FROM $table WHERE name = '$name'"))
        echo 'Delete failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
    //displayTable($mysqli, $table, NULL);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnCheckInOutVideo'])) {
    $name = $_POST['btnCheckInOutVideo'];
    $name = str_replace('_', ' ', $name);
    
    if (mysqli_num_rows($mysqli->query("SELECT name FROM $table WHERE name = '$name' AND rented = 0"))) // available => not checked out
        $mysqli->query("UPDATE $table SET rented = 1 WHERE name = '$name'");
    else // not available => checked out
        $mysqli->query("UPDATE $table SET rented = 0 WHERE name = '$name'");
    //displayTable($mysqli, $table, NULL);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deleteAll'])) {
    if (mysqli_num_rows($mysqli->query("SELECT name FROM $table"))) {
        $mysqli->query("TRUNCATE TABLE $table"); // delete all rows
        $mysqli->query("ALTER TABLE $table AUTO_INCREMENT = 1"); // reset 'id' to 1
    }
    //displayTable($mysqli, $table, NULL);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnFilterMovies'])) {
    $cate = $_POST['dropdown_category'];
    displayTable($mysqli, $table, $cate);
    
    $tblShown = true;
}
function displayTable(&$mysqli, &$table, $filterCate) {
    if (!mysqli_num_rows($mysqli->query("SELECT id FROM $table"))) {
        echo '<p>No videos to display...</p>';
        return;
    }
    
    $stmt = NULL;
    if ($filterCate == NULL || $filterCate == 'all_movies')
        $stmt = $mysqli->prepare("SELECT name, category, length, rented FROM $table ORDER BY category, name");
    else { // filter by category
        if (!mysqli_num_rows($mysqli->query("SELECT id FROM $table WHERE category != '$filterCate'"))) {
            echo "<p>'$filterCate' Videos of the selected categories do not exist. No videos to display...</p>";
            return;
        }
        $stmt = $mysqli->prepare("SELECT name, category, length, rented FROM $table WHERE category = '$filterCate' ORDER BY category, name");
    }
        
    if (!$stmt->execute()) {
        echo 'Query failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
        return;
    }
    
    $vidName = NULL;
    $vidCat = NULL;
    $vidLen = NULL;
    $vidRented = NULL;
    
    if (!$stmt->bind_result($vidName, $vidCat, $vidLen, $vidRented)) {
        echo 'Binding output parameters failed: (' . $stmt->errno . ') ' . $stmt->error . '<br>';
        return;
    }
    
    echo '<table border="2" <tr><td bgcolor="Grey"><font color="Black"><b>Name</b></font>
            </td><td bgcolor="Grey"><b>Category</b></td>
            <td bgcolor="Grey"><b>Length</b></td><td bgcolor="Grey"><b>Rented</b></td>
            <td bgcolor="Grey"><b>Delete</b></td><td bgcolor="Grey"><b>Status</b></td></tr>';
    
    while ($stmt->fetch()) {
        echo "<tr><td>$vidName</td><td>$vidCat</td>
            <td>$vidLen</td>";
        if ($vidRented)
            echo "<td>checked out</td>";
        else
            echo "<td>available</td>";
            
        // $_POST['$vidName'] contains only the first part of the string if it has a space
        // need to prevent the string from being separated by a space in it
        $vidName = str_replace(' ', '_', $vidName);
        echo "<td><form action='interface.php' method='post'>
                    <button name='btnDeleteVideo' value=$vidName>Delete</button>
                </form></td>
                <td><form action='interface.php' method='post'>
                    <button name='btnCheckInOutVideo' value=$vidName>In/Out</button>
                </form></td>
            </tr>";
    }
    echo '</table><br>';
}
function displayMovieCategory(&$mysqli, &$table) {
    if (!mysqli_num_rows($mysqli->query("SELECT name FROM $table"))) {
        //echo '<p>No movie categories to display...</p>';
        return;
    }
    
    $stmt = $mysqli->prepare("SELECT category FROM $table GROUP BY category ORDER BY category");
    if (!$stmt->execute()) {
        echo 'Query failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
        return;
    }
    
    $vidCat = NULL;
    
    if (!$stmt->bind_result($vidCat)) {
        echo 'Binding output parameters failed: (' . $stmt->errno . ') ' . $stmt->error . '<br>';
        return;
    }
    
    echo '&nbsp&nbsp<select name="dropdown_category">';
    while ($stmt->fetch())
        echo "<option value='$vidCat'>$vidCat</option>";
    echo "<option value='all_movies'>All Movies</option>";
    echo '</select>';
    echo "&nbsp&nbsp<button name='btnFilterMovies' value='filterMovies'>Filter Movies</button>";
}
function isint_ref(&$val) {
    $isint = false;
    if (is_numeric($val)) {
        if (strpos($val, '.')) {
            $diff = floatval($val) - intval($val);
            if ($diff > 0)
                $isint = false;
            else {
                $val = intval($val);
                $isint = true;
            }
        }
        else
            $isint = true;
    }   
    return $isint;
}
if (!$tblShown) {
    displayTable($mysqli, $table, NULL);
    $tblShown = true;
}
echo '<!DOCTYPE html> 
    <html lang="en">
    <head>
        <meta charset="utf-8"/>
        <title>Database Interface</title>
    </head>
    <body>';
    
echo "<form action='interface.php' method='post'>
        <fieldset>
            <legend>Add a video</legend>
            Name: <input type='text' name='name'/><br>
            Category: <input type='text' name='category'/><br>
            Length (min): <input type='number' name='length'/>&nbsp&nbsp
            <input type='submit' name='btnAddVideo' value='Add Video'/>
        </fieldset>
        <br>
        <button name='deleteAll' value='deleteAllVideo'>Delete All Videos</button>&nbsp";
displayMovieCategory($mysqli, $table);
echo '</form>
    </body>
    </html>';
mysqli_close($mysqli);
?>
