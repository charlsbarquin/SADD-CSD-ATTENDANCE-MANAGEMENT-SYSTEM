<?php
function getAdminName($conn, $adminId) {
    if (!$adminId) return 'System';
    
    $stmt = $conn->prepare("SELECT username FROM admins WHERE id = ?");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0 ? $result->fetch_assoc()['username'] : 'Unknown';
}