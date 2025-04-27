<?php
require_once __DIR__ . '/../includes/session.php';
include '../config/database.php';

// Fetch filter values
$filter_date = $_GET['date'] ?? date('Y-m-d'); // Default to today
$filter_professor = $_GET['professor'] ?? '';
$filter_status = $_GET['status'] ?? '';

// Fetch professors for dropdown filter
$professors_query = "SELECT id, name FROM professors ORDER BY name ASC";
$professors_result = $conn->query($professors_query);

// Build query for attendance records
$sql = "SELECT a.*, p.name FROM attendance a
        JOIN professors p ON a.professor_id = p.id
        WHERE DATE(a.check_in) = ?";

$params = [$filter_date];
$types = "s";

// Apply professor filter if selected
if (!empty($filter_professor)) {
    $sql .= " AND p.id = ?";
    $params[] = $filter_professor;
    $types .= "i";
}

// Apply status filter if selected
if (!empty($filter_status)) {
    $sql .= " AND a.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

$sql .= " ORDER BY a.check_in DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | Attendance System</title>

    <!-- Bootstrap & Custom Styles -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>

    <div class="container mt-4">
        <h2 class="text-center">📊 Attendance Reports</h2>

        <!-- Filters -->
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Select Date</label>
                <input type="date" name="date" class="form-control" value="<?= $filter_date; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Select Professor</label>
                <select name="professor" class="form-control">
                    <option value="">All Professors</option>
                    <?php while ($professor = $professors_result->fetch_assoc()) : ?>
                        <option value="<?= $professor['id']; ?>" <?= ($filter_professor == $professor['id']) ? 'selected' : ''; ?>>
                            <?= $professor['name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="Present" <?= ($filter_status == "Present") ? 'selected' : ''; ?>>Present</option>
                    <option value="Late" <?= ($filter_status == "Late") ? 'selected' : ''; ?>>Late</option>
                    <option value="Absent" <?= ($filter_status == "Absent") ? 'selected' : ''; ?>>Absent</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
            </div>
        </form>

        <!-- Attendance Table -->
        <table class="table table-bordered" id="attendance-table">
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>Name</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Work Duration</th>
                    <th>Date</th>
                    <th>Location</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = "SELECT a.*, p.name 
                        FROM attendance a
                        JOIN professors p ON a.professor_id = p.id
                        ORDER BY a.checkin_date DESC";
                $result = $conn->query($sql);

                while ($row = $result->fetch_assoc()) {
                    $timeOutDisplay = $row['check_out'] ? $row['check_out'] : "<span class='badge bg-danger'>Pending</span>";
                    $locationLink = ($row['latitude'] && $row['longitude']) ?
                        "<a href='https://www.google.com/maps?q={$row['latitude']},{$row['longitude']}' target='_blank' class='btn btn-info btn-sm'>📍 View Location</a>" :
                        "N/A";

                    echo "<tr>
                            <td><img src='../uploads/{$row['face_scan_image']}' width='50'></td>
                            <td>{$row['name']}</td>
                            <td>{$row['check_in']}</td>
                            <td>{$timeOutDisplay}</td>
                            <td>{$row['work_duration']}</td>
                            <td>{$row['checkin_date']}</td>
                            <td>{$locationLink}</td>
                            <td><span class='badge bg-warning'>{$row['status']}</span></td>
                          </tr>";
                }
                ?>
            </tbody>
        </table>

        <!-- Export Buttons -->
        <div class="d-flex justify-content-end mt-3">
            <a href="../api/export.php?format=pdf&date=<?= $filter_date; ?>&professor=<?= $filter_professor; ?>&status=<?= $filter_status; ?>"
                class="btn btn-danger me-2"><i class="fas fa-file-pdf"></i> Export PDF</a>
            <a href="../api/export.php?format=csv&date=<?= $filter_date; ?>&professor=<?= $filter_professor; ?>&status=<?= $filter_status; ?>"
                class="btn btn-success"><i class="fas fa-file-csv"></i> Export CSV</a>
        </div>
    </div>

    <script>
        function toggleDarkMode() {
            document.body.classList.toggle("dark-mode");
            localStorage.setItem("dark-mode", document.body.classList.contains("dark-mode") ? "enabled" : "disabled");
        }

        $(document).ready(function() {
            if (localStorage.getItem("dark-mode") === "enabled") {
                document.body.classList.add("dark-mode");
            }
        });
    </script>

</body>

</html>
