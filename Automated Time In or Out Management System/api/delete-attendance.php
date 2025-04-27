<?php
include '../config/database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];

    $sql = "DELETE FROM attendance WHERE id='$id'";
    
    if ($conn->query($sql) === TRUE) {
        echo "✅ Record Deleted!";
    } else {
        echo "❌ Error: " . $conn->error;
    }
}
?>
