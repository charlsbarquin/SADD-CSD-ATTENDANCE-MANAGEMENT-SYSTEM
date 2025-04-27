<?php
include '../config/database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $professor_id = $_POST['professor_id'];
    $image_data = $_POST['image_data'];
    $latitude = isset($_POST['latitude']) ? $_POST['latitude'] : null;
    $longitude = isset($_POST['longitude']) ? $_POST['longitude'] : null;

    // Get today's date
    $today_date = date("Y-m-d");

    // Check if the professor already checked in today
    $check_query = "SELECT id FROM attendance WHERE professor_id = ? AND DATE(check_in) = ? LIMIT 1";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("is", $professor_id, $today_date);
    $stmt->execute();
    $check_result = $stmt->get_result();

    if ($check_result->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "❌ You have already checked in today!"]);
        exit;
    }

    // Convert Base64 Image to File
    $image_name = "checkin_" . time() . ".jpg";
    $image_path = "../uploads/" . $image_name;

    // Decode Base64 image and save it
    $image_parts = explode(";base64,", $image_data);
    if (count($image_parts) == 2) {
        $image_decoded = base64_decode($image_parts[1]);
        file_put_contents($image_path, $image_decoded);
    } else {
        echo json_encode(["status" => "error", "message" => "❌ Invalid image format"]);
        exit;
    }

    // **Ensure Status is Set to "Present"** immediately upon check-in
    $status = "Present"; // As soon as the professor checks in, they are marked "Present"

    // Save Image Path, Status, and Geolocation Data to Database
    $sql = "INSERT INTO attendance (professor_id, check_in, face_scan_image, status, latitude, longitude) 
            VALUES (?, NOW(), ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", $professor_id, $image_name, $status, $latitude, $longitude);

    if ($stmt->execute()) {
        // Retrieve the latest check-in data for the professor
        $result = $conn->query("SELECT attendance.*, professors.name FROM attendance 
                                JOIN professors ON attendance.professor_id = professors.id 
                                WHERE attendance.professor_id = '$professor_id' 
                                ORDER BY attendance.check_in DESC LIMIT 1");

        if ($row = $result->fetch_assoc()) {
            echo json_encode([
                "status" => "success",
                "message" => "✅ Check-in recorded!",
                "professor_id" => $row['professor_id'],
                "name" => $row['name'],
                "check_in" => $row['check_in'],
                "status" => $row['status'], // Should always be "Present" now
                "face_scan_image" => '../uploads/' . $row['face_scan_image'],
                "recorded_at" => $row['check_in'],
                "latitude" => $row['latitude'],
                "longitude" => $row['longitude'],
                "location_link" => ($row['latitude'] && $row['longitude']) ? "https://www.google.com/maps?q={$row['latitude']},{$row['longitude']}" : "N/A"
            ]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "❌ Database error: " . $conn->error]);
    }

    $stmt->close();
}
?>
