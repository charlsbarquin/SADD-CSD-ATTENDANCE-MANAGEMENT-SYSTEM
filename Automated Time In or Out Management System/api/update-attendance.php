<?php
include '../config/database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $check_out = $_POST['check_out'];

    $sql = "UPDATE attendance SET check_out='$check_out' WHERE id='$id'";
    
    if ($conn->query($sql) === TRUE) {
        echo "✅ Attendance Updated!";
    } else {
        echo "❌ Error: " . $conn->error;
    }
}
?>
